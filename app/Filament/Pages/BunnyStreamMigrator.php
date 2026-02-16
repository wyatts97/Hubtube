<?php

namespace App\Filament\Pages;

use App\Models\Video;
use App\Services\BunnyStreamService;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;

class BunnyStreamMigrator extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-cloud-arrow-down';
    protected static ?string $navigationLabel = 'Bunny Migration';
    protected static ?string $navigationGroup = 'Tools';
    protected static ?int $navigationSort = 100;
    protected static string $view = 'filament.pages.bunny-stream-migrator';

    // Connection state
    public bool $isConnected = false;
    public int $bunnyTotalVideos = 0;

    // Pipeline stats
    public int $pendingDownloadCount = 0;
    public int $downloadingCount = 0;
    public int $downloadFailedCount = 0;
    public int $processingCount = 0;
    public int $processedCount = 0;
    public int $totalImported = 0;

    // Migration controls
    public bool $isMigrating = false;
    public bool $shouldStop = false;
    public int $concurrency = 1;
    public int $completedThisSession = 0;
    public int $failedThisSession = 0;

    // Active download slots — tracks video IDs currently being downloaded
    public array $activeSlots = [];

    // Session log (last 50 entries)
    public array $migrationLog = [];

    // Disk info
    public string $diskFreeSpace = '';

    // Cache key prefix for per-video download results
    private const CACHE_PREFIX = 'bunny_dl_';
    private const CACHE_MIGRATING = 'bunny_migration_active';

    public function getTitle(): string
    {
        return 'Bunny Stream Migration';
    }

    public function getSubheading(): ?string
    {
        return 'Download Bunny Stream videos and process them as native videos with thumbnails, HLS, and multi-quality transcoding.';
    }

    public function mount(): void
    {
        // Restore migration state if it was running before page reload
        if (Cache::get(self::CACHE_MIGRATING, false)) {
            $this->isMigrating = true;
            $this->shouldStop = false;
            $this->concurrency = (int) Cache::get(self::CACHE_MIGRATING . '_concurrency', 1);
        }

        $this->refreshStats();
        $this->updateDiskSpace();
        $this->detectActiveDownloads();
    }

    /**
     * Refresh all pipeline stats from the database.
     */
    public function refreshStats(): void
    {
        $bunnyVideos = Video::where('source_site', 'bunnystream')
            ->whereNotNull('source_video_id')
            ->selectRaw("
                SUM(CASE WHEN status = 'pending_download' THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN status = 'downloading' THEN 1 ELSE 0 END) as downloading,
                SUM(CASE WHEN status = 'download_failed' THEN 1 ELSE 0 END) as dl_failed,
                SUM(CASE WHEN status IN ('pending', 'processing') THEN 1 ELSE 0 END) as processing,
                SUM(CASE WHEN status = 'processed' THEN 1 ELSE 0 END) as processed,
                COUNT(*) as total
            ")
            ->first();

        $this->pendingDownloadCount = (int) ($bunnyVideos->pending ?? 0);
        $this->downloadingCount = (int) ($bunnyVideos->downloading ?? 0);
        $this->downloadFailedCount = (int) ($bunnyVideos->dl_failed ?? 0);
        $this->processingCount = (int) ($bunnyVideos->processing ?? 0);
        $this->processedCount = (int) ($bunnyVideos->processed ?? 0);
        $this->totalImported = (int) ($bunnyVideos->total ?? 0);
    }

    /**
     * Update disk space info.
     */
    public function updateDiskSpace(): void
    {
        $path = Storage::disk('public')->path('');
        $free = @disk_free_space($path);
        $total = @disk_total_space($path);

        if ($free !== false && $total !== false) {
            $freeGb = round($free / 1024 / 1024 / 1024, 1);
            $totalGb = round($total / 1024 / 1024 / 1024, 1);
            $usedPct = $total > 0 ? round((($total - $free) / $total) * 100) : 0;
            $this->diskFreeSpace = "{$freeGb}GB free of {$totalGb}GB ({$usedPct}% used)";
        } else {
            $this->diskFreeSpace = 'Unknown';
        }
    }

    /**
     * Detect any downloads that are still running (from before page reload).
     */
    private function detectActiveDownloads(): void
    {
        $this->activeSlots = Video::where('source_site', 'bunnystream')
            ->where('status', 'downloading')
            ->pluck('id')
            ->toArray();
    }

    /**
     * Test the Bunny Stream API connection.
     */
    public function testConnection(): void
    {
        $service = new BunnyStreamService();

        if (!$service->isConfigured()) {
            Notification::make()
                ->title('Bunny Stream API not configured')
                ->body('Configure API Key and Library ID in Admin → Integrations → Services.')
                ->danger()
                ->send();
            return;
        }

        $result = $service->testConnection();

        if ($result['success']) {
            $this->isConnected = true;
            $this->bunnyTotalVideos = $result['total_videos'];
            Notification::make()
                ->title('Connected to Bunny Stream')
                ->body("Library {$result['library_id']} — {$result['total_videos']} videos in library.")
                ->success()
                ->send();
        } else {
            $this->isConnected = false;
            Notification::make()
                ->title('Connection failed')
                ->body($result['error'])
                ->danger()
                ->send();
        }
    }

    /**
     * Start the migration pipeline.
     */
    public function startMigration(): void
    {
        $service = new BunnyStreamService();
        if (!$service->isConfigured()) {
            Notification::make()->title('Bunny Stream API not configured')->danger()->send();
            return;
        }

        if ($this->pendingDownloadCount === 0 && $this->downloadFailedCount === 0) {
            Notification::make()
                ->title('No videos to download')
                ->body('Import videos via WP Import first, or retry failed downloads.')
                ->warning()
                ->send();
            return;
        }

        $this->isMigrating = true;
        $this->shouldStop = false;
        $this->completedThisSession = 0;
        $this->failedThisSession = 0;
        $this->migrationLog = [];

        // Persist migration state so it survives page reloads
        Cache::put(self::CACHE_MIGRATING, true, 86400);
        Cache::put(self::CACHE_MIGRATING . '_concurrency', $this->concurrency, 86400);

        $this->dispatchDownloads();
    }

    /**
     * Poll handler — called every 3 seconds while migrating.
     * Checks for completed downloads, collects results, dispatches new ones.
     */
    public function pollProgress(): void
    {
        if (!$this->isMigrating && empty($this->activeSlots)) {
            return;
        }

        $this->updateDiskSpace();

        // Check each active slot for completion
        $stillActive = [];
        foreach ($this->activeSlots as $videoId) {
            $cacheKey = self::CACHE_PREFIX . $videoId;
            $result = Cache::get($cacheKey);

            if ($result && !empty($result['done'])) {
                Cache::forget($cacheKey);

                $logEntry = [
                    'id' => $result['video_id'],
                    'title' => $result['title'] ?? 'Unknown',
                    'bunny_id' => $result['bunny_id'] ?? '',
                    'status' => $result['success'] ? 'downloaded' : 'failed',
                    'error' => $result['error'] ?? null,
                    'time' => now()->format('H:i:s'),
                ];

                if ($result['success']) {
                    $this->completedThisSession++;
                } else {
                    $this->failedThisSession++;
                }

                array_unshift($this->migrationLog, $logEntry);
                if (count($this->migrationLog) > 50) {
                    $this->migrationLog = array_slice($this->migrationLog, 0, 50);
                }
            } else {
                // Still running — verify the video is actually still downloading
                $video = Video::find($videoId);
                if ($video && $video->status === 'downloading') {
                    $stillActive[] = $videoId;
                } else {
                    Cache::forget($cacheKey);
                }
            }
        }

        $this->activeSlots = $stillActive;
        $this->refreshStats();

        // If we should stop, don't dispatch new downloads
        if ($this->shouldStop) {
            if (empty($this->activeSlots)) {
                $this->isMigrating = false;
                Cache::forget(self::CACHE_MIGRATING);
                Cache::forget(self::CACHE_MIGRATING . '_concurrency');
            }
            return;
        }

        // Check if there are more videos to download
        if ($this->pendingDownloadCount === 0 && empty($this->activeSlots)) {
            $this->isMigrating = false;
            Cache::forget(self::CACHE_MIGRATING);
            Cache::forget(self::CACHE_MIGRATING . '_concurrency');

            Notification::make()
                ->title('All downloads complete!')
                ->body("Downloaded: {$this->completedThisSession}, Failed: {$this->failedThisSession}. Videos are now being processed by the queue.")
                ->success()
                ->send();
            return;
        }

        $this->dispatchDownloads();
    }

    /**
     * Dispatch background download processes to fill available concurrency slots.
     */
    private function dispatchDownloads(): void
    {
        $availableSlots = max(0, $this->concurrency - count($this->activeSlots));
        if ($availableSlots <= 0) {
            return;
        }

        $nextVideos = Video::where('source_site', 'bunnystream')
            ->whereNotNull('source_video_id')
            ->where('status', 'pending_download')
            ->whereNotIn('id', $this->activeSlots)
            ->orderBy('id')
            ->limit($availableSlots)
            ->get();

        foreach ($nextVideos as $video) {
            $this->activeSlots[] = $video->id;

            Log::info('BunnyMigrator: dispatching download', [
                'video_id' => $video->id,
                'title' => $video->title,
                'active_slots' => count($this->activeSlots),
            ]);

            Process::timeout(1800)->start(
                "php " . base_path('artisan') . " bunny:download-single {$video->id}"
            );
        }
    }

    /**
     * Stop the migration after current downloads finish.
     */
    public function stopMigration(): void
    {
        $this->shouldStop = true;
        Cache::forget(self::CACHE_MIGRATING);
        Cache::forget(self::CACHE_MIGRATING . '_concurrency');

        Notification::make()
            ->title('Stopping migration...')
            ->body('Waiting for ' . count($this->activeSlots) . ' active download(s) to finish.')
            ->warning()
            ->send();
    }

    /**
     * Reset all download_failed videos back to pending_download for retry.
     */
    public function retryFailed(): void
    {
        $count = Video::where('source_site', 'bunnystream')
            ->where('status', 'download_failed')
            ->update([
                'status' => 'pending_download',
                'failure_reason' => null,
            ]);

        if ($count === 0) {
            Notification::make()->title('No failed downloads to retry')->info()->send();
            return;
        }

        $this->refreshStats();
        Notification::make()
            ->title("Reset {$count} failed videos")
            ->body('They will be picked up when you start or resume migration.')
            ->success()
            ->send();
    }

    /**
     * Download a single video by ID.
     */
    public function downloadSingle(int $videoId): void
    {
        $video = Video::find($videoId);

        if (!$video || !$video->source_video_id) {
            Notification::make()->title('Video not found or has no Bunny ID')->danger()->send();
            return;
        }

        if (!in_array($video->status, ['pending_download', 'download_failed', 'failed'])) {
            Notification::make()
                ->title('Cannot download')
                ->body("Video status is '{$video->status}'. Only pending_download or failed videos can be downloaded.")
                ->warning()
                ->send();
            return;
        }

        if (in_array($videoId, $this->activeSlots)) {
            Notification::make()->title('This video is already downloading')->warning()->send();
            return;
        }

        $this->activeSlots[] = $video->id;

        if (!$this->isMigrating) {
            $this->isMigrating = true;
            $this->shouldStop = true; // Will stop after this single download
        }

        Process::timeout(1800)->start(
            "php " . base_path('artisan') . " bunny:download-single {$videoId}"
        );

        Notification::make()
            ->title("Downloading: {$video->title}")
            ->body('Download started in the background.')
            ->info()
            ->send();
    }

    /**
     * Get pending/failed videos for the queue table.
     */
    public function getQueuedVideosProperty(): array
    {
        return Video::where('source_site', 'bunnystream')
            ->whereNotNull('source_video_id')
            ->whereIn('status', ['pending_download', 'downloading', 'download_failed'])
            ->orderByRaw("FIELD(status, 'downloading', 'pending_download', 'download_failed')")
            ->limit(30)
            ->get()
            ->map(fn ($v) => [
                'id' => $v->id,
                'title' => $v->title,
                'source_video_id' => $v->source_video_id,
                'status' => $v->status,
                'failure_reason' => $v->failure_reason,
                'views_count' => $v->views_count,
            ])
            ->toArray();
    }

    /**
     * Get recently completed downloads (downloaded + processing + processed).
     */
    public function getRecentDownloadsProperty(): array
    {
        return Video::where('source_site', 'bunnystream')
            ->whereNotNull('source_video_id')
            ->whereIn('status', ['pending', 'processing', 'processed'])
            ->orderByDesc('updated_at')
            ->limit(15)
            ->get()
            ->map(fn ($v) => [
                'id' => $v->id,
                'title' => $v->title,
                'source_video_id' => $v->source_video_id,
                'video_path' => $v->video_path,
                'status' => $v->status,
                'updated_at' => $v->updated_at?->diffForHumans(),
            ])
            ->toArray();
    }

    /**
     * Get progress percentage for the overall pipeline.
     */
    public function getProgressPercentProperty(): int
    {
        if ($this->totalImported === 0) return 0;
        return (int) round(($this->processedCount / $this->totalImported) * 100);
    }
}

<?php

namespace App\Filament\Pages;

use App\Models\Video;
use App\Services\BunnyStreamService;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

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

    // Local stats
    public int $embeddedCount = 0;
    public int $nativeCount = 0;
    public int $downloadFailedCount = 0;

    // Migration controls
    public string $targetDisk = 'public';
    public bool $isMigrating = false;
    public bool $shouldStop = false;
    public int $migratedCount = 0;
    public int $totalToMigrate = 0;
    public string $currentVideoTitle = '';
    public int $currentVideoId = 0;

    // Log of completed/failed downloads in this session
    public array $migrationLog = [];

    // Cache keys for background download coordination
    private const CACHE_DOWNLOADING = 'bunny_migration_downloading';
    private const CACHE_CURRENT_VIDEO = 'bunny_migration_current_video';
    private const CACHE_RESULT = 'bunny_migration_result';

    public function getTitle(): string
    {
        return 'Bunny Stream Migration';
    }

    public function getSubheading(): ?string
    {
        return 'Download embedded Bunny Stream videos to local or S3/Wasabi storage, converting them into native videos.';
    }

    public function mount(): void
    {
        $this->refreshStats();
    }

    /**
     * Refresh local database stats about embedded vs native videos.
     */
    public function refreshStats(): void
    {
        $this->embeddedCount = Video::where('is_embedded', true)
            ->where('source_site', 'bunnystream')
            ->whereNotNull('source_video_id')
            ->where('status', '!=', 'failed')
            ->count();

        $this->nativeCount = Video::where('is_embedded', false)
            ->whereNotNull('video_path')
            ->count();

        $this->downloadFailedCount = Video::where('status', 'failed')
            ->where('is_embedded', true)
            ->count();
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
                ->body('Configure Bunny Stream API Key and Library ID in Admin → Integrations.')
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
                ->body("Library {$result['library_id']} — {$result['total_videos']} videos found.")
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
     * Start the migration — downloads the next video, then Livewire polls downloadNext().
     */
    public function startMigration(): void
    {
        $service = new BunnyStreamService();

        if (!$service->isConfigured()) {
            Notification::make()->title('Bunny Stream API not configured')->danger()->send();
            return;
        }

        $this->totalToMigrate = Video::where('is_embedded', true)
            ->where('source_site', 'bunnystream')
            ->whereNotNull('source_video_id')
            ->whereNotIn('status', ['failed'])
            ->count();

        if ($this->totalToMigrate === 0) {
            Notification::make()
                ->title('No videos to migrate')
                ->body('All embedded Bunny Stream videos have already been downloaded.')
                ->warning()
                ->send();
            return;
        }

        $this->isMigrating = true;
        $this->shouldStop = false;
        $this->migratedCount = 0;
        $this->migrationLog = [];
        $this->currentVideoTitle = '';
        $this->currentVideoId = 0;

        // Download the first one immediately
        $this->downloadNext();
    }

    /**
     * Poll handler: check if a background download finished, then kick off the next one.
     * Called by Livewire polling every few seconds.
     */
    public function downloadNext(): void
    {
        if ($this->shouldStop || !$this->isMigrating) {
            $this->isMigrating = false;
            $this->currentVideoTitle = '';
            Cache::forget(self::CACHE_DOWNLOADING);
            Cache::forget(self::CACHE_CURRENT_VIDEO);
            Cache::forget(self::CACHE_RESULT);
            $this->refreshStats();
            return;
        }

        // Check if a download is currently running in the background
        if (Cache::has(self::CACHE_DOWNLOADING)) {
            // Update UI with current video info from cache
            $currentInfo = Cache::get(self::CACHE_CURRENT_VIDEO, []);
            $this->currentVideoTitle = $currentInfo['title'] ?? $this->currentVideoTitle;
            $this->currentVideoId = $currentInfo['id'] ?? $this->currentVideoId;
            return; // Still downloading, wait for next poll
        }

        // Check if the last background download produced a result
        $lastResult = Cache::pull(self::CACHE_RESULT);
        if ($lastResult) {
            if ($lastResult['success']) {
                $this->migratedCount++;
                $this->migrationLog[] = [
                    'id' => $lastResult['video_id'],
                    'title' => $lastResult['title'],
                    'bunny_id' => $lastResult['bunny_id'],
                    'status' => 'completed',
                    'error' => null,
                ];
            } else {
                $this->migrationLog[] = [
                    'id' => $lastResult['video_id'],
                    'title' => $lastResult['title'],
                    'bunny_id' => $lastResult['bunny_id'],
                    'status' => 'failed',
                    'error' => $lastResult['error'],
                ];
            }

            if (count($this->migrationLog) > 50) {
                $this->migrationLog = array_slice($this->migrationLog, -50);
            }

            $this->refreshStats();
        }

        // Find the next video to download
        $video = Video::where('is_embedded', true)
            ->where('source_site', 'bunnystream')
            ->whereNotNull('source_video_id')
            ->whereNotIn('status', ['failed'])
            ->first();

        if (!$video) {
            $this->isMigrating = false;
            $this->currentVideoTitle = '';
            $this->refreshStats();

            Notification::make()
                ->title('Migration complete!')
                ->body("Downloaded {$this->migratedCount} videos successfully.")
                ->success()
                ->send();
            return;
        }

        // Mark as downloading and store current video info
        Cache::put(self::CACHE_DOWNLOADING, true, 900); // 15 min TTL safety
        Cache::put(self::CACHE_CURRENT_VIDEO, [
            'id' => $video->id,
            'title' => $video->title,
        ], 900);

        $this->currentVideoTitle = $video->title;
        $this->currentVideoId = $video->id;

        // Dispatch background download via artisan command
        $videoId = $video->id;
        $disk = $this->targetDisk;
        Process::timeout(900)->start(
            "php " . base_path('artisan') . " bunny:download-single {$videoId} --disk={$disk}"
        );
    }

    /**
     * Stop the migration after the current download finishes.
     */
    public function stopMigration(): void
    {
        $this->shouldStop = true;
        $this->isMigrating = false;
        $this->currentVideoTitle = '';

        Notification::make()
            ->title('Migration stopped')
            ->body("Downloaded {$this->migratedCount} videos before stopping.")
            ->warning()
            ->send();

        $this->refreshStats();
    }

    /**
     * Retry all videos that previously failed to download.
     */
    public function retryFailed(): void
    {
        $count = Video::where('status', 'failed')
            ->where('is_embedded', true)
            ->update(['status' => 'processed']);

        if ($count === 0) {
            Notification::make()->title('No failed downloads to retry')->info()->send();
            return;
        }

        $this->refreshStats();

        Notification::make()
            ->title("Reset {$count} failed videos")
            ->body('They will be picked up when you start migration again.')
            ->success()
            ->send();
    }

    /**
     * Download a single video by ID (dispatched as background process).
     */
    public function downloadSingle(int $videoId): void
    {
        $video = Video::find($videoId);

        if (!$video || !$video->is_embedded) {
            Notification::make()->title('Video not found or not embedded')->danger()->send();
            return;
        }

        if (Cache::has(self::CACHE_DOWNLOADING)) {
            Notification::make()
                ->title('A download is already in progress')
                ->body('Please wait for the current download to finish.')
                ->warning()
                ->send();
            return;
        }

        $this->currentVideoTitle = $video->title;
        $this->currentVideoId = $video->id;
        $this->isMigrating = true;
        $this->shouldStop = false;
        $this->totalToMigrate = max($this->totalToMigrate, 1);

        Cache::put(self::CACHE_DOWNLOADING, true, 900);
        Cache::put(self::CACHE_CURRENT_VIDEO, [
            'id' => $video->id,
            'title' => $video->title,
        ], 900);

        $disk = $this->targetDisk;
        Process::timeout(900)->start(
            "php " . base_path('artisan') . " bunny:download-single {$videoId} --disk={$disk}"
        );

        Notification::make()
            ->title("Downloading: {$video->title}")
            ->body('Download started in the background. The page will update when complete.')
            ->info()
            ->send();
    }

    /**
     * Get a sample of embedded videos for the preview table.
     */
    public function getEmbeddedVideosProperty(): array
    {
        return Video::where('is_embedded', true)
            ->orderByDesc('created_at')
            ->limit(20)
            ->get()
            ->map(fn ($v) => [
                'id' => $v->id,
                'title' => $v->title,
                'source_video_id' => $v->source_video_id,
                'source_site' => $v->source_site,
                'status' => $v->status,
                'views_count' => $v->views_count,
            ])
            ->toArray();
    }

    /**
     * Get recently completed downloads.
     */
    public function getRecentDownloadsProperty(): array
    {
        return Video::where('is_embedded', false)
            ->whereNotNull('source_video_id')
            ->whereNotNull('video_path')
            ->orderByDesc('updated_at')
            ->limit(10)
            ->get()
            ->map(fn ($v) => [
                'id' => $v->id,
                'title' => $v->title,
                'source_video_id' => $v->source_video_id,
                'video_path' => $v->video_path,
                'status' => $v->status,
                'updated_at' => $v->updated_at?->toISOString(),
            ])
            ->toArray();
    }

    /**
     * Get available storage disks for the dropdown.
     * Note: Downloads always go to local first for FFmpeg processing.
     * Cloud offloading happens automatically via ProcessVideoJob if enabled in Storage settings.
     */
    public function getAvailableDisksProperty(): array
    {
        return ['public' => 'Local (ProcessVideoJob handles cloud offload if enabled)'];
    }
}

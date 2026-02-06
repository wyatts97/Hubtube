<?php

namespace App\Filament\Pages;

use App\Models\Video;
use App\Services\BunnyStreamService;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

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
                ->body('Set BUNNY_STREAM_API_KEY and BUNNY_STREAM_LIBRARY_ID in your .env file.')
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
     * Download the next pending embedded video. Called by Livewire polling.
     */
    public function downloadNext(): void
    {
        if ($this->shouldStop || !$this->isMigrating) {
            $this->isMigrating = false;
            $this->currentVideoTitle = '';
            $this->refreshStats();
            return;
        }

        // Get the next embedded video to download
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

        $this->currentVideoTitle = $video->title;
        $this->currentVideoId = $video->id;

        // Run the download synchronously
        $service = new BunnyStreamService();
        $result = $service->downloadVideo($video, $this->targetDisk);

        if ($result['success']) {
            $this->migratedCount++;
            $this->migrationLog[] = [
                'id' => $video->id,
                'title' => $video->title,
                'bunny_id' => $video->source_video_id,
                'status' => 'completed',
                'error' => null,
            ];
        } else {
            $this->migrationLog[] = [
                'id' => $video->id,
                'title' => $video->title,
                'bunny_id' => $video->source_video_id,
                'status' => 'failed',
                'error' => $result['error'],
            ];
        }

        // Keep only last 50 log entries
        if (count($this->migrationLog) > 50) {
            $this->migrationLog = array_slice($this->migrationLog, -50);
        }

        $this->refreshStats();
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
     * Download a single video by ID directly (synchronous).
     */
    public function downloadSingle(int $videoId): void
    {
        $video = Video::find($videoId);

        if (!$video || !$video->is_embedded) {
            Notification::make()->title('Video not found or not embedded')->danger()->send();
            return;
        }

        $this->currentVideoTitle = $video->title;
        $this->currentVideoId = $video->id;

        $service = new BunnyStreamService();
        $result = $service->downloadVideo($video, $this->targetDisk);

        $this->currentVideoTitle = '';
        $this->currentVideoId = 0;
        $this->refreshStats();

        if ($result['success']) {
            Notification::make()
                ->title("Downloaded: {$video->title}")
                ->body("Saved to: {$result['video_path']}")
                ->success()
                ->send();
        } else {
            Notification::make()
                ->title("Failed: {$video->title}")
                ->body($result['error'])
                ->danger()
                ->send();
        }
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
     */
    public function getAvailableDisksProperty(): array
    {
        $disks = ['public' => 'Local (public disk)'];

        if (config('filesystems.disks.wasabi.key')) {
            $disks['wasabi'] = 'Wasabi S3';
        }

        if (config('filesystems.disks.b2.key')) {
            $disks['b2'] = 'Backblaze B2';
        }

        return $disks;
    }
}

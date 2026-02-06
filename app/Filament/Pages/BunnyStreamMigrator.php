<?php

namespace App\Filament\Pages;

use App\Jobs\DownloadBunnyVideoJob;
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
    public int $pendingCount = 0;

    // Migration controls
    public string $targetDisk = 'public';
    public int $concurrency = 3;
    public bool $isMigrating = false;
    public int $queuedCount = 0;

    // Log
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
            ->where('status', '!=', 'download_failed')
            ->count();

        $this->nativeCount = Video::where('is_embedded', false)
            ->whereNotNull('video_path')
            ->count();

        $this->downloadFailedCount = Video::where('status', 'download_failed')->count();

        // Count videos currently queued (is_embedded but status = 'downloading')
        $this->pendingCount = Video::where('is_embedded', true)
            ->where('status', 'downloading')
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
     * Queue download jobs for all embedded videos that haven't been downloaded yet.
     */
    public function startMigration(): void
    {
        $service = new BunnyStreamService();

        if (!$service->isConfigured()) {
            Notification::make()
                ->title('Bunny Stream API not configured')
                ->danger()
                ->send();
            return;
        }

        // Get all embedded videos with a bunny source_video_id that haven't failed or already downloading
        $videos = Video::where('is_embedded', true)
            ->where('source_site', 'bunnystream')
            ->whereNotNull('source_video_id')
            ->whereNotIn('status', ['downloading', 'download_failed'])
            ->get();

        if ($videos->isEmpty()) {
            Notification::make()
                ->title('No videos to migrate')
                ->body('All embedded Bunny Stream videos have already been queued or downloaded.')
                ->warning()
                ->send();
            return;
        }

        $this->isMigrating = true;
        $this->queuedCount = 0;
        $this->migrationLog = [];

        foreach ($videos as $video) {
            // Mark as downloading so we don't double-queue
            $video->update(['status' => 'downloading']);

            DownloadBunnyVideoJob::dispatch($video, $this->targetDisk)
                ->onQueue('downloads');

            $this->queuedCount++;
            $this->migrationLog[] = [
                'id' => $video->id,
                'title' => $video->title,
                'bunny_id' => $video->source_video_id,
                'status' => 'queued',
            ];
        }

        $this->refreshStats();

        Notification::make()
            ->title("Queued {$this->queuedCount} videos for download")
            ->body("Videos will be downloaded in the background via the 'downloads' queue.")
            ->success()
            ->send();
    }

    /**
     * Retry all videos that previously failed to download.
     */
    public function retryFailed(): void
    {
        $failed = Video::where('status', 'download_failed')
            ->where('is_embedded', true)
            ->get();

        if ($failed->isEmpty()) {
            Notification::make()
                ->title('No failed downloads to retry')
                ->info()
                ->send();
            return;
        }

        $count = 0;
        foreach ($failed as $video) {
            $video->update(['status' => 'downloading']);
            DownloadBunnyVideoJob::dispatch($video, $this->targetDisk)
                ->onQueue('downloads');
            $count++;
        }

        $this->refreshStats();

        Notification::make()
            ->title("Retrying {$count} failed downloads")
            ->success()
            ->send();
    }

    /**
     * Download a single video by ID (for testing).
     */
    public function downloadSingle(int $videoId): void
    {
        $video = Video::find($videoId);

        if (!$video || !$video->is_embedded) {
            Notification::make()
                ->title('Video not found or not embedded')
                ->danger()
                ->send();
            return;
        }

        $video->update(['status' => 'downloading']);
        DownloadBunnyVideoJob::dispatch($video, $this->targetDisk)
            ->onQueue('downloads');

        $this->refreshStats();

        Notification::make()
            ->title("Queued download for: {$video->title}")
            ->success()
            ->send();
    }

    /**
     * Get a sample of embedded videos for the preview table.
     */
    public function getEmbeddedVideosProperty(): array
    {
        return Video::where('is_embedded', true)
            ->select(['id', 'title', 'source_video_id', 'source_site', 'status', 'views_count', 'created_at'])
            ->orderByDesc('created_at')
            ->limit(20)
            ->get()
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
            ->select(['id', 'title', 'source_video_id', 'video_path', 'status', 'updated_at'])
            ->orderByDesc('updated_at')
            ->limit(10)
            ->get()
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

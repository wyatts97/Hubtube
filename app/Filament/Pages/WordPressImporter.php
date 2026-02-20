<?php

namespace App\Filament\Pages;

use App\Models\User;
use App\Models\Video;
use App\Services\AdminLogger;
use App\Services\BunnyStreamService;
use App\Services\WordPressImportService;
use Filament\Pages\Page;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Livewire\WithFileUploads;

use App\Filament\Concerns\HasCustomizableNavigation;

class WordPressImporter extends Page
{
    use HasCustomizableNavigation;
    use WithFileUploads;

    protected static ?string $navigationIcon = 'heroicon-o-arrow-down-tray';
    protected static ?string $navigationLabel = 'WP Import';
    protected static ?string $navigationGroup = 'Tools';
    protected static ?int $navigationSort = 99;
    protected static string $view = 'filament.pages.wordpress-importer';

    // -- Upload & Parse --
    public $sqlFile = null;
    public ?string $storedFilePath = null;
    public bool $isParsing = false;
    public bool $isParsed = false;
    public array $parseStats = [];
    public array $previewVideos = [];

    // -- Import Settings --
    public ?int $importUserId = null;
    public ?string $wpAuthor = null;
    public int $batchSize = 50;
    public int $delayMs = 100;
    public string $downloadMode = 'light'; // 'light' = no FFmpeg, 'full' = with FFmpeg

    // -- Import State --
    public bool $isImporting = false;
    public bool $importComplete = false;
    public int $totalVideos = 0;
    public int $processedVideos = 0;
    public int $importedCount = 0;
    public int $skippedCount = 0;
    public array $importErrors = [];

    // -- Download Pipeline State --
    public bool $isDownloading = false;
    public bool $downloadComplete = false;
    public int $maxConcurrent = 2;
    public array $activeSlots = [];    // videoId => cache_key
    public array $sessionLog = [];     // [{title, status, error?, time}]
    public int $downloadedCount = 0;
    public int $downloadFailedCount = 0;

    // -- Pipeline Stats (refreshed by polling) --
    public int $statPendingDownload = 0;
    public int $statDownloading = 0;
    public int $statDownloadFailed = 0;
    public int $statPending = 0;
    public int $statProcessing = 0;
    public int $statProcessed = 0;

    // -- Bunny Connection --
    public bool $bunnyConnected = false;
    public ?string $bunnyError = null;

    public function getTitle(): string
    {
        return 'WordPress Import + Download';
    }

    public function getSubheading(): ?string
    {
        return 'Import WP video posts and download from Bunny Stream in one seamless flow.';
    }

    public function mount(): void
    {
        $this->testBunnyConnection();
        $this->refreshStats();
    }

    public function getUsersProperty(): array
    {
        return User::select('id', 'username', 'first_name', 'last_name')
            ->orderBy('username')
            ->get()
            ->toArray();
    }

    public function getWpAuthorsProperty(): array
    {
        return $this->parseStats['wp_authors'] ?? [];
    }

    // ---------------------------------------------
    // Bunny Connection
    // ---------------------------------------------

    public function testBunnyConnection(): void
    {
        try {
            $service = new BunnyStreamService();
            $result = $service->testConnection();
            $this->bunnyConnected = $result['success'] ?? false;
            $this->bunnyError = $result['error'] ?? null;
        } catch (\Throwable $e) {
            $this->bunnyConnected = false;
            $this->bunnyError = $e->getMessage();
        }
    }

    // ---------------------------------------------
    // Step 1: Upload & Parse SQL
    // ---------------------------------------------

    public function updatedSqlFile(): void
    {
        $this->resetParseState();
    }

    public function parseSql(): void
    {
        if (!$this->sqlFile) {
            Notification::make()->title('No file uploaded')->warning()->send();
            return;
        }

        $this->isParsing = true;

        try {
            $service = new WordPressImportService();

            $this->storedFilePath = $this->sqlFile->store('wp-imports', 'local');
            $filePath = Storage::disk('local')->path($this->storedFilePath);

            $this->parseStats = $service->parseSqlFile($filePath);

            // Default to first author if only one
            $authors = $this->parseStats['wp_authors'] ?? [];
            if (count($authors) === 1) {
                $this->wpAuthor = array_key_first($authors);
            }

            // Get video posts for preview (use author filter if set)
            if ($this->wpAuthor) {
                $service->setAuthorFilter($this->wpAuthor);
            }
            $videoPosts = $service->getVideoPosts();
            $this->totalVideos = count($videoPosts);
            $this->previewVideos = array_slice($videoPosts, 0, 10);

            $this->isParsed = true;
            $this->isParsing = false;

            Notification::make()
                ->title('SQL Parsed Successfully')
                ->body("Found {$this->totalVideos} video posts ready to import.")
                ->success()
                ->send();

        } catch (\Throwable $e) {
            $this->isParsing = false;
            Notification::make()->title('Parse Error')->body($e->getMessage())->danger()->send();
        }
    }

    public function updatedWpAuthor(): void
    {
        if (!$this->storedFilePath || !Storage::disk('local')->exists($this->storedFilePath)) {
            return;
        }

        // Re-count videos for selected author
        $service = new WordPressImportService();
        $filePath = Storage::disk('local')->path($this->storedFilePath);
        $service->parseSqlFile($filePath);

        if ($this->wpAuthor) {
            $service->setAuthorFilter($this->wpAuthor);
        }

        $videoPosts = $service->getVideoPosts();
        $this->totalVideos = count($videoPosts);
        $this->previewVideos = array_slice($videoPosts, 0, 10);
    }

    // ---------------------------------------------
    // Step 2: Import Records into DB
    // ---------------------------------------------

    public function runImport(): void
    {
        if (!$this->storedFilePath || !Storage::disk('local')->exists($this->storedFilePath)) {
            Notification::make()->title('SQL file not found. Please re-upload.')->danger()->send();
            return;
        }

        if (!$this->importUserId) {
            Notification::make()->title('Please select a user to assign imported videos to.')->warning()->send();
            return;
        }

        if (!$this->bunnyConnected) {
            Notification::make()->title('Bunny Stream not connected. Configure API keys in Integration Settings.')->danger()->send();
            return;
        }

        $this->isImporting = true;
        $this->importComplete = false;
        $this->processedVideos = 0;
        $this->importedCount = 0;
        $this->skippedCount = 0;
        $this->importErrors = [];

        try {
            $service = new WordPressImportService();
            $service->setImportUserId($this->importUserId);

            if ($this->wpAuthor) {
                $service->setAuthorFilter($this->wpAuthor);
            }

            $filePath = Storage::disk('local')->path($this->storedFilePath);
            $service->parseSqlFile($filePath);
            $allVideos = $service->getVideoPosts();

            $batches = array_chunk($allVideos, $this->batchSize);

            foreach ($batches as $batch) {
                $result = $service->importBatch($batch);

                $this->importedCount += $result['imported'];
                $this->skippedCount += $result['skipped'];
                $this->processedVideos += count($batch);

                foreach ($result['errors'] as $error) {
                    $this->importErrors[] = $error;
                }

                if ($this->delayMs > 0) {
                    usleep($this->delayMs * 1000);
                }
            }

            $this->isImporting = false;
            $this->importComplete = true;

            Storage::disk('local')->delete($this->storedFilePath);

            AdminLogger::log('WordPress video import completed', 'admin', [
                'imported' => $this->importedCount,
                'skipped' => $this->skippedCount,
                'errors' => count($this->importErrors),
            ]);

            $this->refreshStats();

            Notification::make()
                ->title('Import Complete')
                ->body("Imported: {$this->importedCount}, Skipped: {$this->skippedCount}, Errors: " . count($this->importErrors))
                ->success()
                ->send();

        } catch (\Throwable $e) {
            $this->isImporting = false;
            Notification::make()->title('Import Failed')->body($e->getMessage())->danger()->send();
        }
    }

    // ---------------------------------------------
    // Step 3: Download Pipeline
    // ---------------------------------------------

    public function startDownloads(): void
    {
        if (!$this->bunnyConnected) {
            Notification::make()->title('Bunny Stream not connected.')->danger()->send();
            return;
        }

        $pending = Video::where('status', 'pending_download')
            ->where('source_site', 'bunnystream')
            ->whereNotNull('source_video_id')
            ->count();

        if ($pending === 0) {
            Notification::make()->title('No videos pending download.')->warning()->send();
            return;
        }

        $this->isDownloading = true;
        $this->downloadComplete = false;
        $this->sessionLog = [];
        $this->downloadedCount = 0;
        $this->downloadFailedCount = 0;

        Notification::make()
            ->title('Download Pipeline Started')
            ->body("{$pending} videos queued for download.")
            ->success()
            ->send();

        // Immediately fill slots
        $this->fillDownloadSlots();
    }

    public function stopDownloads(): void
    {
        $this->isDownloading = false;

        Notification::make()->title('Download pipeline stopped.')->warning()->send();
    }

    /**
     * Called by wire:poll every 3 seconds while downloading.
     * Checks active slots for completion and fills new ones.
     */
    public function pollProgress(): void
    {
        if (!$this->isDownloading && empty($this->activeSlots)) {
            $this->refreshStats();
            return;
        }

        // Check each active slot
        foreach ($this->activeSlots as $videoId => $cacheKey) {
            $result = Cache::get($cacheKey);
            if (!$result || empty($result['done'])) {
                continue; // Still running
            }

            // Slot finished — process result
            unset($this->activeSlots[$videoId]);
            Cache::forget($cacheKey);

            $logEntry = [
                'title' => $result['title'] ?? 'Unknown',
                'video_id' => $result['video_id'] ?? $videoId,
                'bunny_id' => $result['bunny_id'] ?? '',
                'time' => now()->format('H:i:s'),
            ];

            if ($result['success']) {
                $logEntry['status'] = 'success';
                $logEntry['video_path'] = $result['video_path'] ?? '';
                $this->downloadedCount++;
            } else {
                $logEntry['status'] = 'failed';
                $logEntry['error'] = $result['error'] ?? 'Unknown error';
                $this->downloadFailedCount++;

                // Error toast for failures
                Notification::make()
                    ->title('Download Failed')
                    ->body(($result['title'] ?? 'Video') . ': ' . ($result['error'] ?? 'Unknown error'))
                    ->danger()
                    ->duration(8000)
                    ->send();
            }

            // Prepend to session log (newest first), cap at 50
            array_unshift($this->sessionLog, $logEntry);
            $this->sessionLog = array_slice($this->sessionLog, 0, 50);
        }

        // Fill empty slots if still downloading
        if ($this->isDownloading) {
            $this->fillDownloadSlots();
        }

        // Check if pipeline is done
        if ($this->isDownloading && empty($this->activeSlots)) {
            $remaining = Video::where('status', 'pending_download')
                ->where('source_site', 'bunnystream')
                ->whereNotNull('source_video_id')
                ->count();

            if ($remaining === 0) {
                $this->isDownloading = false;
                $this->downloadComplete = true;

                Notification::make()
                    ->title('All Downloads Complete')
                    ->body("Downloaded: {$this->downloadedCount}, Failed: {$this->downloadFailedCount}")
                    ->success()
                    ->send();
            }
        }

        $this->refreshStats();
    }

    private function fillDownloadSlots(): void
    {
        $slotsAvailable = $this->maxConcurrent - count($this->activeSlots);
        if ($slotsAvailable <= 0) return;

        $videos = Video::where('status', 'pending_download')
            ->where('source_site', 'bunnystream')
            ->whereNotNull('source_video_id')
            ->whereNotIn('id', array_keys($this->activeSlots))
            ->orderBy('id')
            ->limit($slotsAvailable)
            ->get();

        $lightFlag = $this->downloadMode === 'light' ? ' --light' : '';

        foreach ($videos as $video) {
            $cacheKey = 'bunny_dl_' . $video->id;
            Cache::forget($cacheKey); // Clear any stale result

            $this->activeSlots[$video->id] = $cacheKey;

            // Dispatch background artisan command
            $artisan = base_path('artisan');
            $cmd = "php {$artisan} bunny:download-single {$video->id}{$lightFlag}";

            Log::info('WPImporter: dispatching download', [
                'video_id' => $video->id,
                'cmd' => $cmd,
            ]);

            Process::timeout(1800)->start($cmd);
        }
    }

    /**
     * Retry all failed downloads.
     */
    public function retryFailed(): void
    {
        $count = Video::where('status', 'download_failed')
            ->where('source_site', 'bunnystream')
            ->update(['status' => 'pending_download', 'failure_reason' => null]);

        if ($count > 0) {
            Notification::make()
                ->title("{$count} videos reset to pending download.")
                ->success()
                ->send();

            $this->refreshStats();

            // Auto-start if not already downloading
            if (!$this->isDownloading) {
                $this->startDownloads();
            }
        } else {
            Notification::make()->title('No failed downloads to retry.')->warning()->send();
        }
    }

    /**
     * Download a single specific video.
     */
    public function downloadSingle(int $videoId): void
    {
        $video = Video::find($videoId);
        if (!$video || !$video->source_video_id) {
            Notification::make()->title('Video not found or has no Bunny ID.')->danger()->send();
            return;
        }

        // Reset status if needed
        if (!in_array($video->status, ['pending_download', 'download_failed', 'failed'])) {
            Notification::make()->title("Video status is '{$video->status}', cannot re-download.")->warning()->send();
            return;
        }

        $cacheKey = 'bunny_dl_' . $video->id;
        Cache::forget($cacheKey);
        $this->activeSlots[$video->id] = $cacheKey;

        $lightFlag = $this->downloadMode === 'light' ? ' --light' : '';
        $artisan = base_path('artisan');
        Process::timeout(1800)->start("php {$artisan} bunny:download-single {$video->id}{$lightFlag}");

        if (!$this->isDownloading) {
            $this->isDownloading = true;
        }

        Notification::make()->title("Downloading: {$video->title}")->success()->send();
    }

    // ---------------------------------------------
    // Stats & Helpers
    // ---------------------------------------------

    public function refreshStats(): void
    {
        $base = Video::where('source_site', 'bunnystream');

        $this->statPendingDownload = (clone $base)->where('status', 'pending_download')->count();
        $this->statDownloading = (clone $base)->where('status', 'downloading')->count();
        $this->statDownloadFailed = (clone $base)->where('status', 'download_failed')->count();
        $this->statPending = (clone $base)->where('status', 'pending')->count();
        $this->statProcessing = (clone $base)->where('status', 'processing')->count();
        $this->statProcessed = (clone $base)->where('status', 'processed')->count();
    }

    public function getImportProgressPercent(): int
    {
        if ($this->totalVideos === 0) return 0;
        return (int) round(($this->processedVideos / $this->totalVideos) * 100);
    }

    public function getDownloadProgressPercent(): int
    {
        $total = $this->downloadedCount + $this->downloadFailedCount + $this->statPendingDownload + count($this->activeSlots);
        if ($total === 0) return 0;
        return (int) round((($this->downloadedCount + $this->downloadFailedCount) / $total) * 100);
    }

    public function purgeImported(): void
    {
        $service = new WordPressImportService();
        $deleted = $service->purgeImported();

        AdminLogger::log('Purged WordPress imported videos', 'admin', [
            'deleted_count' => $deleted,
        ]);

        Notification::make()
            ->title('Purge Complete')
            ->body("Deleted {$deleted} previously imported videos.")
            ->success()
            ->send();

        $this->resetParseState();
        $this->refreshStats();
    }

    public function resetImport(): void
    {
        if ($this->storedFilePath && Storage::disk('local')->exists($this->storedFilePath)) {
            Storage::disk('local')->delete($this->storedFilePath);
        }

        $this->sqlFile = null;
        $this->storedFilePath = null;
        $this->resetParseState();
    }

    private function resetParseState(): void
    {
        $this->isParsed = false;
        $this->parseStats = [];
        $this->isImporting = false;
        $this->importComplete = false;
        $this->totalVideos = 0;
        $this->processedVideos = 0;
        $this->importedCount = 0;
        $this->skippedCount = 0;
        $this->importErrors = [];
        $this->previewVideos = [];
    }

    /**
     * Get videos in the download queue for display.
     */
    public function getDownloadQueueProperty(): array
    {
        return Video::where('source_site', 'bunnystream')
            ->whereIn('status', ['pending_download', 'downloading', 'download_failed'])
            ->orderByRaw("FIELD(status, 'downloading', 'download_failed', 'pending_download')")
            ->limit(25)
            ->get(['id', 'title', 'source_video_id', 'status', 'failure_reason'])
            ->toArray();
    }
}

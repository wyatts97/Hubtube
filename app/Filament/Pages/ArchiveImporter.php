<?php

namespace App\Filament\Pages;

use App\Models\User;
use App\Services\ArchiveImportService;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class ArchiveImporter extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-folder-open';
    protected static ?string $navigationLabel = 'Archive Import';
    protected static ?string $navigationGroup = 'Tools';
    protected static ?int $navigationSort = 98;
    protected static string $view = 'filament.pages.archive-importer';

    // Config inputs
    public string $archivePath = '';
    public string $sqlFilePath = '';
    public ?int $importUserId = null;

    // Scan state
    public bool $isScanning = false;
    public bool $isScanned = false;
    public array $archiveStats = [];
    public array $parseStats = [];
    public array $fileValidation = [];
    public array $previewVideos = [];
    public int $totalImportable = 0;

    // Import state
    public bool $isImporting = false;
    public bool $importComplete = false;
    public int $processedCount = 0;
    public int $importedCount = 0;
    public int $skippedCount = 0;
    public int $errorCount = 0;
    public array $importErrors = [];
    public array $importLog = [];
    public int $alreadyImported = 0;

    // Internal: stored parsed video data for import (not displayed in full)
    public array $allVideos = [];

    // Livewire polling for import progress
    public bool $shouldPoll = false;

    public function getTitle(): string
    {
        return 'Archive Import';
    }

    public function getSubheading(): ?string
    {
        return 'Import videos from the WedgieTube WordPress archive directory + SQL dump into HubTube as native videos.';
    }

    public function getUsersProperty(): array
    {
        return User::select('id', 'username', 'first_name', 'last_name')->orderBy('username')->get()->toArray();
    }

    /**
     * Scan the archive directory and parse the SQL file.
     */
    public function scanArchive(): void
    {
        if (empty($this->archivePath) || empty($this->sqlFilePath)) {
            Notification::make()->title('Please provide both the archive directory path and SQL file path.')->warning()->send();
            return;
        }

        if (!is_dir($this->archivePath)) {
            Notification::make()->title('Archive directory not found')->body($this->archivePath)->danger()->send();
            return;
        }

        if (!file_exists($this->sqlFilePath)) {
            Notification::make()->title('SQL file not found')->body($this->sqlFilePath)->danger()->send();
            return;
        }

        $this->isScanning = true;
        $this->resetState();

        try {
            $service = new ArchiveImportService();
            $service->setArchivePath($this->archivePath);

            // Scan archive directory
            $this->archiveStats = $service->scanArchive();

            // Parse SQL file
            $this->parseStats = $service->parseSqlFile($this->sqlFilePath);

            // Get video posts with file path info
            $allVideos = $service->getVideoPosts();

            // Validate which files exist in the archive
            $validation = $service->validateFiles($allVideos);
            $this->fileValidation = [
                'matched' => $validation['matched'],
                'missing_video' => $validation['missing_video'],
                'missing_thumb' => $validation['missing_thumb'],
            ];

            // Store all validated videos for import
            $this->allVideos = $validation['videos'];
            $this->totalImportable = $validation['matched'];

            // Preview first 15 (with file status)
            $this->previewVideos = array_slice($validation['videos'], 0, 15);

            // Check how many are already imported
            $this->alreadyImported = $service->getImportedCount();

            $this->isScanned = true;
            $this->isScanning = false;

            Notification::make()
                ->title('Scan Complete')
                ->body("Found {$validation['matched']} videos with matching files out of " . count($allVideos) . " total video posts.")
                ->success()
                ->send();

        } catch (\Throwable $e) {
            $this->isScanning = false;
            Notification::make()->title('Scan Error')->body($e->getMessage())->danger()->send();
        }
    }

    /**
     * Start the import process. Uses Livewire polling to process one video at a time.
     */
    public function startImport(): void
    {
        if (!$this->importUserId) {
            Notification::make()->title('Please select a user to assign imported videos to.')->warning()->send();
            return;
        }

        $this->isImporting = true;
        $this->importComplete = false;
        $this->processedCount = 0;
        $this->importedCount = 0;
        $this->skippedCount = 0;
        $this->errorCount = 0;
        $this->importErrors = [];
        $this->importLog = [];
        $this->shouldPoll = true;
    }

    /**
     * Process the next video in the queue. Called by Livewire polling.
     */
    public function importNext(): void
    {
        if (!$this->isImporting || !$this->shouldPoll) {
            return;
        }

        // Find next unprocessed video that has a local file
        $importableVideos = array_filter($this->allVideos, fn($v) => !empty($v['video_found']));
        $importableVideos = array_values($importableVideos);

        if ($this->processedCount >= count($importableVideos)) {
            $this->isImporting = false;
            $this->importComplete = true;
            $this->shouldPoll = false;

            Notification::make()
                ->title('Import Complete')
                ->body("Imported: {$this->importedCount}, Skipped: {$this->skippedCount}, Errors: {$this->errorCount}")
                ->success()
                ->send();
            return;
        }

        $video = $importableVideos[$this->processedCount];

        $service = new ArchiveImportService();
        $service->setImportUserId($this->importUserId);
        $service->setArchivePath($this->archivePath);

        $result = $service->importVideo($video);

        $this->processedCount++;

        $logEntry = [
            'title' => \Illuminate\Support\Str::limit($video['title'], 50),
            'status' => $result['status'],
            'message' => $result['message'],
        ];

        // Keep only last 20 log entries to avoid bloating Livewire state
        array_unshift($this->importLog, $logEntry);
        $this->importLog = array_slice($this->importLog, 0, 20);

        switch ($result['status']) {
            case 'imported':
                $this->importedCount++;
                break;
            case 'skipped':
                $this->skippedCount++;
                break;
            case 'error':
                $this->errorCount++;
                $this->importErrors[] = [
                    'wp_id' => $video['wp_id'],
                    'title' => $video['title'],
                    'error' => $result['message'],
                ];
                break;
        }
    }

    /**
     * Stop the import process.
     */
    public function stopImport(): void
    {
        $this->shouldPoll = false;
        $this->isImporting = false;

        Notification::make()
            ->title('Import Stopped')
            ->body("Processed {$this->processedCount} videos so far. You can resume by clicking Start Import again.")
            ->warning()
            ->send();
    }

    /**
     * Purge all previously archive-imported videos.
     */
    public function purgeImported(): void
    {
        $service = new ArchiveImportService();
        $deleted = $service->purgeImported();

        Notification::make()
            ->title('Purge Complete')
            ->body("Deleted {$deleted} previously imported archive videos and their files.")
            ->success()
            ->send();

        $this->alreadyImported = 0;
        $this->resetState();
    }

    /**
     * Reset everything for a fresh scan.
     */
    public function resetAll(): void
    {
        $this->resetState();
        $this->archivePath = '';
        $this->sqlFilePath = '';
        $this->importUserId = null;
    }

    private function resetState(): void
    {
        $this->isScanned = false;
        $this->archiveStats = [];
        $this->parseStats = [];
        $this->fileValidation = [];
        $this->previewVideos = [];
        $this->totalImportable = 0;
        $this->allVideos = [];
        $this->isImporting = false;
        $this->importComplete = false;
        $this->processedCount = 0;
        $this->importedCount = 0;
        $this->skippedCount = 0;
        $this->errorCount = 0;
        $this->importErrors = [];
        $this->importLog = [];
        $this->shouldPoll = false;
    }

    public function getProgressPercent(): int
    {
        if ($this->totalImportable === 0) return 0;
        return (int) round(($this->processedCount / $this->totalImportable) * 100);
    }

    /**
     * Apply faststart (moov atom at beginning) to all archive-imported videos
     * so they become seekable in the browser video player.
     */
    public function fixSeekability(): void
    {
        $ffmpegPath = \App\Models\Setting::get('ffmpeg_path', 'ffmpeg');
        $videos = \App\Models\Video::where('source_site', 'wedgietube_archive')
            ->whereNotNull('video_path')
            ->get();

        $fixed = 0;
        $failed = 0;

        foreach ($videos as $video) {
            $disk = $video->storage_disk ?? 'public';
            $filePath = \Illuminate\Support\Facades\Storage::disk($disk)->path($video->video_path);

            if (!file_exists($filePath)) {
                $failed++;
                continue;
            }

            $tmpPath = $filePath . '.faststart.mp4';
            $cmd = escapeshellarg($ffmpegPath)
                . ' -i ' . escapeshellarg($filePath)
                . ' -c copy -movflags +faststart'
                . ' -y ' . escapeshellarg($tmpPath)
                . ' 2>&1';

            shell_exec($cmd);

            if (file_exists($tmpPath) && filesize($tmpPath) > 0) {
                unlink($filePath);
                rename($tmpPath, $filePath);
                $fixed++;
            } else {
                if (file_exists($tmpPath)) {
                    unlink($tmpPath);
                }
                $failed++;
            }
        }

        Notification::make()
            ->title('Seekability Fix Complete')
            ->body("Fixed {$fixed} videos" . ($failed > 0 ? ", {$failed} failed" : '') . '. Videos should now be seekable in the player.')
            ->success()
            ->send();
    }
}

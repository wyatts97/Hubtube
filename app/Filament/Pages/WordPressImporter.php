<?php

namespace App\Filament\Pages;

use App\Models\User;
use App\Services\WordPressImportService;
use Filament\Pages\Page;
use Filament\Notifications\Notification;
use Livewire\WithFileUploads;
use Illuminate\Support\Facades\Storage;

class WordPressImporter extends Page
{
    use WithFileUploads;

    protected static ?string $navigationIcon = 'heroicon-o-arrow-down-tray';
    protected static ?string $navigationLabel = 'WP Import';
    protected static ?string $navigationGroup = 'Tools';
    protected static ?int $navigationSort = 99;
    protected static string $view = 'filament.pages.wordpress-importer';

    public $sqlFile = null;
    public int $batchSize = 50;
    public int $delayMs = 100;
    public ?int $importUserId = null;

    // Parse state
    public bool $isParsing = false;
    public bool $isParsed = false;
    public array $parseStats = [];

    // Import state
    public bool $isImporting = false;
    public bool $importComplete = false;
    public int $totalVideos = 0;
    public int $processedVideos = 0;
    public int $importedCount = 0;
    public int $skippedCount = 0;
    public array $importErrors = [];
    public array $previewVideos = [];

    // Stored file path for re-parsing during import (avoids huge Livewire state)
    public ?string $storedFilePath = null;

    public function getTitle(): string
    {
        return 'WordPress Import';
    }

    public function getSubheading(): ?string
    {
        return 'Import vidmov_video posts from WedgieTube WordPress SQL dump into HubTube videos.';
    }

    /**
     * Get list of users for the import user dropdown.
     */
    public function getUsersProperty(): array
    {
        return User::select('id', 'username', 'first_name', 'last_name')->orderBy('username')->get()->toArray();
    }

    public function updatedSqlFile(): void
    {
        $this->resetState();
    }

    private function resetState(): void
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
     * Parse the uploaded SQL file and show stats + preview.
     */
    public function parseSql(): void
    {
        if (!$this->sqlFile) {
            Notification::make()->title('No file uploaded')->warning()->send();
            return;
        }

        $this->isParsing = true;

        try {
            $service = new WordPressImportService();

            // Store the uploaded file to a persistent location for re-use during import
            $this->storedFilePath = $this->sqlFile->store('wp-imports', 'local');
            $filePath = Storage::disk('local')->path($this->storedFilePath);

            $this->parseStats = $service->parseSqlFile($filePath);
            $videoPosts = $service->getVideoPosts();
            $this->totalVideos = count($videoPosts);

            // Only store first 10 for preview (keep Livewire state small)
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
            Notification::make()
                ->title('Parse Error')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    /**
     * Run the import in batches. Re-parses the SQL file to avoid
     * storing all video data in Livewire component state.
     */
    public function runImport(): void
    {
        if (!$this->storedFilePath || !Storage::disk('local')->exists($this->storedFilePath)) {
            Notification::make()->title('SQL file not found. Please re-upload.')->danger()->send();
            return;
        }

        $this->isImporting = true;
        $this->importComplete = false;
        $this->processedVideos = 0;
        $this->importedCount = 0;
        $this->skippedCount = 0;
        $this->importErrors = [];

        try {
            if (!$this->importUserId) {
                Notification::make()->title('Please select a user to assign imported videos to.')->warning()->send();
                $this->isImporting = false;
                return;
            }

            $service = new WordPressImportService();
            $service->setImportUserId($this->importUserId);
            $filePath = Storage::disk('local')->path($this->storedFilePath);

            // Re-parse the file to get all video posts
            $service->parseSqlFile($filePath);
            $allVideos = $service->getVideoPosts();

            // Process in batches
            $batches = array_chunk($allVideos, $this->batchSize);

            foreach ($batches as $batch) {
                $result = $service->importBatch($batch);

                $this->importedCount += $result['imported'];
                $this->skippedCount += $result['skipped'];
                $this->processedVideos += count($batch);

                foreach ($result['errors'] as $error) {
                    $this->importErrors[] = $error;
                }

                // Delay between batches
                if ($this->delayMs > 0) {
                    usleep($this->delayMs * 1000);
                }
            }

            $this->isImporting = false;
            $this->importComplete = true;

            // Clean up stored file
            Storage::disk('local')->delete($this->storedFilePath);

            Notification::make()
                ->title('Import Complete')
                ->body("Imported: {$this->importedCount}, Skipped: {$this->skippedCount}, Errors: " . count($this->importErrors))
                ->success()
                ->send();

        } catch (\Throwable $e) {
            $this->isImporting = false;
            Notification::make()
                ->title('Import Failed')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    /**
     * Purge all previously imported WP videos so user can re-import cleanly.
     */
    public function purgeImported(): void
    {
        $service = new WordPressImportService();
        $deleted = $service->purgeImported();

        Notification::make()
            ->title('Purge Complete')
            ->body("Deleted {$deleted} previously imported videos. You can now re-import.")
            ->success()
            ->send();

        $this->resetState();
    }

    /**
     * Reset everything for a fresh import.
     */
    public function resetImport(): void
    {
        // Clean up stored file
        if ($this->storedFilePath && Storage::disk('local')->exists($this->storedFilePath)) {
            Storage::disk('local')->delete($this->storedFilePath);
        }

        $this->sqlFile = null;
        $this->storedFilePath = null;
        $this->resetState();
    }

    public function getProgressPercent(): int
    {
        if ($this->totalVideos === 0) return 0;
        return (int) round(($this->processedVideos / $this->totalVideos) * 100);
    }
}

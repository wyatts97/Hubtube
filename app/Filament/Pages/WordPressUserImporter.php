<?php

namespace App\Filament\Pages;

use App\Models\User;
use App\Services\AdminLogger;
use App\Services\WordPressUserImportService;
use Filament\Pages\Page;
use Filament\Notifications\Notification;
use Livewire\WithFileUploads;
use Illuminate\Support\Facades\Storage;

class WordPressUserImporter extends Page
{
    use WithFileUploads;

    protected static ?string $navigationIcon = 'heroicon-o-users';
    protected static ?string $navigationLabel = 'WP User Import';
    protected static ?string $navigationGroup = 'Tools';
    protected static ?int $navigationSort = 100;
    protected static string $view = 'filament.pages.wordpress-user-importer';

    public $sqlFile = null;
    public int $batchSize = 50;

    // Parse state
    public bool $isParsing = false;
    public bool $isParsed = false;
    public array $parseStats = [];

    // Import state
    public bool $isImporting = false;
    public bool $importComplete = false;
    public int $totalUsers = 0;
    public int $processedUsers = 0;
    public int $importedCount = 0;
    public int $skippedCount = 0;
    public array $importErrors = [];
    public array $previewUsers = [];

    // Stored file path
    public ?string $storedFilePath = null;

    public function getTitle(): string
    {
        return 'WordPress User Import';
    }

    public function getSubheading(): ?string
    {
        return 'Import users from the WedgieTube WordPress users table SQL dump.';
    }

    /**
     * Get count of previously imported WP users.
     */
    public function getPreviouslyImportedCountProperty(): int
    {
        return User::where('settings->wp_imported', true)->count();
    }

    /**
     * Get total HubTube users.
     */
    public function getTotalHubtubeUsersProperty(): int
    {
        return User::count();
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
        $this->totalUsers = 0;
        $this->processedUsers = 0;
        $this->importedCount = 0;
        $this->skippedCount = 0;
        $this->importErrors = [];
        $this->previewUsers = [];
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
            $service = new WordPressUserImportService();

            // Store the uploaded file to a persistent location
            $this->storedFilePath = $this->sqlFile->store('wp-imports', 'local');
            $filePath = Storage::disk('local')->path($this->storedFilePath);

            $this->parseStats = $service->parseSqlFile($filePath);
            $allUsers = $service->getUsers();
            $this->totalUsers = count($allUsers);

            // Only store first 20 for preview
            $this->previewUsers = array_slice($allUsers, 0, 20);

            $this->isParsed = true;
            $this->isParsing = false;

            Notification::make()
                ->title('SQL Parsed Successfully')
                ->body("Found {$this->totalUsers} users ready to import.")
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
     * Run the import in batches.
     */
    public function runImport(): void
    {
        if (!$this->storedFilePath || !Storage::disk('local')->exists($this->storedFilePath)) {
            Notification::make()->title('SQL file not found. Please re-upload.')->danger()->send();
            return;
        }

        $this->isImporting = true;
        $this->importComplete = false;
        $this->processedUsers = 0;
        $this->importedCount = 0;
        $this->skippedCount = 0;
        $this->importErrors = [];

        try {
            $service = new WordPressUserImportService();
            $filePath = Storage::disk('local')->path($this->storedFilePath);

            // Re-parse the file
            $service->parseSqlFile($filePath);
            $allUsers = array_values($service->getUsers());

            // Track used usernames across batches to prevent collisions
            $usedUsernames = [];

            // Process in batches
            $batches = array_chunk($allUsers, $this->batchSize);

            foreach ($batches as $batch) {
                $result = $service->importBatch($batch, $usedUsernames);

                $this->importedCount += $result['imported'];
                $this->skippedCount += $result['skipped'];
                $this->processedUsers += count($batch);

                foreach ($result['errors'] as $error) {
                    $this->importErrors[] = $error;
                }
            }

            $this->isImporting = false;
            $this->importComplete = true;

            // Clean up stored file
            Storage::disk('local')->delete($this->storedFilePath);

            AdminLogger::log('WordPress user import completed', 'admin', [
                'imported' => $this->importedCount,
                'skipped' => $this->skippedCount,
                'errors' => count($this->importErrors),
            ]);

            Notification::make()
                ->title('User Import Complete')
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
     * Purge all previously imported WP users.
     */
    public function purgeImported(): void
    {
        $service = new WordPressUserImportService();
        $deleted = $service->purgeImported();

        AdminLogger::log('Purged WordPress imported users', 'admin', [
            'deleted_count' => $deleted,
        ]);

        Notification::make()
            ->title('Purge Complete')
            ->body("Deleted {$deleted} previously imported WP users and their channels.")
            ->success()
            ->send();

        $this->resetState();
    }

    /**
     * Reset everything for a fresh import.
     */
    public function resetImport(): void
    {
        if ($this->storedFilePath && Storage::disk('local')->exists($this->storedFilePath)) {
            Storage::disk('local')->delete($this->storedFilePath);
        }

        $this->sqlFile = null;
        $this->storedFilePath = null;
        $this->resetState();
    }

    public function getProgressPercent(): int
    {
        if ($this->totalUsers === 0) return 0;
        return (int) round(($this->processedUsers / $this->totalUsers) * 100);
    }
}

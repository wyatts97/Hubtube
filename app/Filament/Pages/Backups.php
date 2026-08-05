<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use App\Services\AdminLogger;
use Filament\Actions\Action;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Computed;

class Backups extends Page implements HasForms
{
    use InteractsWithForms;
    protected static string | \BackedEnum | null $navigationIcon = 'phosphor-archive';
    protected static ?string $navigationLabel = 'Backups';
    protected static string | \UnitEnum | null $navigationGroup = 'Tools';
    protected static ?int $navigationSort = 90;

    protected string $view = 'filament.pages.backups';

    public bool $running = false;
    public string $status = '';
    public ?array $settingsData = [];

    public function mount(): void
    {
        $this->settingsForm->fill([
            'backup_enabled' => Setting::get('backup_enabled', true),
        ]);
    }

    public function settingsForm(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Auto-Backup Settings')
                    ->description('Control the scheduled nightly backup.')
                    ->schema([
                        Toggle::make('backup_enabled')
                            ->label('Enable automatic daily backups')
                            ->helperText('When enabled, a full backup runs nightly at 1:00 AM. Old backups are cleaned weekly.'),
                    ]),
            ])
            ->statePath('settingsData');
    }

    public function saveBackupSettings(): void
    {
        $data = $this->settingsForm->getState();
        Setting::set('backup_enabled', $data['backup_enabled'], 'backup', 'boolean');
        AdminLogger::settingsSaved('Backups', array_keys($data));
        Notification::make()->title('Backup settings saved')->success()->send();
    }

    protected function getForms(): array
    {
        return ['settingsForm'];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('create')
                ->label('Create Backup')
                ->icon('phosphor-plus')
                ->action(function () {
                    $this->running = true;
                    $this->status = 'Backup started...';

                    try {
                        Artisan::call('backup:run');
                        $output = Artisan::output();
                        $this->status = $output;

                        Notification::make()
                            ->title('Backup created')
                            ->body('The backup was created successfully.')
                            ->success()
                            ->send();
                    } catch (\Throwable $e) {
                        $this->status = 'Error: ' . $e->getMessage();

                        Notification::make()
                            ->title('Backup failed')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    } finally {
                        $this->running = false;
                    }
                })
                ->disabled(fn () => $this->running),

            Action::make('cleanup')
                ->label('Clean Old Backups')
                ->icon('phosphor-broom')
                ->color('warning')
                ->action(function () {
                    $this->running = true;
                    $this->status = 'Cleanup started...';

                    try {
                        Artisan::call('backup:clean');
                        $output = Artisan::output();
                        $this->status = $output;

                        Notification::make()
                            ->title('Cleanup complete')
                            ->body('Old backups have been cleaned up.')
                            ->success()
                            ->send();
                    } catch (\Throwable $e) {
                        $this->status = 'Error: ' . $e->getMessage();

                        Notification::make()
                            ->title('Cleanup failed')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    } finally {
                        $this->running = false;
                    }
                })
                ->disabled(fn () => $this->running),
        ];
    }

    #[Computed]
    public function backups(): array
    {
        $disk = Storage::disk('local');
        $backupDir = config('backup.directory_name', 'backups');

        if (!$disk->exists($backupDir)) {
            return [];
        }

        $files = $disk->files($backupDir);

        return collect($files)
            ->filter(fn ($file) => str_ends_with($file, '.zip'))
            ->map(fn ($file) => [
                'name' => basename($file),
                'size' => $this->formatBytes($disk->size($file)),
                'modified' => date('M j, Y g:i A', $disk->lastModified($file)),
                'path' => $file,
            ])
            ->sortByDesc('modified')
            ->values()
            ->toArray();
    }

    public function deleteBackup(string $path): void
    {
        try {
            Storage::disk('local')->delete($path);

            Notification::make()
                ->title('Backup deleted')
                ->success()
                ->send();
        } catch (\Throwable $e) {
            Notification::make()
                ->title('Delete failed')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }

        unset($this->backups);
    }

    public function downloadBackup(string $path): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        return Storage::disk('local')->download($path);
    }

    protected function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        $size = (float) $bytes;

        while ($size >= 1024 && $i < count($units) - 1) {
            $size /= 1024;
            $i++;
        }

        return round($size, 2) . ' ' . $units[$i];
    }
}

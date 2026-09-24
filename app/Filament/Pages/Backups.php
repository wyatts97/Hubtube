<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\RequiresPermission;
use App\Jobs\RunBackupCommandJob;
use App\Models\Setting;
use App\Services\AdminLogger;
use App\Support\Bytes;
use Filament\Actions\Action;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Computed;
use Symfony\Component\HttpFoundation\StreamedResponse;

class Backups extends Page implements HasForms
{
    use RequiresPermission;

    protected static string $requiredPermission = 'view_any_user';

    use InteractsWithForms;

    protected static string|\BackedEnum|null $navigationIcon = 'phosphor-archive';

    protected static ?string $navigationLabel = 'Backups';

    protected static string|\UnitEnum|null $navigationGroup = 'Tools';

    protected static ?int $navigationSort = 90;

    protected string $view = 'filament.pages.backups';

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
                            ->helperText('Full backup nightly at 1:00 AM. Old backups are pruned weekly.'),

                        Actions::make([
                            Action::make('saveBackupSettings')
                                ->color('success')
                                ->label('Save Settings')
                                ->icon('phosphor-floppy-disk')
                                ->action('saveBackupSettings'),
                        ])->columnSpanFull(),
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
                ->color('success')
                ->label('Create Backup')
                ->icon('phosphor-plus')
                ->action(fn () => $this->queueBackupCommand('backup:run')),

            Action::make('cleanup')
                ->label('Clean Old Backups')
                ->icon('phosphor-broom')
                ->color('warning')
                ->action(fn () => $this->queueBackupCommand('backup:clean')),
        ];
    }

    /** Backups outlast a web request, so they run on the queue and report back. */
    protected function queueBackupCommand(string $command): void
    {
        RunBackupCommandJob::dispatch($command, auth()->id());

        Notification::make()
            ->title('Started in the background')
            ->body("You'll get a notification when it finishes.")
            ->success()
            ->send();
    }

    #[Computed]
    public function backups(): array
    {
        $disk = Storage::disk('local');
        $backupDir = config('backup.backup.name', 'backups');

        if (! $disk->exists($backupDir)) {
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

    public function downloadBackup(string $path): StreamedResponse
    {
        return Storage::disk('local')->download($path);
    }

    protected function formatBytes(int $bytes): string
    {
        return Bytes::format($bytes);
    }
}

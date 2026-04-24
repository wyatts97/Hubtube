<?php

namespace App\Filament\Resources\ActivityLogResource\Pages;

use App\Filament\Resources\ActivityLogResource;
use App\Services\LogViewerService;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Livewire\Attributes\Url;

class ListApplicationLogs extends Page
{
    protected static string $resource = ActivityLogResource::class;

    protected static string $view = 'filament.resources.activity-log-resource.pages.list-application-logs';

    protected static ?string $title = 'Application Logs';

    #[Url(as: 'file')]
    public ?string $selectedFile = null;

    #[Url(as: 'level')]
    public ?string $selectedLevel = null;

    #[Url(as: 'q')]
    public ?string $searchQuery = '';

    #[Url(as: 'tail')]
    public bool $tailMode = true;

    public int $perPage = 50;

    public int $page = 1;

    /** Currently-open entry (index into filtered entries) for the detail modal. */
    public ?int $openEntryIndex = null;

    public function mount(): void
    {
        $files = $this->service()->getLogFiles();
        if ($this->selectedFile === null && !empty($files)) {
            $this->selectedFile = $files[0]['name'];
        }
    }

    protected function service(): LogViewerService
    {
        return app(LogViewerService::class);
    }

    public function updatedSelectedFile(): void
    {
        $this->page = 1;
        $this->openEntryIndex = null;
    }

    public function updatedSelectedLevel(): void { $this->page = 1; }
    public function updatedSearchQuery(): void { $this->page = 1; }
    public function updatedTailMode(): void { $this->page = 1; }

    public function getFilesProperty(): array
    {
        return $this->service()->getLogFiles();
    }

    public function getFileOptionsProperty(): array
    {
        $opts = [];
        foreach ($this->files as $f) {
            $opts[$f['name']] = $f['name'] . ' · ' . $f['size'];
        }
        return $opts;
    }

    public function getCurrentFileMetaProperty(): ?array
    {
        foreach ($this->files as $f) {
            if ($f['name'] === $this->selectedFile) {
                return $f;
            }
        }
        return null;
    }

    /**
     * Parsed entries for the current filters.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getEntriesProperty(): array
    {
        if (!$this->selectedFile) {
            return [];
        }
        return $this->service()->parseLogFile(
            $this->selectedFile,
            limit: $this->tailMode ? 2000 : 5000,
            level: $this->selectedLevel,
            search: $this->searchQuery,
            tail: $this->tailMode,
        );
    }

    public function getPaginatedEntriesProperty(): array
    {
        $entries = $this->entries;
        $offset = ($this->page - 1) * $this->perPage;
        return array_slice($entries, $offset, $this->perPage);
    }

    public function getTotalPagesProperty(): int
    {
        return max(1, (int) ceil(count($this->entries) / $this->perPage));
    }

    public function nextPage(): void
    {
        if ($this->page < $this->totalPages) $this->page++;
    }

    public function prevPage(): void
    {
        if ($this->page > 1) $this->page--;
    }

    public function openEntry(int $index): void
    {
        $this->openEntryIndex = $index;
    }

    public function closeEntry(): void
    {
        $this->openEntryIndex = null;
    }

    public function getOpenEntryProperty(): ?array
    {
        if ($this->openEntryIndex === null) {
            return null;
        }
        $absolute = ($this->page - 1) * $this->perPage + $this->openEntryIndex;
        return $this->entries[$absolute] ?? null;
    }

    public function refreshLogs(): void
    {
        \Illuminate\Support\Facades\Cache::forget('log_viewer:files');
        $this->dispatch('$refresh');
        Notification::make()->title('Logs reloaded')->success()->send();
    }

    public function clearCurrentFile(): void
    {
        if (!$this->selectedFile) return;
        $this->service()->clearLogFile($this->selectedFile);
        $this->page = 1;
        Notification::make()->title("Cleared {$this->selectedFile}")->success()->send();
    }

    public function deleteCurrentFile(): void
    {
        if (!$this->selectedFile) return;
        $current = $this->selectedFile;
        $this->service()->deleteLogFile($current);
        $this->selectedFile = null;
        $this->mount(); // re-pick newest
        Notification::make()->title("Deleted {$current}")->success()->send();
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('activityLog')
                ->label('Activity Log')
                ->icon('heroicon-o-clipboard-document-list')
                ->color('gray')
                ->url(ActivityLogResource::getUrl('index')),

            Actions\ActionGroup::make([
                Actions\Action::make('download')
                    ->label('Download File')
                    ->icon('heroicon-m-arrow-down-tray')
                    ->url(fn () => $this->selectedFile
                        ? route('admin.logs.download-file', ['filename' => $this->selectedFile])
                        : null)
                    ->openUrlInNewTab()
                    ->visible(fn () => (bool) $this->selectedFile),

                Actions\Action::make('clear')
                    ->label('Clear File Contents')
                    ->icon('heroicon-m-backspace')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalDescription('Truncate the selected log file to 0 bytes. This cannot be undone.')
                    ->action(fn () => $this->clearCurrentFile())
                    ->visible(fn () => (bool) $this->selectedFile),

                Actions\Action::make('delete')
                    ->label('Delete File')
                    ->icon('heroicon-m-trash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalDescription('Permanently delete the selected log file.')
                    ->action(fn () => $this->deleteCurrentFile())
                    ->visible(fn () => (bool) $this->selectedFile),
            ])
                ->label('File Actions')
                ->icon('heroicon-m-ellipsis-vertical')
                ->button()
                ->color('gray'),

            Actions\Action::make('refresh')
                ->label('Refresh')
                ->icon('heroicon-m-arrow-path')
                ->color('gray')
                ->action(fn () => $this->refreshLogs()),
        ];
    }

    /**
     * Hook into Filament's sub-navigation so this page and the Activity Log list
     * appear as tabs across both /admin/logs and /admin/logs/application.
     */
    public function getSubNavigation(): array
    {
        return static::subNav();
    }

    public static function subNav(): array
    {
        return [
            \Filament\Navigation\NavigationItem::make('Activity Log')
                ->icon('heroicon-o-clipboard-document-list')
                ->isActiveWhen(fn () => request()->routeIs('filament.admin.resources.logs.index'))
                ->url(ActivityLogResource::getUrl('index')),

            \Filament\Navigation\NavigationItem::make('Application Logs')
                ->icon('heroicon-o-folder-open')
                ->isActiveWhen(fn () => request()->routeIs('filament.admin.resources.logs.application'))
                ->url(ActivityLogResource::getUrl('application')),
        ];
    }
}

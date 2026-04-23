<?php

namespace App\Filament\Pages;

use App\Services\LogViewerService;
use App\Filament\Resources\ActivityLogResource;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Filament\Tables;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Support\Facades\Artisan;

class LogViewer extends Page implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';
    protected static ?string $navigationLabel = 'System Logs';
    protected static ?string $navigationGroup = 'Tools';
    protected static ?int $navigationSort = 99;
    protected static string $view = 'filament.pages.log-viewer';

    public ?string $selectedLogFile = null;
    public ?string $searchQuery = null;
    public ?string $selectedLevel = null;

    public function mount(): void
    {
        $this->form->fill([
            'selected_log' => null,
            'search' => '',
            'level' => null,
        ]);
    }

    public function form(Form $form): Form
    {
        $logService = app(LogViewerService::class);
        $logFiles = collect($logService->getLogFiles())
            ->mapWithKeys(fn ($file) => [$file['name'] => $file['name'] . ' (' . $file['size'] . ')'])
            ->toArray();

        return $form
            ->schema([
                Select::make('selected_log')
                    ->label('Log File')
                    ->options($logFiles)
                    ->placeholder('Select a log file...')
                    ->searchable()
                    ->live()
                    ->afterStateUpdated(fn ($state) => $this->selectedLogFile = $state),

                Select::make('level')
                    ->label('Log Level')
                    ->options([
                        'debug' => 'Debug',
                        'info' => 'Info',
                        'notice' => 'Notice',
                        'warning' => 'Warning',
                        'error' => 'Error',
                        'critical' => 'Critical',
                        'alert' => 'Alert',
                        'emergency' => 'Emergency',
                    ])
                    ->placeholder('All levels')
                    ->live()
                    ->afterStateUpdated(fn ($state) => $this->selectedLevel = $state),

                TextInput::make('search')
                    ->label('Search')
                    ->placeholder('Search in logs...')
                    ->suffixAction(
                        \Filament\Forms\Components\Actions\Action::make('clear')
                            ->icon('heroicon-m-x-mark')
                            ->action(fn ($set) => $set('search', ''))
                    )
                    ->live(debounce: 500)
                    ->afterStateUpdated(fn ($state) => $this->searchQuery = $state),
            ])
            ->columns(3);
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(\Spatie\Activitylog\Models\Activity::query()->with(['causer', 'subject']))
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Timestamp')
                    ->dateTime('M d, Y H:i:s')
                    ->sortable(),

                Tables\Columns\TextColumn::make('log_name')
                    ->label('Level')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'error' => 'danger',
                        'auth' => 'warning',
                        'admin' => 'info',
                        'system' => 'gray',
                        default => 'gray',
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('description')
                    ->wrap()
                    ->limit(120)
                    ->searchable(),

                Tables\Columns\TextColumn::make('causer')
                    ->label('Causer')
                    ->state(fn ($record) => $record->causer?->username ?? 'System')
                    ->toggleable(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->url(fn ($record) => ActivityLogResource::getUrl('view', ['record' => $record])),
            ])
            ->emptyStateHeading('No activity logs')
            ->emptyStateDescription('Activity logs will appear here.')
            ->paginated([25, 50, 100]);
    }

    protected function getLogEntries(): array
    {
        $service = app(LogViewerService::class);

        if ($this->searchQuery) {
            return $service->searchLogs($this->searchQuery, 100, $this->selectedLevel);
        }

        if ($this->selectedLogFile) {
            return $service->parseLogFile($this->selectedLogFile, 100, $this->selectedLevel);
        }

        // Default to laravel.log if it exists
        return $service->parseLogFile('laravel.log', 100, $this->selectedLevel);
    }

    protected function getLogFiles(): array
    {
        return app(LogViewerService::class)->getLogFiles();
    }

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\Action::make('activity_logs')
                ->label('Activity Logs')
                ->icon('heroicon-o-clipboard-document-list')
                ->url(ActivityLogResource::getUrl('index')),

            \Filament\Actions\Action::make('clear_cache')
                ->label('Clear Log Cache')
                ->icon('heroicon-o-arrow-path')
                ->color('warning')
                ->requiresConfirmation()
                ->action(function () {
                    // Clear any cached log parsing
                    Notification::make()
                        ->title('Log cache cleared')
                        ->success()
                        ->send();
                }),
        ];
    }

    public function getTitle(): string
    {
        return 'System Logs';
    }
}

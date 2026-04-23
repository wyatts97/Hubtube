<?php

namespace App\Filament\Resources\ActivityLogResource\Pages;

use App\Filament\Resources\ActivityLogResource;
use Filament\Resources\Pages\ViewRecord;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Infolist;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Blade;

class ViewActivityLog extends ViewRecord
{
    protected static string $resource = ActivityLogResource::class;

    protected static string $view = 'filament.resources.activity-log-resource.pages.view-activity-log';

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Section::make('Log Entry Details')
                    ->schema([
                        TextEntry::make('created_at')
                            ->label('Timestamp')
                            ->dateTime('M d, Y H:i:s')
                            ->icon('heroicon-m-calendar'),

                        TextEntry::make('log_name')
                            ->label('Level')
                            ->badge()
                            ->color(fn (string $state): string => match ($state) {
                                'error' => 'danger',
                                'auth' => 'warning',
                                'admin' => 'info',
                                'system' => 'gray',
                                default => 'gray',
                            }),

                        TextEntry::make('causer_display')
                            ->label('Causer')
                            ->default(fn ($record) => $this->resolveCauserLabel($record))
                            ->icon('heroicon-m-user'),

                        TextEntry::make('subject_display')
                            ->label('Subject')
                            ->default(fn ($record) => $this->resolveSubjectLabel($record))
                            ->icon('heroicon-m-document'),
                    ])
                    ->columns(4),

                Section::make('Description')
                    ->schema([
                        TextEntry::make('description')
                            ->hiddenLabel()
                            ->columnSpanFull()
                            ->extraAttributes(['class' => 'text-base leading-relaxed']),
                    ]),

                Section::make('Context')
                    ->collapsible()
                    ->schema([
                        TextEntry::make('properties')
                            ->hiddenLabel()
                            ->columnSpanFull()
                            ->formatStateUsing(fn ($state) => $this->formatContextJson($state))
                            ->extraAttributes(['class' => 'font-mono text-sm'])
                            ->html(),
                    ]),

                Section::make('Stack Trace')
                    ->collapsible()
                    ->collapsed(fn ($record) => empty($this->extractStackTrace($record)) || $this->extractStackTrace($record) === 'N/A')
                    ->schema([
                        TextEntry::make('stack_trace')
                            ->hiddenLabel()
                            ->columnSpanFull()
                            ->default(fn ($record) => $this->extractStackTrace($record))
                            ->formatStateUsing(fn ($state) => $this->formatStackTrace($state))
                            ->extraAttributes(['class' => 'font-mono text-xs'])
                            ->html(),
                    ]),
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('copy')
                ->label('Copy Full Log')
                ->icon('heroicon-m-clipboard')
                ->color('gray')
                ->action(function () {
                    $this->dispatch('log-copied');
                    Notification::make()
                        ->title('Log entry copied to clipboard')
                        ->success()
                        ->send();
                })
                ->extraAttributes([
                    'x-data' => '',
                    'x-on:click' => '
                        const fullLog = document.getElementById("full-log-content").innerText;
                        navigator.clipboard.writeText(fullLog).then(() => {
                            $dispatch("log-copied");
                        });
                    ',
                ]),

            Action::make('export')
                ->label('Export JSON')
                ->icon('heroicon-m-arrow-down-tray')
                ->color('gray')
                ->url(fn () => route('admin.activity-log.export', ['id' => $this->record->id])),

            Action::make('delete')
                ->label('Delete')
                ->icon('heroicon-m-trash')
                ->color('danger')
                ->requiresConfirmation()
                ->action(fn () => $this->deleteRecord()),
        ];
    }

    private function resolveCauserLabel($record): string
    {
        if (!$record->causer) {
            return 'System';
        }

        if (isset($record->causer->username) && $record->causer->username) {
            return $record->causer->username;
        }

        return class_basename($record->causer_type) . ' #' . $record->causer_id;
    }

    private function resolveSubjectLabel($record): string
    {
        if (!$record->subject_type || !$record->subject_id) {
            return '—';
        }

        return class_basename($record->subject_type) . ' #' . $record->subject_id;
    }

    private function formatContextJson($properties): string
    {
        if (empty($properties)) {
            return '<pre class="bg-gray-900 text-gray-300 p-4 rounded-lg overflow-x-auto">{}</pre>';
        }

        if (is_object($properties) && method_exists($properties, 'toArray')) {
            $properties = $properties->toArray();
        }

        $json = json_encode($properties, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        // Syntax highlighting for JSON
        $highlighted = $this->highlightJson($json);

        return '<pre class="bg-gray-900 text-gray-300 p-4 rounded-lg overflow-x-auto">' . $highlighted . '</pre>';
    }

    private function highlightJson(string $json): string
    {
        $json = htmlspecialchars($json, ENT_QUOTES, 'UTF-8');

        // Key highlighting (cyan)
        $json = preg_replace('/&quot;([\w_]+)&quot;:/', '<span class="text-cyan-400">"$1"</span>:', $json);

        // String values (green)
        $json = preg_replace('/: &quot;([^&]*)&quot;/', ': <span class="text-green-400">"$1"</span>', $json);

        // Numbers (yellow)
        $json = preg_replace('/: (\d+)(,?)$/m', ': <span class="text-yellow-400">$1</span>$2', $json);

        // Booleans/null (purple)
        $json = preg_replace('/: (true|false|null)/', ': <span class="text-purple-400">$1</span>', $json);

        return $json;
    }

    private function extractStackTrace($record): string
    {
        $properties = $record->properties;

        if (is_object($properties) && method_exists($properties, 'toArray')) {
            $properties = $properties->toArray();
        }

        if (!is_array($properties)) {
            return 'N/A';
        }

        return (string) ($properties['trace'] ?? $properties['stack_trace'] ?? $properties['stack'] ?? 'N/A');
    }

    private function formatStackTrace(string $trace): string
    {
        if ($trace === 'N/A' || empty($trace)) {
            return '<p class="text-gray-500 italic">No stack trace available</p>';
        }

        // Format PHP stack trace with line numbers
        $lines = explode("\n", $trace);
        $formatted = [];
        $lineNumber = 1;

        foreach ($lines as $line) {
            if (empty(trim($line))) {
                continue;
            }

            // Highlight file paths and line numbers
            $line = htmlspecialchars($line, ENT_QUOTES, 'UTF-8');
            $line = preg_replace(
                '/(\/[^\s]+\.php)(\(\d+\))?/',
                '<span class="text-yellow-400">$1</span><span class="text-blue-400">$2</span>',
                $line
            );
            $line = preg_replace(
                '/#\d+/',
                '<span class="text-purple-400">$0</span>',
                $line
            );

            $formatted[] = '<div class="flex gap-3 py-1 hover:bg-gray-800/50 rounded">' .
                '<span class="text-gray-600 w-8 text-right select-none">' . $lineNumber . '</span>' .
                '<span class="flex-1">' . $line . '</span>' .
                '</div>';
            $lineNumber++;
        }

        return '<div class="bg-gray-900 text-gray-300 p-4 rounded-lg overflow-x-auto">' .
            implode("", $formatted) .
            '</div>';
    }

    public function getTitle(): string
    {
        return 'Log Entry #' . $this->record->id;
    }
}

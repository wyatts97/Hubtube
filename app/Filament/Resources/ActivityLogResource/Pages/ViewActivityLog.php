<?php

namespace App\Filament\Resources\ActivityLogResource\Pages;

use App\Filament\Resources\ActivityLogResource;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Spatie\Activitylog\Models\Activity;

class ViewActivityLog extends ViewRecord
{
    protected static string $resource = ActivityLogResource::class;

    protected static string $view = 'filament.resources.activity-log-resource.pages.view-activity-log';

    public function getTitle(): string
    {
        return 'Log Entry #' . $this->record->id;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('copyAll')
                ->label('Copy Full Log')
                ->icon('heroicon-m-clipboard-document')
                ->color('gray')
                ->action(function () {
                    $this->dispatch('copy-to-clipboard', text: $this->buildFullLogText());
                    Notification::make()
                        ->title('Log entry copied to clipboard')
                        ->success()
                        ->send();
                }),

            ActionGroup::make([
                Action::make('exportJson')
                    ->label('Export as JSON')
                    ->icon('heroicon-m-code-bracket')
                    ->url(fn () => route('admin.logs.export-entry', ['id' => $this->record->id, 'format' => 'json']))
                    ->openUrlInNewTab(),
                Action::make('exportCsv')
                    ->label('Export as CSV')
                    ->icon('heroicon-m-table-cells')
                    ->url(fn () => route('admin.logs.export-entry', ['id' => $this->record->id, 'format' => 'csv']))
                    ->openUrlInNewTab(),
                Action::make('exportTxt')
                    ->label('Export as TXT')
                    ->icon('heroicon-m-document-text')
                    ->url(fn () => route('admin.logs.export-entry', ['id' => $this->record->id, 'format' => 'txt']))
                    ->openUrlInNewTab(),
            ])
                ->label('Export')
                ->icon('heroicon-m-arrow-down-tray')
                ->button()
                ->color('gray'),

            Action::make('delete')
                ->label('Delete')
                ->icon('heroicon-m-trash')
                ->color('danger')
                ->requiresConfirmation()
                ->action(function () {
                    $id = $this->record->id;
                    $this->record->delete();
                    Notification::make()->title("Log entry #{$id} deleted")->success()->send();
                    $this->redirect(ActivityLogResource::getUrl('index'));
                }),
        ];
    }

    /**
     * Plain-text full log used by the "Copy Full Log" button.
     */
    public function buildFullLogText(): string
    {
        /** @var Activity $record */
        $record = $this->record;
        $ctx = $this->contextJson();
        $trace = $this->stackTraceText();

        return implode("\n", [
            'Log Entry #' . $record->id,
            str_repeat('=', 40),
            'Timestamp:   ' . ($record->created_at?->format('Y-m-d H:i:s') ?? 'N/A'),
            'Level:       ' . ($record->log_name ?? 'n/a'),
            'Causer:      ' . $this->causerLabel(),
            'Subject:     ' . $this->subjectLabel(),
            '',
            'Description:',
            (string) $record->description,
            '',
            'Context:',
            $ctx,
            '',
            'Stack Trace:',
            $trace,
        ]);
    }

    public function causerLabel(): string
    {
        $record = $this->record;
        if (!$record->causer) {
            return 'System';
        }
        if (isset($record->causer->username) && $record->causer->username) {
            return $record->causer->username;
        }
        return class_basename($record->causer_type) . ' #' . $record->causer_id;
    }

    public function subjectLabel(): string
    {
        $record = $this->record;
        if (!$record->subject_type || !$record->subject_id) {
            return '—';
        }
        return class_basename($record->subject_type) . ' #' . $record->subject_id;
    }

    /**
     * Pretty-printed JSON of properties (minus 'trace' which is shown separately).
     */
    public function contextJson(): string
    {
        $props = $this->normalizeProperties($this->record->properties);
        if (empty($props)) {
            return '{}';
        }
        // Strip trace-like keys, rendered separately
        unset($props['trace'], $props['stack_trace'], $props['stack']);

        $encoded = json_encode($props, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        return $encoded === false ? var_export($props, true) : $encoded;
    }

    /**
     * Get the raw stack trace (handles string, array, or nested-array shapes).
     */
    public function stackTraceText(): string
    {
        $props = $this->normalizeProperties($this->record->properties);
        $trace = $props['trace'] ?? $props['stack_trace'] ?? $props['stack'] ?? null;

        if ($trace === null || $trace === '') {
            return '';
        }

        if (is_string($trace)) {
            return $trace;
        }

        if (is_array($trace)) {
            // Could be array of strings (each frame) or array of frame dicts
            $lines = [];
            foreach ($trace as $i => $frame) {
                if (is_string($frame)) {
                    $lines[] = $frame;
                } elseif (is_array($frame)) {
                    $file = $frame['file'] ?? '?';
                    $line = $frame['line'] ?? '?';
                    $fn = $frame['function'] ?? '?';
                    $class = $frame['class'] ?? null;
                    $callable = $class ? "{$class}::{$fn}" : $fn;
                    $lines[] = "#{$i} {$file}({$line}): {$callable}()";
                } else {
                    $lines[] = "#{$i} " . var_export($frame, true);
                }
            }
            return implode("\n", $lines);
        }

        return (string) $trace;
    }

    public function hasStackTrace(): bool
    {
        return $this->stackTraceText() !== '';
    }

    private function normalizeProperties(mixed $props): array
    {
        if (empty($props)) {
            return [];
        }
        if (is_object($props) && method_exists($props, 'toArray')) {
            return $props->toArray();
        }
        if (is_array($props)) {
            return $props;
        }
        if (is_string($props)) {
            $decoded = json_decode($props, true);
            return is_array($decoded) ? $decoded : [];
        }
        return [];
    }
}

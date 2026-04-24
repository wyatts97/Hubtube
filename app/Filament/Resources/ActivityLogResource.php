<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ActivityLogResource\Pages;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Spatie\Activitylog\Models\Activity;

class ActivityLogResource extends Resource
{
    protected static ?string $model = Activity::class;

    protected static ?string $slug = 'logs';

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationLabel = 'Logs';

    protected static ?string $modelLabel = 'Log Entry';

    protected static ?string $pluralModelLabel = 'Logs';

    protected static ?string $navigationGroup = 'Tools';

    protected static ?int $navigationSort = 98;

    public static function getNavigationBadge(): ?string
    {
        try {
            $count = Activity::query()
                ->where('log_name', 'error')
                ->where('created_at', '>=', now()->subDay())
                ->count();
            return $count > 0 ? (string) $count : null;
        } catch (\Throwable) {
            return null;
        }
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public static function form(Form $form): Form
    {
        // Unused: detail lives on ViewActivityLog page. Keep a minimal schema so
        // any default ViewRecord form calls don't crash.
        return $form->schema([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['causer', 'subject']))
            ->defaultSort('created_at', 'desc')
            ->poll('15s')
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Timestamp')
                    ->dateTime('M d, Y H:i:s')
                    ->sortable(),

                Tables\Columns\TextColumn::make('log_name')
                    ->label('Level')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => ucfirst((string) $state))
                    ->color(fn (?string $state): string => match ($state) {
                        'error' => 'danger',
                        'auth' => 'warning',
                        'admin' => 'info',
                        'system' => 'gray',
                        default => 'gray',
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('description')
                    ->wrap()
                    ->limit(140)
                    ->tooltip(fn (Activity $record): ?string => strlen((string) $record->description) > 140 ? $record->description : null)
                    ->searchable(),

                Tables\Columns\TextColumn::make('causer_label')
                    ->label('Causer')
                    ->state(fn (Activity $record): string => static::resolveCauserLabel($record))
                    ->icon('heroicon-m-user')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('subject_label')
                    ->label('Subject')
                    ->state(fn (Activity $record): string => static::resolveSubjectLabel($record))
                    ->icon('heroicon-m-document')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\IconColumn::make('has_trace')
                    ->label('Trace')
                    ->state(fn (Activity $record): bool => static::recordHasStackTrace($record))
                    ->boolean()
                    ->trueIcon('heroicon-m-bug-ant')
                    ->falseIcon('heroicon-m-minus')
                    ->trueColor('warning')
                    ->falseColor('gray')
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('log_name')
                    ->label('Level')
                    ->multiple()
                    ->options([
                        'admin' => 'Admin',
                        'auth' => 'Auth',
                        'error' => 'Error',
                        'system' => 'System',
                    ]),

                Filter::make('created_at')
                    ->form([
                        Forms\Components\DatePicker::make('created_from')->label('From'),
                        Forms\Components\DatePicker::make('created_until')->label('Until'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['created_from'] ?? null, fn (Builder $q, $d) => $q->whereDate('created_at', '>=', $d))
                            ->when($data['created_until'] ?? null, fn (Builder $q, $d) => $q->whereDate('created_at', '<=', $d));
                    })
                    ->indicateUsing(function (array $data): array {
                        $i = [];
                        if ($data['created_from'] ?? null) $i[] = 'From ' . $data['created_from'];
                        if ($data['created_until'] ?? null) $i[] = 'Until ' . $data['created_until'];
                        return $i;
                    }),

                Filter::make('has_stack_trace')
                    ->label('Has Stack Trace')
                    ->query(function (Builder $query): Builder {
                        // Portable across MySQL/MariaDB/PostgreSQL/SQLite: match the JSON text
                        return $query->where(function (Builder $q) {
                            $q->where('properties', 'like', '%"trace"%')
                                ->orWhere('properties', 'like', '%"stack_trace"%')
                                ->orWhere('properties', 'like', '%"stack"%');
                        });
                    })
                    ->toggle(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->url(fn (Activity $record): string => static::getUrl('view', ['record' => $record])),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('exportJson')
                        ->label('Export as JSON')
                        ->icon('heroicon-m-code-bracket')
                        ->action(fn (Collection $records) => static::streamExport($records, 'json'))
                        ->deselectRecordsAfterCompletion(),
                    Tables\Actions\BulkAction::make('exportCsv')
                        ->label('Export as CSV')
                        ->icon('heroicon-m-table-cells')
                        ->action(fn (Collection $records) => static::streamExport($records, 'csv'))
                        ->deselectRecordsAfterCompletion(),
                    Tables\Actions\BulkAction::make('exportTxt')
                        ->label('Export as TXT')
                        ->icon('heroicon-m-document-text')
                        ->action(fn (Collection $records) => static::streamExport($records, 'txt'))
                        ->deselectRecordsAfterCompletion(),
                    Tables\Actions\DeleteBulkAction::make(),
                ])
                    ->label('Bulk actions')
                    ->icon('heroicon-m-ellipsis-vertical'),
            ])
            ->striped()
            ->emptyStateIcon('heroicon-o-document-text')
            ->emptyStateHeading('No log entries')
            ->emptyStateDescription('Activity will appear here as users and the system perform actions.');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListActivityLogs::route('/'),
            'application' => Pages\ListApplicationLogs::route('/application'),
            'view' => Pages\ViewActivityLog::route('/{record}'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }

    // ── Public helpers (also used by Pages) ──────────────────────────────

    public static function resolveCauserLabel(Activity $record): string
    {
        if (!$record->causer) {
            return 'System';
        }
        if (isset($record->causer->username) && $record->causer->username) {
            return $record->causer->username;
        }
        return class_basename($record->causer_type) . ' #' . $record->causer_id;
    }

    public static function resolveSubjectLabel(Activity $record): string
    {
        if (!$record->subject_type || !$record->subject_id) {
            return '—';
        }
        return class_basename($record->subject_type) . ' #' . $record->subject_id;
    }

    public static function recordHasStackTrace(Activity $record): bool
    {
        $props = $record->properties;
        if (is_object($props) && method_exists($props, 'toArray')) {
            $props = $props->toArray();
        }
        if (!is_array($props)) {
            return false;
        }
        foreach (['trace', 'stack_trace', 'stack'] as $k) {
            if (!empty($props[$k])) {
                return true;
            }
        }
        return false;
    }

    /**
     * Stream an export of the given activity records in the requested format.
     *
     * @param  Collection<int, Activity>  $records
     * @param  'json'|'csv'|'txt'         $format
     */
    public static function streamExport(Collection $records, string $format): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $stamp = now()->format('Y-m-d-His');
        $filename = "activity-logs-{$stamp}.{$format}";

        $rows = $records->map(function (Activity $r) {
            $props = $r->properties;
            if (is_object($props) && method_exists($props, 'toArray')) {
                $props = $props->toArray();
            }
            return [
                'id' => $r->id,
                'timestamp' => $r->created_at?->format('Y-m-d H:i:s'),
                'level' => $r->log_name,
                'causer' => static::resolveCauserLabel($r),
                'subject' => static::resolveSubjectLabel($r),
                'description' => (string) $r->description,
                'context' => $props,
            ];
        })->all();

        return match ($format) {
            'json' => response()->streamDownload(function () use ($rows) {
                echo json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            }, $filename, ['Content-Type' => 'application/json']),

            'csv' => response()->streamDownload(function () use ($rows) {
                $out = fopen('php://output', 'w');
                fputcsv($out, ['id', 'timestamp', 'level', 'causer', 'subject', 'description', 'context']);
                foreach ($rows as $row) {
                    fputcsv($out, [
                        $row['id'], $row['timestamp'], $row['level'],
                        $row['causer'], $row['subject'], $row['description'],
                        is_array($row['context']) ? json_encode($row['context'], JSON_UNESCAPED_SLASHES) : (string) $row['context'],
                    ]);
                }
                fclose($out);
            }, $filename, ['Content-Type' => 'text/csv']),

            'txt' => response()->streamDownload(function () use ($rows) {
                foreach ($rows as $row) {
                    echo "Log Entry #{$row['id']}\n";
                    echo str_repeat('=', 40) . "\n";
                    echo "Timestamp:   {$row['timestamp']}\n";
                    echo "Level:       {$row['level']}\n";
                    echo "Causer:      {$row['causer']}\n";
                    echo "Subject:     {$row['subject']}\n";
                    echo "\nDescription:\n{$row['description']}\n";
                    echo "\nContext:\n" . (is_array($row['context']) ? json_encode($row['context'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) : $row['context']) . "\n";
                    echo "\n\n";
                }
            }, $filename, ['Content-Type' => 'text/plain']),

            default => abort(400, 'Unsupported export format'),
        };
    }
}

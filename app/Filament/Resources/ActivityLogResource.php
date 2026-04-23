<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ActivityLogResource\Pages;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Spatie\Activitylog\Models\Activity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;


class ActivityLogResource extends Resource
{
    protected static ?string $model = Activity::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?string $navigationLabel = 'Logs';

    protected static ?string $navigationGroup = 'Tools';

    protected static ?int $navigationSort = 98;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\DateTimePicker::make('created_at')
                    ->label('Timestamp')
                    ->disabled(),

                Forms\Components\TextInput::make('log_name')
                    ->label('Level')
                    ->disabled(),

                Forms\Components\TextInput::make('causer_label')
                    ->label('Causer')
                    ->disabled(),

                Forms\Components\TextInput::make('subject_label')
                    ->label('Subject')
                    ->disabled(),

                Forms\Components\Textarea::make('description')
                    ->rows(3)
                    ->disabled()
                    ->columnSpanFull(),

                Forms\Components\Textarea::make('context_json')
                    ->label('Context JSON')
                    ->rows(10)
                    ->disabled()
                    ->columnSpanFull()
                    ->extraAttributes(['class' => 'font-mono']),

                Forms\Components\Textarea::make('stack_trace')
                    ->rows(10)
                    ->disabled()
                    ->columnSpanFull()
                    ->extraAttributes(['class' => 'font-mono']),
            ])
            ->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['causer', 'subject']))
            ->defaultSort('created_at', 'desc')
            ->poll('10s')
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
                    ->state(fn (Activity $record): string => static::resolveCauserLabel($record))
                    ->toggleable(),

                Tables\Columns\TextColumn::make('subject')
                    ->label('Subject')
                    ->state(fn (Activity $record): string => static::resolveSubjectLabel($record))
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
                        Forms\Components\DatePicker::make('created_from')
                            ->label('From'),
                        Forms\Components\DatePicker::make('created_until')
                            ->label('Until'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['created_from'],
                                fn (Builder $query, $date): Builder => $query->whereDate('created_at', '>=', $date),
                            )
                            ->when(
                                $data['created_until'],
                                fn (Builder $query, $date): Builder => $query->whereDate('created_at', '<=', $date),
                            );
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];
                        if ($data['created_from'] ?? null) {
                            $indicators[] = 'From ' . $data['created_from'];
                        }
                        if ($data['created_until'] ?? null) {
                            $indicators[] = 'Until ' . $data['created_until'];
                        }
                        return $indicators;
                    }),

                Filter::make('has_stack_trace')
                    ->label('Has Stack Trace')
                    ->query(function (Builder $query): Builder {
                        return $query->whereRaw("JSON_CONTAINS_PATH(properties, 'one', '$.trace') OR JSON_CONTAINS_PATH(properties, 'one', '$.stack_trace') OR JSON_CONTAINS_PATH(properties, 'one', '$.stack')");
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
                    Tables\Actions\BulkAction::make('export')
                        ->label('Export Selected')
                        ->icon('heroicon-o-arrow-down-tray')
                        ->action(function (\Illuminate\Database\Eloquent\Collection $records) {
                            $data = $records->map(fn (Activity $record) => [
                                'id' => $record->id,
                                'timestamp' => $record->created_at?->format('Y-m-d H:i:s'),
                                'level' => $record->log_name,
                                'causer' => static::resolveCauserLabel($record),
                                'subject' => static::resolveSubjectLabel($record),
                                'description' => $record->description,
                                'context' => $record->properties,
                            ])->toArray();

                            $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
                            $filename = 'activity-logs-' . now()->format('Y-m-d-His') . '.json';

                            // Download via browser using Livewire dispatch
                            return response()->streamDownload(function () use ($json) {
                                echo $json;
                            }, $filename, ['Content-Type' => 'application/json']);
                        }),
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->striped();
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListActivityLogs::route('/'),
            'view' => Pages\ViewActivityLog::route('/{record}'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }

    private static function resolveCauserLabel(Activity $record): string
    {
        if (!$record->causer) {
            return 'System';
        }

        if (isset($record->causer->username) && $record->causer->username) {
            return $record->causer->username;
        }

        return class_basename($record->causer_type) . ' #' . $record->causer_id;
    }

    private static function resolveSubjectLabel(Activity $record): string
    {
        if (!$record->subject_type || !$record->subject_id) {
            return '—';
        }

        return class_basename($record->subject_type) . ' #' . $record->subject_id;
    }

    private static function formatContext(Activity $record): string
    {
        $properties = $record->properties;

        if (empty($properties)) {
            return '{}';
        }

        if (is_object($properties) && method_exists($properties, 'toArray')) {
            $properties = $properties->toArray();
        }

        return json_encode($properties, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '{}';
    }

    private static function extractStackTrace(Activity $record): string
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
}

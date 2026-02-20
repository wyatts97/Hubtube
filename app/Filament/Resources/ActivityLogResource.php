<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ActivityLogResource\Pages;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Spatie\Activitylog\Models\Activity;

use App\Filament\Concerns\HasCustomizableNavigation;

class ActivityLogResource extends Resource
{
    use HasCustomizableNavigation;
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
                Tables\Filters\SelectFilter::make('log_name')
                    ->label('Level')
                    ->options([
                        'admin' => 'Admin',
                        'auth' => 'Auth',
                        'error' => 'Error',
                        'system' => 'System',
                    ]),
            ])
            ->actions([
                Tables\Actions\Action::make('view')
                    ->icon('heroicon-o-eye')
                    ->color('gray')
                    ->label('View')
                    ->modalHeading('Log Entry')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close')
                    ->fillForm(fn (Activity $record): array => [
                        'created_at' => optional($record->created_at)->format('Y-m-d H:i:s'),
                        'log_name' => $record->log_name,
                        'causer_label' => static::resolveCauserLabel($record),
                        'subject_label' => static::resolveSubjectLabel($record),
                        'description' => $record->description,
                        'context_json' => static::formatContext($record),
                        'stack_trace' => static::extractStackTrace($record),
                    ])
                    ->form([
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
                    ]),

                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListActivityLogs::route('/'),
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

<?php

namespace App\Filament\Pages;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use App\Models\FailedJob;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

use App\Filament\Concerns\HasCustomizableNavigation;

class FailedJobs extends Page implements HasTable
{
    use HasCustomizableNavigation;
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-exclamation-triangle';
    protected static ?string $navigationLabel = 'Failed Jobs';
    protected static ?string $navigationGroup = 'System';
    protected static ?int $navigationSort = 99;
    protected static string $view = 'filament.pages.failed-jobs';

    public static function shouldRegisterNavigation(): bool
    {
        if (static::isHiddenByNavCustomizer()) return false;
        try {
            return DB::table('failed_jobs')->count() > 0;
        } catch (\Exception $e) {
            return false;
        }
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(FailedJob::query())
            ->defaultSort('failed_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('ID')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('payload')
                    ->label('Job')
                    ->formatStateUsing(function ($state) {
                        $payload = is_string($state) ? json_decode($state, true) : $state;
                        return class_basename($payload['displayName'] ?? 'Unknown');
                    })
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->where('payload', 'like', "%{$search}%");
                    })
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('queue')
                    ->badge()
                    ->color('gray')
                    ->sortable(),

                Tables\Columns\TextColumn::make('exception')
                    ->label('Error')
                    ->formatStateUsing(fn ($state) => Str::limit(Str::before($state, "\n"), 100))
                    ->wrap()
                    ->color('danger')
                    ->tooltip(fn ($state) => Str::limit($state, 300)),

                Tables\Columns\TextColumn::make('failed_at')
                    ->label('Failed')
                    ->dateTime('M d, Y H:i:s')
                    ->sortable()
                    ->since(),
            ])
            ->actions([
                Tables\Actions\Action::make('view')
                    ->icon('heroicon-o-eye')
                    ->color('gray')
                    ->modalHeading('Failed Job Details')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close')
                    ->form(fn ($record) => [
                        TextInput::make('job_class')
                            ->label('Job Class')
                            ->default(function () use ($record) {
                                $payload = json_decode($record->payload, true);
                                return $payload['displayName'] ?? 'Unknown';
                            })
                            ->disabled(),
                        TextInput::make('queue')
                            ->label('Queue')
                            ->default($record->queue)
                            ->disabled(),
                        TextInput::make('failed_at')
                            ->label('Failed At')
                            ->default($record->failed_at)
                            ->disabled(),
                        Textarea::make('exception')
                            ->label('Exception')
                            ->default($record->exception)
                            ->rows(15)
                            ->disabled()
                            ->columnSpanFull()
                            ->extraAttributes(['class' => 'font-mono text-xs']),
                    ]),

                Tables\Actions\Action::make('retry')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading('Retry Job')
                    ->modalDescription('This will re-queue the failed job for processing.')
                    ->action(function ($record) {
                        Artisan::call('queue:retry', ['id' => [$record->uuid]]);
                        Notification::make()->title('Job queued for retry')->success()->send();
                    }),

                Tables\Actions\Action::make('delete')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(function ($record) {
                        FailedJob::where('id', $record->id)->delete();
                        Notification::make()->title('Failed job deleted')->success()->send();
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkAction::make('retrySelected')
                    ->label('Retry Selected')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->deselectRecordsAfterCompletion()
                    ->action(function ($records) {
                        foreach ($records as $record) {
                            Artisan::call('queue:retry', ['id' => [$record->uuid]]);
                        }
                        Notification::make()->title(count($records) . ' jobs queued for retry')->success()->send();
                    }),

                Tables\Actions\BulkAction::make('deleteSelected')
                    ->label('Delete Selected')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->deselectRecordsAfterCompletion()
                    ->action(function ($records) {
                        FailedJob::whereIn('id', $records->pluck('id'))->delete();
                        Notification::make()->title(count($records) . ' failed jobs deleted')->success()->send();
                    }),
            ])
            ->headerActions([
                Tables\Actions\Action::make('retryAll')
                    ->label('Retry All')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalDescription('Retry all failed jobs? They will be re-queued for processing.')
                    ->action(function () {
                        Artisan::call('queue:retry', ['id' => ['all']]);
                        Notification::make()->title('All failed jobs queued for retry')->success()->send();
                    }),

                Tables\Actions\Action::make('flushAll')
                    ->label('Delete All')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalDescription('Delete ALL failed jobs? This cannot be undone.')
                    ->action(function () {
                        Artisan::call('queue:flush');
                        Notification::make()->title('All failed jobs deleted')->success()->send();
                    }),
            ])
            ->emptyStateHeading('No failed jobs')
            ->emptyStateDescription('All queue jobs are running smoothly.')
            ->emptyStateIcon('heroicon-o-check-circle')
            ->striped()
            ->paginated([10, 25, 50]);
    }
}

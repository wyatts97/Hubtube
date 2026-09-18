<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\RequiresPermission;
use App\Filament\Resources\VideoResource;
use App\Models\StorageReclaim;
use App\Services\Storage\StorageReclaimService;
use App\Support\Bytes;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;

/**
 * Review screen for re-compressed original uploads.
 *
 * Each attempt produces a smaller file and then *stops*: video_path still names
 * the upload, so the site serves exactly what it did before until someone here
 * accepts. Nothing expires and nothing is swept — a reclaim waits indefinitely
 * rather than delete a file nobody agreed to delete.
 *
 * The number to read carefully is "awaiting review": those bytes are not saved
 * yet — both copies are on disk while it waits — so they are counted separately
 * from what has actually been reclaimed.
 */
class StorageReclaims extends Page implements HasTable
{
    use InteractsWithTable;
    use RequiresPermission;

    protected static string $requiredPermission = 'update_video';

    protected static string|\BackedEnum|null $navigationIcon = 'phosphor-recycle';

    protected static ?string $navigationLabel = 'Storage Reclaim';

    protected static string|\UnitEnum|null $navigationGroup = 'Content';

    protected static ?int $navigationSort = 100;

    protected string $view = 'filament.pages.storage-reclaims';

    public static function getNavigationBadge(): ?string
    {
        $waiting = StorageReclaim::query()->awaitingReview()->count();

        return $waiting > 0 ? (string) $waiting : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    /** Headline figures for the page's own summary, in bytes. */
    public function getTotalsProperty(): array
    {
        $rows = StorageReclaim::query()
            ->selectRaw('status, COUNT(*) as rows_count, SUM(before_bytes - after_bytes) as saved')
            ->whereNotNull('before_bytes')
            ->whereNotNull('after_bytes')
            ->groupBy('status')
            ->get()
            ->keyBy('status');

        return [
            'reclaimed' => (int) ($rows[StorageReclaim::ACCEPTED]->saved ?? 0),
            // Held, not saved: the old file is still on disk.
            'awaiting' => (int) ($rows[StorageReclaim::AWAITING_REVIEW]->saved ?? 0),
            'awaiting_count' => StorageReclaim::query()->awaitingReview()->count(),
            'running_count' => StorageReclaim::query()
                ->whereIn('status', [StorageReclaim::PENDING, StorageReclaim::RUNNING])
                ->count(),
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(StorageReclaim::query()->with(['video:id,title,slug', 'requester:id,username']))
            ->defaultSort('created_at', 'desc')
            // Rows move on their own while an encode is running.
            ->poll('30s')
            ->columns([
                TextColumn::make('video.title')
                    ->label('Video')
                    ->limit(40)
                    ->searchable()
                    ->description(fn (StorageReclaim $record): string => $record->targetLabel()),

                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        StorageReclaim::AWAITING_REVIEW => 'warning',
                        StorageReclaim::ACCEPTED => 'success',
                        StorageReclaim::FAILED, StorageReclaim::REVERT_FAILED => 'danger',
                        StorageReclaim::REVERTED, StorageReclaim::SKIPPED => 'gray',
                        default => 'info',
                    })
                    ->formatStateUsing(fn (string $state): string => str_replace('_', ' ', ucfirst($state)))
                    ->description(fn (StorageReclaim $record): ?string => $record->error),

                TextColumn::make('before_bytes')
                    ->label('Before')
                    ->formatStateUsing(fn (?int $state): string => $state ? Bytes::format($state) : '—')
                    ->sortable(),

                TextColumn::make('after_bytes')
                    ->label('After')
                    ->formatStateUsing(fn (?int $state): string => $state ? Bytes::format($state) : '—')
                    ->sortable(),

                TextColumn::make('saved')
                    ->label('Saved')
                    ->state(fn (StorageReclaim $record): string => $record->savingLabel())
                    ->weight('bold')
                    ->color(fn (StorageReclaim $record): string => $record->isRealised() ? 'success' : 'gray'),

                TextColumn::make('requester.username')
                    ->label('Requested by')
                    ->placeholder('Console')
                    ->toggleable(),

                TextColumn::make('created_at')
                    ->label('Started')
                    ->since()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        StorageReclaim::AWAITING_REVIEW => 'Awaiting review',
                        StorageReclaim::PENDING => 'Pending',
                        StorageReclaim::RUNNING => 'Running',
                        StorageReclaim::ACCEPTED => 'Accepted',
                        StorageReclaim::REVERTED => 'Discarded',
                        StorageReclaim::SKIPPED => 'Skipped',
                        StorageReclaim::FAILED => 'Failed',
                        StorageReclaim::REVERT_FAILED => 'Revert failed',
                    ]),
            ])
            ->recordActions([
                Action::make('accept')
                    ->label('Accept')
                    ->icon('phosphor-check')
                    ->color('success')
                    ->visible(fn (StorageReclaim $record): bool => $record->status === StorageReclaim::AWAITING_REVIEW)
                    ->requiresConfirmation()
                    ->modalHeading('Accept this reclaim?')
                    ->modalDescription(fn (StorageReclaim $record): string => sprintf(
                        'The kept copy of %s is deleted permanently and %s is freed. There are no backups of media files.',
                        $record->targetLabel(),
                        Bytes::format($record->savedBytes()),
                    ))
                    ->modalSubmitActionLabel('Accept and delete')
                    ->action(function (StorageReclaim $record) {
                        $this->announce(
                            app(StorageReclaimService::class)->accept($record),
                            "Reclaimed {$record->savingLabel()}",
                            'This reclaim could not be accepted.',
                        );
                    }),

                Action::make('revert')
                    ->label('Discard')
                    ->icon('phosphor-arrow-u-up-left')
                    ->color('gray')
                    ->visible(fn (StorageReclaim $record): bool => $record->status === StorageReclaim::AWAITING_REVIEW)
                    ->requiresConfirmation()
                    ->modalHeading('Throw the re-compressed file away?')
                    ->modalDescription('The upload keeps serving, exactly as it is now — it was never repointed. Only the candidate is deleted.')
                    ->action(function (StorageReclaim $record) {
                        $this->announce(
                            app(StorageReclaimService::class)->revert($record),
                            'The re-compressed file was discarded',
                            'That could not be discarded.',
                        );
                    }),

                Action::make('view')
                    ->label('Open video')
                    ->icon('phosphor-eye')
                    ->color('gray')
                    ->url(fn (StorageReclaim $record): ?string => $record->video
                        ? VideoResource::getUrl('edit', ['record' => $record->video_id])
                        : null)
                    ->openUrlInNewTab()
                    ->visible(fn (StorageReclaim $record): bool => $record->video !== null),
            ])
            ->toolbarActions([
                BulkAction::make('acceptSelected')
                    ->label('Accept selected')
                    ->icon('phosphor-check')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Accept the selected reclaims?')
                    ->modalDescription('Every kept file is deleted permanently. There are no backups of media files.')
                    ->action(function (Collection $records) {
                        $this->applyToMany($records, 'accept');
                    }),

                BulkAction::make('revertSelected')
                    ->label('Discard selected')
                    ->icon('phosphor-arrow-u-up-left')
                    ->color('gray')
                    ->requiresConfirmation()
                    ->action(function (Collection $records) {
                        $this->applyToMany($records, 'revert');
                    }),
            ])
            ->emptyStateHeading('No storage reclaims yet')
            ->emptyStateDescription('Re-compressions are requested from the Media Library, or with `php artisan storage:reclaim`.')
            ->emptyStateIcon('phosphor-recycle');
    }

    /** Run one review action over a selection, reporting the tally once. */
    protected function applyToMany(Collection $records, string $method): void
    {
        $service = app(StorageReclaimService::class);
        $done = 0;
        $freed = 0;

        foreach ($records as $record) {
            $saving = $record->savedBytes();

            if ($service->{$method}($record)) {
                $done++;
                $freed += $method === 'accept' ? $saving : 0;
            }
        }

        $skipped = $records->count() - $done;

        Notification::make()
            ->title($method === 'accept'
                ? "Accepted {$done} reclaim(s), freeing ".Bytes::format($freed)
                : "Discarded {$done} reclaim(s)")
            ->body($skipped > 0 ? "{$skipped} could not be processed and were left alone." : null)
            ->color($done > 0 ? 'success' : 'warning')
            ->send();
    }

    protected function announce(bool $ok, string $success, string $failure): void
    {
        Notification::make()
            ->title($ok ? $success : $failure)
            ->color($ok ? 'success' : 'danger')
            ->send();
    }
}

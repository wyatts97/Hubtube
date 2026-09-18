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
 * Review screen for storage reclaims.
 *
 * Every reclaim produces a smaller file and then *stops*, holding the old one
 * until someone here says yes. This page ships before any operation exists, so
 * there is never a release in which work can be created with no way to review
 * it.
 *
 * The number to read carefully is "awaiting review": those bytes are not saved
 * yet — they are being held twice, once as the new file and once as the old —
 * so they are counted separately from what has actually been reclaimed.
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

        $realised = 0;

        foreach ([StorageReclaim::ACCEPTED, StorageReclaim::EXPIRED] as $status) {
            $realised += (int) ($rows[$status]->saved ?? 0);
        }

        return [
            'reclaimed' => $realised,
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
                        StorageReclaim::ACCEPTED, StorageReclaim::EXPIRED => 'success',
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
                    ->placeholder('Scheduled')
                    ->toggleable(),

                TextColumn::make('keep_until')
                    ->label('Auto-accepts')
                    ->placeholder('—')
                    ->state(fn (StorageReclaim $record): ?string => $record->status === StorageReclaim::AWAITING_REVIEW
                        ? $record->keep_until?->diffForHumans()
                        : null)
                    ->tooltip(fn (StorageReclaim $record): ?string => $record->keep_until?->toDayDateTimeString())
                    ->sortable(),

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
                        StorageReclaim::EXPIRED => 'Auto-accepted',
                        StorageReclaim::REVERTED => 'Reverted',
                        StorageReclaim::SKIPPED => 'Skipped',
                        StorageReclaim::FAILED => 'Failed',
                        StorageReclaim::REVERT_FAILED => 'Revert failed',
                    ]),
                SelectFilter::make('target')
                    ->options([
                        StorageReclaim::TARGET_ORIGINAL => 'Original upload',
                        StorageReclaim::TARGET_RENDITION => 'Rendition',
                        StorageReclaim::TARGET_HLS => 'HLS duplicate',
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
                    ->label('Revert')
                    ->icon('phosphor-arrow-u-up-left')
                    ->color('gray')
                    ->visible(fn (StorageReclaim $record): bool => $record->status === StorageReclaim::AWAITING_REVIEW)
                    ->requiresConfirmation()
                    ->modalHeading('Put the original file back?')
                    ->modalDescription('The kept file is restored and the re-encoded one discarded. Playback goes back to exactly what it was.')
                    ->action(function (StorageReclaim $record) {
                        $this->announce(
                            app(StorageReclaimService::class)->revert($record),
                            'The original file was restored',
                            'The kept file could not be restored. The video is still playing the file it was.',
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
                    ->label('Revert selected')
                    ->icon('phosphor-arrow-u-up-left')
                    ->color('gray')
                    ->requiresConfirmation()
                    ->action(function (Collection $records) {
                        $this->applyToMany($records, 'revert');
                    }),
            ])
            ->emptyStateHeading('No storage reclaims yet')
            ->emptyStateDescription('Reclaims are requested from the Media Library, or with `php artisan storage:reclaim`.')
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
                : "Reverted {$done} reclaim(s)")
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

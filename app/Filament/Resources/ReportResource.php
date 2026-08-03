<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ReportResource\Pages\ListReports;
use App\Filament\Resources\ReportResource\Pages\ViewReport;
use App\Filament\Resources\ReportResource\Pages;
use App\Models\Comment;
use App\Models\Report;
use App\Models\User;
use App\Models\Video;
use App\Filament\Resources\UserResource;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification as FilamentNotification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

class ReportResource extends Resource
{
    protected static ?string $model = Report::class;

    protected static string | \BackedEnum | null $navigationIcon = 'phosphor-flag';

    protected static ?string $navigationLabel = 'Reports';

    protected static string | \UnitEnum | null $navigationGroup = 'Content';

    protected static ?int $navigationSort = 5;

    // Pending count is surfaced as a topbar pill (see SystemStatusBar::getActionItems).

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Report Details')
                    ->schema([
                        TextInput::make('user.username')
                            ->label('Reported By')
                            ->disabled(),
                        TextInput::make('reportable_type')
                            ->label('Content Type')
                            ->formatStateUsing(fn (?string $state) => $state ? class_basename($state) : '—')
                            ->disabled(),
                        TextInput::make('reason')
                            ->disabled()
                            ->formatStateUsing(fn (?string $state) => static::reasonLabels()[$state] ?? $state),
                        Select::make('status')
                            ->options([
                                Report::STATUS_PENDING => 'Pending',
                                Report::STATUS_REVIEWING => 'Reviewing',
                                Report::STATUS_RESOLVED => 'Resolved',
                                Report::STATUS_DISMISSED => 'Dismissed',
                            ])
                            ->disabled(),
                        Textarea::make('description')
                            ->label('Reporter Notes')
                            ->disabled()
                            ->rows(3)
                            ->columnSpanFull(),
                        Textarea::make('resolution_notes')
                            ->label('Resolution Notes')
                            ->disabled()
                            ->rows(3)
                            ->columnSpanFull(),
                        TextInput::make('resolvedBy.username')
                            ->label('Resolved By')
                            ->disabled(),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['user', 'resolvedBy', 'reportable']))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('user.username')
                    ->label('Reported By')
                    ->searchable()
                    ->icon('phosphor-user')
                    ->iconColor('gray')
                    ->placeholder('(deleted)'),

                TextColumn::make('reportable_type')
                    ->label('Type')
                    ->badge()
                    ->formatStateUsing(fn (?string $state) => $state ? class_basename($state) : '—')
                    ->color(fn (?string $state) => match ($state) {
                        Video::class => 'info',
                        Comment::class => 'warning',
                        User::class => 'danger',
                        default => 'gray',
                    }),

                TextColumn::make('reported_content')
                    ->label('Reported Content')
                    ->getStateUsing(fn (Report $record) => static::contentLabel($record))
                    ->limit(50)
                    ->url(fn (Report $record) => static::contentUrl($record))
                    ->openUrlInNewTab()
                    ->color('gray'),

                TextColumn::make('reason')
                    ->badge()
                    ->formatStateUsing(fn (?string $state) => static::reasonLabels()[$state] ?? $state)
                    ->color(fn (?string $state) => match ($state) {
                        Report::REASON_ILLEGAL, Report::REASON_UNDERAGE => 'danger',
                        Report::REASON_HARASSMENT, Report::REASON_COPYRIGHT => 'warning',
                        default => 'gray',
                    }),

                TextColumn::make('description')
                    ->label('Details')
                    ->limit(60)
                    ->placeholder('(no details)')
                    ->size('sm')
                    ->color('gray')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        Report::STATUS_PENDING => 'warning',
                        Report::STATUS_REVIEWING => 'info',
                        Report::STATUS_RESOLVED => 'success',
                        Report::STATUS_DISMISSED => 'gray',
                        default => 'gray',
                    }),

                TextColumn::make('created_at')
                    ->label('Reported')
                    ->since()
                    ->sortable()
                    ->size('sm')
                    ->color('gray')
                    ->tooltip(fn (Report $record): string => $record->created_at?->format('M j, Y g:i A') ?? ''),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        Report::STATUS_PENDING => 'Pending',
                        Report::STATUS_REVIEWING => 'Reviewing',
                        Report::STATUS_RESOLVED => 'Resolved',
                        Report::STATUS_DISMISSED => 'Dismissed',
                    ]),
                SelectFilter::make('reportable_type')
                    ->label('Content Type')
                    ->options([
                        Video::class => 'Video',
                        Comment::class => 'Comment',
                        User::class => 'User',
                    ]),
                SelectFilter::make('reason')
                    ->options(static::reasonLabels()),
            ])
            ->recordActions([
                ViewAction::make(),
                ActionGroup::make(static::resolutionActions()),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('dismiss')
                        ->label('Dismiss')
                        ->icon('phosphor-x-circle')
                        ->color('gray')
                        ->requiresConfirmation()
                        ->action(function (Collection $records) {
                            $userId = auth()->id();
                            $records->each(function (Report $r) use ($userId) {
                                if (in_array($r->status, [Report::STATUS_PENDING, Report::STATUS_REVIEWING])) {
                                    $r->update([
                                        'status' => Report::STATUS_DISMISSED,
                                        'resolved_at' => now(),
                                        'resolved_by' => $userId,
                                    ]);
                                }
                            });
                        })
                        ->deselectRecordsAfterCompletion(),
                ]),
            ])
            ->striped();
    }

    /**
     * Shared record-level moderation actions, used both in the table's
     * ActionGroup and as header actions on the View page. Each action relies
     * on Filament automatically injecting the current `Report $record` into
     * its closures, so the same definitions work in both contexts.
     *
     * @return array<int, Action>
     */
    public static function resolutionActions(): array
    {
        return [
            Action::make('start_review')
                ->label('Start Reviewing')
                ->icon('phosphor-magnifying-glass')
                ->color('info')
                ->visible(fn (Report $record) => $record->status === Report::STATUS_PENDING)
                ->action(fn (Report $record) => $record->update(['status' => Report::STATUS_REVIEWING])),

            Action::make('remove_content')
                ->label('Remove Content')
                ->icon('phosphor-trash')
                ->color('danger')
                ->requiresConfirmation()
                ->modalDescription('This will hide/delete the reported content and mark this report as resolved.')
                ->visible(fn (Report $record) => in_array($record->status, [Report::STATUS_PENDING, Report::STATUS_REVIEWING])
                    && in_array($record->reportable_type, [Video::class, Comment::class])
                    && $record->reportable)
                ->action(function (Report $record) {
                    $removed = static::removeReportedContent($record);

                    $record->resolve(auth()->user(), $removed
                        ? 'Content removed by moderator.'
                        : 'Content could not be found (already removed).');

                    FilamentNotification::make()
                        ->title($removed ? 'Content removed and report resolved.' : 'Report resolved (content already gone).')
                        ->success()
                        ->send();
                }),

            Action::make('resolve')
                ->label('Resolve')
                ->icon('phosphor-check-circle')
                ->color('success')
                ->visible(fn (Report $record) => in_array($record->status, [Report::STATUS_PENDING, Report::STATUS_REVIEWING]))
                ->schema([
                    Textarea::make('resolution_notes')
                        ->label('Resolution Notes')
                        ->rows(3),
                ])
                ->action(fn (Report $record, array $data) => $record->resolve(auth()->user(), $data['resolution_notes'] ?? null)),

            Action::make('dismiss')
                ->label('Dismiss')
                ->icon('phosphor-x-circle')
                ->color('gray')
                ->visible(fn (Report $record) => in_array($record->status, [Report::STATUS_PENDING, Report::STATUS_REVIEWING]))
                ->schema([
                    Textarea::make('resolution_notes')
                        ->label('Reason (optional)')
                        ->rows(3),
                ])
                ->action(fn (Report $record, array $data) => $record->dismiss(auth()->user(), $data['resolution_notes'] ?? null)),
        ];
    }

    protected static function removeReportedContent(Report $record): bool
    {
        $reportable = $record->reportable;

        if (!$reportable) {
            return false;
        }

        if ($reportable instanceof Video) {
            $reportable->update(['is_approved' => false]);
            return true;
        }

        if ($reportable instanceof Comment) {
            $reportable->delete();
            return true;
        }

        return false;
    }

    protected static function contentLabel(Report $record): string
    {
        $reportable = $record->reportable;

        if (!$reportable) {
            return '(deleted content) #' . $record->reportable_id;
        }

        return $reportable->title ?? $reportable->content ?? $reportable->username ?? ('#' . $record->reportable_id);
    }

    protected static function contentUrl(Report $record): ?string
    {
        $reportable = $record->reportable;

        if (!$reportable) {
            return null;
        }

        return match (true) {
            $reportable instanceof Video => url('/' . $reportable->slug),
            $reportable instanceof Comment => $reportable->video?->slug ? url('/' . $reportable->video->slug) : null,
            $reportable instanceof User => UserResource::getUrl('edit', ['record' => $reportable]),
            default => null,
        };
    }

    protected static function reasonLabels(): array
    {
        return [
            Report::REASON_SPAM => 'Spam or Misleading',
            Report::REASON_HARASSMENT => 'Harassment',
            Report::REASON_ILLEGAL => 'Illegal Content',
            Report::REASON_COPYRIGHT => 'Copyright Violation',
            Report::REASON_UNDERAGE => 'Underage Content',
            Report::REASON_OTHER => 'Other',
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListReports::route('/'),
            'view' => ViewReport::route('/{record}'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }
}

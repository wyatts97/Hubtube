<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DmcaRequestResource\Pages\ListDmcaRequests;
use App\Filament\Resources\DmcaRequestResource\Pages\ViewDmcaRequest;
use App\Models\DmcaRequest;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification as FilamentNotification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

class DmcaRequestResource extends Resource
{
    protected static ?string $model = DmcaRequest::class;

    protected static string|\BackedEnum|null $navigationIcon = 'phosphor-scales';

    protected static ?string $navigationLabel = 'DMCA Requests';

    protected static string|\UnitEnum|null $navigationGroup = 'Moderation';

    protected static ?int $navigationSort = 6;

    protected static ?string $recordTitleAttribute = 'complainant_name';

    public static function getGloballySearchableAttributes(): array
    {
        // copyrighted_work_description is deliberately excluded: it is a long
        // TEXT blob, and a LIKE scan over it buys nothing admins search for.
        return ['complainant_name', 'complainant_email', 'infringing_urls'];
    }

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        return [
            'Email' => $record->complainant_email,
            'Video' => $record->video?->title ?: '(no match)',
            'Status' => ucfirst((string) $record->status),
        ];
    }

    public static function getGlobalSearchEloquentQuery(): Builder
    {
        return parent::getGlobalSearchEloquentQuery()->with('video');
    }

    public static function getNavigationBadge(): ?string
    {
        $count = static::getModel()::pending()->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Complainant')
                    ->schema([
                        TextInput::make('complainant_name')->label('Name')->disabled(),
                        TextInput::make('complainant_email')->label('Email')->disabled(),
                        TextInput::make('complainant_company')->label('Company/Agency')->disabled(),
                        TextInput::make('signature')->label('Signature')->disabled(),
                    ])->columns(2),

                Section::make('Claim')
                    ->schema([
                        Textarea::make('copyrighted_work_description')
                            ->label('Copyrighted Work')
                            ->disabled()
                            ->rows(3)
                            ->columnSpanFull(),
                        Textarea::make('infringing_urls')
                            ->label('Infringing URL(s)')
                            ->disabled()
                            ->rows(3)
                            ->columnSpanFull(),
                        Checkbox::make('good_faith_statement')->label('Good faith statement affirmed')->disabled(),
                        Checkbox::make('accuracy_statement')->label('Accuracy/perjury statement affirmed')->disabled(),
                    ]),

                Section::make('Resolution')
                    ->schema([
                        TextInput::make('status')->disabled(),
                        Textarea::make('admin_notes')->label('Admin Notes')->disabled()->rows(3)->columnSpanFull(),
                        TextInput::make('resolvedBy.username')->label('Resolved By')->disabled(),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['video', 'resolvedBy']))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('complainant_name')
                    ->label('Complainant')
                    ->searchable()
                    ->description(fn (DmcaRequest $record): string => $record->complainant_email),

                TextColumn::make('video.title')
                    ->label('Reported Video')
                    ->limit(50)
                    ->url(fn (DmcaRequest $record): ?string => $record->video ? url('/'.$record->video->slug) : null)
                    ->openUrlInNewTab()
                    ->placeholder('(no match — see URLs)')
                    ->color('gray')
                    ->toggleable(),

                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        DmcaRequest::STATUS_PENDING => 'warning',
                        DmcaRequest::STATUS_ACTIONED => 'success',
                        DmcaRequest::STATUS_REJECTED => 'gray',
                        default => 'gray',
                    })
                    ->toggleable(),

                TextColumn::make('created_at')
                    ->label('Submitted')
                    ->since()
                    ->sortable()
                    ->size('sm')
                    ->color('gray')
                    ->tooltip(fn (DmcaRequest $record): string => $record->created_at?->format('M j, Y g:i A') ?? '')
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        DmcaRequest::STATUS_PENDING => 'Pending',
                        DmcaRequest::STATUS_ACTIONED => 'Actioned',
                        DmcaRequest::STATUS_REJECTED => 'Rejected',
                    ]),
            ])
            ->recordActions([
                ViewAction::make(),
                ActionGroup::make(static::resolutionActions()),
                DeleteAction::make()
                    ->label('Delete')
                    ->icon('phosphor-trash')
                    ->color('danger')
                    ->requiresConfirmation(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->label('Delete')
                        ->icon('phosphor-trash')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->deselectRecordsAfterCompletion(),
                    BulkAction::make('action')
                        ->label('Mark Actioned')
                        ->icon('phosphor-check-circle')
                        ->color('success')
                        ->requiresConfirmation()
                        ->schema([
                            Textarea::make('admin_notes')->label('Notes')->rows(3),
                        ])
                        ->action(function (Collection $records, array $data) {
                            $user = auth()->user();
                            $records->each(function (DmcaRequest $r) use ($user, $data) {
                                if ($r->status === DmcaRequest::STATUS_PENDING) {
                                    $r->action($user, $data['admin_notes'] ?? null);
                                }
                            });
                        })
                        ->deselectRecordsAfterCompletion(),
                    BulkAction::make('reject')
                        ->label('Reject')
                        ->icon('phosphor-x-circle')
                        ->color('gray')
                        ->requiresConfirmation()
                        ->schema([
                            Textarea::make('admin_notes')->label('Reason (optional)')->rows(3),
                        ])
                        ->action(function (Collection $records, array $data) {
                            $user = auth()->user();
                            $records->each(function (DmcaRequest $r) use ($user, $data) {
                                if ($r->status === DmcaRequest::STATUS_PENDING) {
                                    $r->reject($user, $data['admin_notes'] ?? null);
                                }
                            });
                        })
                        ->deselectRecordsAfterCompletion(),
                ]),
            ])
            ->striped()
            ->emptyStateIcon('phosphor-scales')
            ->emptyStateHeading('No DMCA requests')
            ->emptyStateDescription('Takedown notices submitted through the DMCA form appear here for review.');
    }

    /**
     * Shared record-level actions, used both in the table's ActionGroup and as
     * header actions on the View page.
     *
     * @return array<int, Action>
     */
    public static function resolutionActions(): array
    {
        return [
            Action::make('action_and_remove')
                ->label('Action & Remove Video')
                ->icon('phosphor-trash')
                ->color('danger')
                ->requiresConfirmation()
                ->modalDescription('This will unpublish the linked video and mark this request as actioned.')
                ->visible(fn (DmcaRequest $record) => $record->status === DmcaRequest::STATUS_PENDING && $record->video)
                ->schema([
                    Textarea::make('admin_notes')->label('Notes')->rows(3),
                ])
                ->action(function (DmcaRequest $record, array $data) {
                    $record->video?->update(['is_approved' => false]);
                    $record->action(auth()->user(), $data['admin_notes'] ?? 'Video unpublished.');

                    FilamentNotification::make()
                        ->title('Video unpublished and request actioned.')
                        ->success()
                        ->send();
                }),

            Action::make('action')
                ->label('Mark Actioned')
                ->icon('phosphor-check-circle')
                ->color('success')
                ->visible(fn (DmcaRequest $record) => $record->status === DmcaRequest::STATUS_PENDING)
                ->schema([
                    Textarea::make('admin_notes')->label('Notes')->rows(3),
                ])
                ->action(fn (DmcaRequest $record, array $data) => $record->action(auth()->user(), $data['admin_notes'] ?? null)),

            Action::make('reject')
                ->label('Reject')
                ->icon('phosphor-x-circle')
                ->color('gray')
                ->visible(fn (DmcaRequest $record) => $record->status === DmcaRequest::STATUS_PENDING)
                ->schema([
                    Textarea::make('admin_notes')->label('Reason (optional)')->rows(3),
                ])
                ->action(fn (DmcaRequest $record, array $data) => $record->reject(auth()->user(), $data['admin_notes'] ?? null)),
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDmcaRequests::route('/'),
            'view' => ViewDmcaRequest::route('/{record}'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }
}

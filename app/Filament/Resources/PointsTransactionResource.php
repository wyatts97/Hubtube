<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PointsTransactionResource\Pages\ListPointsTransactions;
use App\Models\PointsTransaction;
use App\Models\User;
use App\Services\PointsService;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class PointsTransactionResource extends Resource
{
    protected static ?string $model = PointsTransaction::class;

    protected static string|\BackedEnum|null $navigationIcon = 'phosphor-coins';

    protected static string|\UnitEnum|null $navigationGroup = 'Monetization';

    protected static ?string $navigationLabel = 'Points Ledger';

    protected static ?int $navigationSort = 6;

    // Deliberately excluded from global search. There is no record title
    // attribute, so every result would render as an identical model label, and
    // the only useful search key is user.username -- which would flood the
    // topbar dropdown with ledger rows and surface balances ambiently. These
    // are browsed by filter from the resource's own table instead.
    protected static bool $isGloballySearchable = false;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->deferLoading()
            ->columns([
                TextColumn::make('user.username')->label('User')->searchable()->sortable(),
                TextColumn::make('type')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        PointsTransaction::TYPE_VIDEO_UPLOAD => 'success',
                        PointsTransaction::TYPE_IMAGE_UPLOAD => 'info',
                        PointsTransaction::TYPE_COMMENT => 'gray',
                        PointsTransaction::TYPE_REDEMPTION => 'warning',
                        PointsTransaction::TYPE_ADMIN_ADJUSTMENT => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state) => str_replace('_', ' ', ucwords($state, '_')))
                    ->toggleable(),
                TextColumn::make('points')
                    ->label('Points')
                    ->sortable()
                    ->formatStateUsing(fn (int $state) => ($state > 0 ? '+' : '').number_format($state))
                    ->color(fn (int $state) => $state > 0 ? 'success' : 'danger')
                    ->toggleable(),
                TextColumn::make('balance_after')->label('Balance After')->sortable()
                    ->toggleable(),
                TextColumn::make('description')->limit(40)->tooltip(fn (PointsTransaction $record) => $record->description)
                    ->toggleable(),
                TextColumn::make('created_at')->dateTime()->sortable()
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('type')->options([
                    PointsTransaction::TYPE_VIDEO_UPLOAD => 'Video Upload',
                    PointsTransaction::TYPE_IMAGE_UPLOAD => 'Image Upload',
                    PointsTransaction::TYPE_COMMENT => 'Comment',
                    PointsTransaction::TYPE_REDEMPTION => 'Redemption',
                    PointsTransaction::TYPE_ADMIN_ADJUSTMENT => 'Admin Adjustment',
                ]),
            ])
            ->recordActions([])
            ->toolbarActions([
                Action::make('adjust_points')
                    ->label('Adjust User Points')
                    ->icon('phosphor-plus-minus')
                    ->color('warning')
                    ->schema([
                        Select::make('user_id')
                            ->label('User')
                            ->options(fn () => User::orderBy('username')->pluck('username', 'id'))
                            ->searchable()
                            ->required(),
                        TextInput::make('points')
                            ->label('Points (use negative to deduct)')
                            ->numeric()
                            ->required(),
                        TextInput::make('reason')
                            ->label('Reason (shown to user in history)')
                            ->required(),
                    ])
                    ->action(function (array $data) {
                        $user = User::findOrFail($data['user_id']);
                        $points = (int) $data['points'];

                        if ($points === 0) {
                            Notification::make()->title('Points cannot be zero.')->danger()->send();

                            return;
                        }

                        $service = app(PointsService::class);

                        if ($points > 0) {
                            $service->award($user, PointsTransaction::TYPE_ADMIN_ADJUSTMENT, $points, null, "Admin adjustment: {$data['reason']}");
                        } else {
                            $service->spend($user, abs($points), PointsTransaction::TYPE_ADMIN_ADJUSTMENT, "Admin adjustment: {$data['reason']}");
                        }

                        Notification::make()->title('Points adjusted successfully.')->success()->send();
                    }),
            ])
            ->defaultSort('created_at', 'desc')
            ->striped()
            ->emptyStateIcon('phosphor-coins')
            ->emptyStateHeading('No points activity yet')
            ->emptyStateDescription('Points earned from uploads and comments, and points spent on redemptions, are logged here.');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPointsTransactions::route('/'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }
}

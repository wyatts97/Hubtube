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

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return $table
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
                    ->formatStateUsing(fn (string $state) => str_replace('_', ' ', ucwords($state, '_'))),
                TextColumn::make('points')
                    ->label('Points')
                    ->sortable()
                    ->formatStateUsing(fn (int $state) => ($state > 0 ? '+' : '') . number_format($state))
                    ->color(fn (int $state) => $state > 0 ? 'success' : 'danger'),
                TextColumn::make('balance_after')->label('Balance After')->sortable(),
                TextColumn::make('description')->limit(40)->tooltip(fn (PointsTransaction $record) => $record->description),
                TextColumn::make('created_at')->dateTime()->sortable(),
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
            ->striped();
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

<?php

namespace App\Filament\Resources;

use App\Filament\Resources\WalletTransactionResource\Pages\ListWalletTransactions;
use App\Models\Setting;
use App\Models\WalletTransaction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class WalletTransactionResource extends Resource
{
    protected static ?string $model = WalletTransaction::class;

    protected static string|\BackedEnum|null $navigationIcon = 'phosphor-currency-dollar';

    protected static string|\UnitEnum|null $navigationGroup = 'Monetization';

    protected static ?int $navigationSort = 1;

    // Deliberately excluded from global search. There is no record title
    // attribute, so every result would render as an identical model label, and
    // the only useful search key is user.username -- which would flood the
    // topbar dropdown with ledger rows and surface balances ambiently. These
    // are browsed by filter from the resource's own table instead.
    protected static bool $isGloballySearchable = false;

    public static function shouldRegisterNavigation(): bool
    {
        return (bool) Setting::get('monetization_enabled', true);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Transaction Details')
                    ->schema([
                        Select::make('user_id')
                            ->relationship('user', 'username')
                            ->required()
                            ->searchable()
                            ->disabled(),
                        Select::make('type')
                            ->options([
                                'deposit' => 'Deposit',
                                'withdrawal' => 'Withdrawal',
                                'video_purchase' => 'Video Purchase',
                                'video_sale' => 'Video Sale',
                                'subscription' => 'Subscription',
                                'refund' => 'Refund',
                            ])
                            ->disabled(),
                        TextInput::make('amount')
                            ->numeric()
                            ->prefix('$')
                            ->disabled(),
                        TextInput::make('balance_after')
                            ->numeric()
                            ->prefix('$')
                            ->disabled(),
                        Textarea::make('description')
                            ->disabled()
                            ->columnSpanFull(),
                    ])->columns(2),
                Section::make('Status')
                    ->schema([
                        Select::make('status')
                            ->options([
                                'pending' => 'Pending',
                                'completed' => 'Completed',
                                'failed' => 'Failed',
                                'cancelled' => 'Cancelled',
                            ]),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->deferLoading()
            ->columns([
                TextColumn::make('user.username')
                    ->label('User')
                    ->searchable(),
                TextColumn::make('type')
                    ->badge()
                    ->color(fn (string $state): string => match (true) {
                        in_array($state, ['deposit', 'video_sale']) => 'success',
                        in_array($state, ['withdrawal', 'video_purchase']) => 'danger',
                        $state === 'refund' => 'warning',
                        default => 'gray',
                    })
                    ->toggleable(),

                TextColumn::make('amount')
                    ->money('USD')
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('balance_after')
                    ->money('USD')
                    ->toggleable(),

                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'pending' => 'warning',
                        'completed' => 'success',
                        'failed' => 'danger',
                        'cancelled' => 'gray',
                        default => 'gray',
                    })
                    ->toggleable(),

                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->options([
                        'deposit' => 'Deposit',
                        'withdrawal' => 'Withdrawal',
                        'video_purchase' => 'Video Purchase',
                        'video_sale' => 'Video Sale',
                    ]),
                SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'completed' => 'Completed',
                        'failed' => 'Failed',
                        'cancelled' => 'Cancelled',
                    ]),
            ])
            ->recordActions([
                ViewAction::make(),
            ])
            ->toolbarActions([])
            ->striped()
            ->emptyStateIcon('phosphor-currency-dollar')
            ->emptyStateHeading('No transactions yet')
            ->emptyStateDescription('Deposits, purchases, sales, and refunds are recorded here automatically.');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListWalletTransactions::route('/'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }
}

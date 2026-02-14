<?php

namespace App\Filament\Resources;

use App\Filament\Resources\WithdrawalRequestResource\Pages;
use App\Models\WalletTransaction;
use App\Models\WithdrawalRequest;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;

class WithdrawalRequestResource extends Resource
{
    protected static ?string $model = WithdrawalRequest::class;

    protected static ?string $navigationIcon = 'heroicon-o-arrow-up-tray';

    protected static ?string $navigationGroup = 'Monetization';

    protected static ?int $navigationSort = 2;

    public static function getNavigationBadge(): ?string
    {
        $count = static::getModel()::where('status', WithdrawalRequest::STATUS_PENDING)->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Request Details')
                    ->schema([
                        Forms\Components\Select::make('user_id')
                            ->relationship('user', 'username')
                            ->disabled(),
                        Forms\Components\TextInput::make('amount')
                            ->prefix('$')
                            ->numeric()
                            ->disabled(),
                        Forms\Components\TextInput::make('currency')
                            ->disabled(),
                        Forms\Components\TextInput::make('payment_method')
                            ->disabled(),
                        Forms\Components\KeyValue::make('payment_details')
                            ->disabled()
                            ->columnSpanFull(),
                    ])->columns(2),

                Forms\Components\Section::make('Processing')
                    ->schema([
                        Forms\Components\Select::make('status')
                            ->options([
                                WithdrawalRequest::STATUS_PENDING => 'Pending',
                                WithdrawalRequest::STATUS_PROCESSING => 'Processing',
                                WithdrawalRequest::STATUS_COMPLETED => 'Completed',
                                WithdrawalRequest::STATUS_REJECTED => 'Rejected',
                            ])
                            ->disabled(),
                        Forms\Components\Select::make('processed_by')
                            ->relationship('processedBy', 'username')
                            ->disabled(),
                        Forms\Components\DateTimePicker::make('processed_at')
                            ->disabled(),
                        Forms\Components\TextInput::make('transaction_id')
                            ->disabled(),
                        Forms\Components\Textarea::make('notes')
                            ->rows(4)
                            ->disabled()
                            ->columnSpanFull(),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->sortable(),
                Tables\Columns\TextColumn::make('user.username')
                    ->label('User')
                    ->searchable(),
                Tables\Columns\TextColumn::make('amount')
                    ->money('USD')
                    ->sortable(),
                Tables\Columns\TextColumn::make('payment_method')
                    ->badge(),
                Tables\Columns\BadgeColumn::make('status')
                    ->colors([
                        'warning' => WithdrawalRequest::STATUS_PENDING,
                        'info' => WithdrawalRequest::STATUS_PROCESSING,
                        'success' => WithdrawalRequest::STATUS_COMPLETED,
                        'danger' => WithdrawalRequest::STATUS_REJECTED,
                    ]),
                Tables\Columns\TextColumn::make('processedBy.username')
                    ->label('Processed By')
                    ->placeholder('-'),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        WithdrawalRequest::STATUS_PENDING => 'Pending',
                        WithdrawalRequest::STATUS_PROCESSING => 'Processing',
                        WithdrawalRequest::STATUS_COMPLETED => 'Completed',
                        WithdrawalRequest::STATUS_REJECTED => 'Rejected',
                    ]),
                Tables\Filters\SelectFilter::make('payment_method')
                    ->options([
                        'paypal' => 'PayPal',
                        'bank' => 'Bank',
                        'crypto' => 'Crypto',
                    ]),
            ])
            ->actions([
                Tables\Actions\Action::make('approve')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (WithdrawalRequest $record) => $record->status === WithdrawalRequest::STATUS_PENDING)
                    ->requiresConfirmation()
                    ->form([
                        Forms\Components\TextInput::make('transaction_id')
                            ->label('External Transaction ID')
                            ->maxLength(255),
                    ])
                    ->action(function (WithdrawalRequest $record, array $data): void {
                        DB::transaction(function () use ($record, $data): void {
                            $record->approve(auth()->user(), $data['transaction_id'] ?? null);

                            WalletTransaction::query()
                                ->where('reference_type', WithdrawalRequest::class)
                                ->where('reference_id', $record->id)
                                ->where('type', 'withdrawal_hold')
                                ->latest('id')
                                ->limit(1)
                                ->update([
                                    'type' => WalletTransaction::TYPE_WITHDRAWAL,
                                    'status' => WalletTransaction::STATUS_COMPLETED,
                                    'description' => "Withdrawal request #{$record->id} approved",
                                ]);
                        });
                    }),

                Tables\Actions\Action::make('reject')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (WithdrawalRequest $record) => $record->status === WithdrawalRequest::STATUS_PENDING)
                    ->requiresConfirmation()
                    ->form([
                        Forms\Components\Textarea::make('notes')
                            ->label('Rejection reason')
                            ->rows(3)
                            ->required(),
                    ])
                    ->action(function (WithdrawalRequest $record, array $data): void {
                        DB::transaction(function () use ($record, $data): void {
                            $record->reject(auth()->user(), $data['notes']);

                            WalletTransaction::query()
                                ->where('reference_type', WithdrawalRequest::class)
                                ->where('reference_id', $record->id)
                                ->where('type', 'withdrawal_hold')
                                ->latest('id')
                                ->limit(1)
                                ->update([
                                    'status' => WalletTransaction::STATUS_CANCELLED,
                                    'description' => "Withdrawal request #{$record->id} rejected",
                                ]);
                        });
                    }),

                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListWithdrawalRequests::route('/'),
            'edit' => Pages\EditWithdrawalRequest::route('/{record}/edit'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }
}

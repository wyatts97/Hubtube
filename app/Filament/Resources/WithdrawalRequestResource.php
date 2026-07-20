<?php

namespace App\Filament\Resources;

use App\Models\Setting;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Textarea;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use App\Filament\Resources\WithdrawalRequestResource\Pages\ListWithdrawalRequests;
use App\Filament\Resources\WithdrawalRequestResource\Pages\EditWithdrawalRequest;
use App\Filament\Resources\WithdrawalRequestResource\Pages;
use App\Models\WalletTransaction;
use App\Models\WithdrawalRequest;
use App\Services\EmailService;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;

class WithdrawalRequestResource extends Resource
{
    protected static ?string $model = WithdrawalRequest::class;

    protected static string | \BackedEnum | null $navigationIcon = 'phosphor-tray-arrow-up';

    protected static string | \UnitEnum | null $navigationGroup = 'Monetization';

    protected static ?int $navigationSort = 2;

    public static function shouldRegisterNavigation(): bool
    {
        return (bool) Setting::get('monetization_enabled', true);
    }

    // Pending count is surfaced as a topbar pill (see SystemStatusBar::getActionItems).

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Request Details')
                    ->schema([
                        Select::make('user_id')
                            ->relationship('user', 'username')
                            ->disabled(),
                        TextInput::make('amount')
                            ->prefix('$')
                            ->numeric()
                            ->disabled(),
                        TextInput::make('currency')
                            ->disabled(),
                        TextInput::make('payment_method')
                            ->disabled(),
                        KeyValue::make('payment_details')
                            ->disabled()
                            ->columnSpanFull(),
                    ])->columns(2),

                Section::make('Processing')
                    ->schema([
                        Select::make('status')
                            ->options([
                                WithdrawalRequest::STATUS_PENDING => 'Pending',
                                WithdrawalRequest::STATUS_PROCESSING => 'Processing',
                                WithdrawalRequest::STATUS_COMPLETED => 'Completed',
                                WithdrawalRequest::STATUS_REJECTED => 'Rejected',
                            ])
                            ->disabled(),
                        Select::make('processed_by')
                            ->relationship('processedBy', 'username')
                            ->disabled(),
                        DateTimePicker::make('processed_at')
                            ->disabled(),
                        TextInput::make('transaction_id')
                            ->disabled(),
                        Textarea::make('notes')
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
                TextColumn::make('id')
                    ->sortable(),
                TextColumn::make('user.username')
                    ->label('User')
                    ->searchable(),

                TextColumn::make('amount')
                    ->money('USD')
                    ->sortable(),
                TextColumn::make('payment_method')
                    ->badge(),

                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        WithdrawalRequest::STATUS_PENDING => 'warning',
                        WithdrawalRequest::STATUS_PROCESSING => 'info',
                        WithdrawalRequest::STATUS_COMPLETED => 'success',
                        WithdrawalRequest::STATUS_REJECTED => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('processedBy.username')
                    ->label('Processed By')
                    ->placeholder('-'),

                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        WithdrawalRequest::STATUS_PENDING => 'Pending',
                        WithdrawalRequest::STATUS_PROCESSING => 'Processing',
                        WithdrawalRequest::STATUS_COMPLETED => 'Completed',
                        WithdrawalRequest::STATUS_REJECTED => 'Rejected',
                    ]),
                SelectFilter::make('payment_method')
                    ->options([
                        'paypal' => 'PayPal',
                        'bank' => 'Bank',
                        'crypto' => 'Crypto',
                    ]),
            ])
            ->recordActions([
                Action::make('approve')
                    ->icon('phosphor-check-circle')
                    ->color('success')
                    ->visible(fn (WithdrawalRequest $record) => $record->status === WithdrawalRequest::STATUS_PENDING)
                    ->requiresConfirmation()
                    ->schema([
                        TextInput::make('transaction_id')
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

                        $record->loadMissing('user');
                        if ($record->user) {
                            EmailService::sendToUser('withdrawal-approved', $record->user->email, [
                                'username' => $record->user->username,
                                'amount' => '$' . number_format($record->amount, 2),
                            ]);
                        }
                    }),

                Action::make('reject')
                    ->icon('phosphor-x-circle')
                    ->color('danger')
                    ->visible(fn (WithdrawalRequest $record) => $record->status === WithdrawalRequest::STATUS_PENDING)
                    ->requiresConfirmation()
                    ->schema([
                        Textarea::make('notes')
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

                        $record->loadMissing('user');
                        if ($record->user) {
                            EmailService::sendToUser('withdrawal-rejected', $record->user->email, [
                                'username' => $record->user->username,
                                'amount' => '$' . number_format($record->amount, 2),
                                'rejection_reason' => $data['notes'] ?? 'No reason provided.',
                            ]);
                        }
                    }),

                EditAction::make(),
            ])
            ->toolbarActions([])
            ->striped();
    }

    public static function getPages(): array
    {
        return [
            'index' => ListWithdrawalRequests::route('/'),
            'edit' => EditWithdrawalRequest::route('/{record}/edit'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }
}
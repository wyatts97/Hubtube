<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CCBillSubscriptionResource\Pages\ListCCBillSubscriptions;
use App\Models\CCBillSubscription;
use App\Models\Setting;
use Filament\Actions\ViewAction;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class CCBillSubscriptionResource extends Resource
{
    protected static ?string $model = CCBillSubscription::class;
    protected static string | \BackedEnum | null $navigationIcon = 'phosphor-credit-card';
    protected static string | \UnitEnum | null $navigationGroup = 'Monetization';
    protected static ?string $navigationLabel = 'CCBill Subscriptions';
    protected static ?int $navigationSort = 4;

    public static function shouldRegisterNavigation(): bool
    {
        return (bool) Setting::get('monetization_enabled', true)
            && (bool) Setting::get('ccbill_enabled', false);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Subscription')
                ->schema([
                    TextInput::make('ccbill_subscription_id')->disabled(),
                    TextInput::make('status')->disabled(),
                    TextInput::make('subscription_type')->disabled(),
                    TextInput::make('current_period_end')->disabled(),
                ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('user.username')->label('User')->searchable(),
                TextColumn::make('ccbill_subscription_id')->label('CCBill Sub ID')->searchable()->copyable(),
                TextColumn::make('plan.name')->label('Plan'),
                TextColumn::make('subscription_type')->badge(),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'active' => 'success',
                        'cancelled' => 'warning',
                        'past_due' => 'warning',
                        'expired' => 'gray',
                        'refunded', 'chargeback' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('current_period_end')->dateTime()->sortable(),
                TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->options([
                    'active' => 'Active',
                    'cancelled' => 'Cancelled',
                    'past_due' => 'Past Due',
                    'expired' => 'Expired',
                    'refunded' => 'Refunded',
                    'chargeback' => 'Chargeback',
                ]),
            ])
            ->recordActions([
                ViewAction::make(),
            ])
            ->toolbarActions([])
            ->defaultSort('created_at', 'desc')
            ->striped();
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCCBillSubscriptions::route('/'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }
}

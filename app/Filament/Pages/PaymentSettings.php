<?php

namespace App\Filament\Pages;

use Filament\Actions\Action;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use App\Models\Plan;
use App\Models\Setting;
use App\Services\AdminLogger;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;


class PaymentSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string | \BackedEnum | null $navigationIcon = 'phosphor-credit-card';
    protected static ?string $navigationLabel = 'Payment Gateways';
    protected static string | \UnitEnum | null $navigationGroup = 'Monetization';
    protected static ?int $navigationSort = 3;
    protected string $view = 'filament.pages.site-settings';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            // Master toggle
            'monetization_enabled' => Setting::get('monetization_enabled', true),
            // Stripe
            'stripe_enabled' => Setting::get('stripe_enabled', false),
            'stripe_key' => Setting::get('stripe_key', ''),
            'stripe_secret' => Setting::get('stripe_secret', ''),
            'stripe_webhook_secret' => Setting::get('stripe_webhook_secret', ''),
            // PayPal
            'paypal_enabled' => Setting::get('paypal_enabled', false),
            'paypal_client_id' => Setting::get('paypal_client_id', ''),
            'paypal_secret' => Setting::get('paypal_secret', ''),
            'paypal_sandbox' => Setting::get('paypal_sandbox', true),
            // CCBill
            'ccbill_enabled' => Setting::get('ccbill_enabled', false),
            'ccbill_account' => Setting::get('ccbill_account', ''),
            'ccbill_subaccount' => Setting::get('ccbill_subaccount', ''),
            'ccbill_flex_id' => Setting::get('ccbill_flex_id', ''),
            'ccbill_salt' => Setting::get('ccbill_salt', ''),
            // General
            'currency' => Setting::get('currency', 'USD'),
            'min_deposit' => Setting::get('min_deposit', 10),
            'min_withdrawal' => Setting::get('min_withdrawal', 50),
            'platform_fee_percent' => Setting::get('platform_fee_percent', 20),
            // Pro Plans
            'pro_enabled' => Setting::get('pro_enabled', true),
            'pro_monthly_price' => Setting::get('pro_monthly_price', 9.99),
            'pro_annual_discount_percent' => Setting::get('pro_annual_discount_percent', 20),
            'pro_upload_limit_mb' => Setting::get('pro_upload_limit_mb', 1024),
            'pro_daily_upload_cap' => Setting::get('pro_daily_upload_cap', 50),
            'pro_ad_free' => Setting::get('pro_ad_free', true),
            'pro_badge_text' => Setting::get('pro_badge_text', 'PRO'),
        ]);
    }

    public function getHeaderActions(): array
    {
        return [
            Action::make('syncStripePrices')
                ->label('Sync Prices to Stripe')
                ->icon('phosphor-lightning')
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading('Sync Pro prices to Stripe')
                ->modalDescription('This will create or update Stripe Products and Prices for the monthly and annual Pro plans.')
                ->action(fn () => $this->syncStripePrices()),
        ];
    }

    public function syncStripePrices(): void
    {
        $monthlyPrice = (float) Setting::get('pro_monthly_price', 9.99);
        $discountPercent = (int) Setting::get('pro_annual_discount_percent', 20);
        $currency = strtolower((string) Setting::get('currency', 'USD'));
        $annualPrice = round($monthlyPrice * 12 * (1 - $discountPercent / 100), 2);

        try {
            $stripe = auth()->user()->stripe();

            $product = $stripe->products->create([
                'name' => 'Pro Membership',
            ]);

            $monthly = $stripe->prices->create([
                'product' => $product->id,
                'unit_amount' => (int) round($monthlyPrice * 100),
                'currency' => $currency,
                'recurring' => ['interval' => 'month'],
            ]);

            $annual = $stripe->prices->create([
                'product' => $product->id,
                'unit_amount' => (int) round($annualPrice * 100),
                'currency' => $currency,
                'recurring' => ['interval' => 'year'],
            ]);

            Plan::query()->updateOrCreate(
                ['slug' => 'pro-monthly'],
                [
                    'name' => 'Pro Monthly',
                    'amount_cents' => (int) round($monthlyPrice * 100),
                    'interval' => 'month',
                    'annual_discount_percent' => 0,
                    'stripe_price_id' => $monthly->id,
                    'is_active' => true,
                ]
            );

            Plan::query()->updateOrCreate(
                ['slug' => 'pro-annual'],
                [
                    'name' => 'Pro Annual',
                    'amount_cents' => (int) round($annualPrice * 100),
                    'interval' => 'year',
                    'annual_discount_percent' => $discountPercent,
                    'stripe_price_id' => $annual->id,
                    'is_active' => true,
                ]
            );

            Notification::make()
                ->title('Stripe prices synced successfully')
                ->success()
                ->send();
        } catch (\Throwable $e) {
            Notification::make()
                ->title('Stripe sync failed')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('Payment Settings')
                    ->tabs([
                        Tab::make('General')
                            ->schema([
                                Section::make('Monetization')
                                    ->schema([
                                        Toggle::make('monetization_enabled')
                                            ->label('Enable Monetization')
                                            ->helperText('When disabled, all billing, wallet, payment, and subscription features are hidden from the site. Only ad-based monetization remains active.'),
                                    ]),
                                Section::make('Currency & Fees')
                                    ->schema([
                                        TextInput::make('currency')
                                            ->label('Currency Code')
                                            ->placeholder('USD')
                                            ->maxLength(3),
                                        TextInput::make('min_deposit')
                                            ->label('Minimum Deposit')
                                            ->numeric()
                                            ->prefix('$'),
                                        TextInput::make('min_withdrawal')
                                            ->label('Minimum Withdrawal')
                                            ->numeric()
                                            ->prefix('$'),
                                        TextInput::make('platform_fee_percent')
                                            ->label('Platform Fee')
                                            ->numeric()
                                            ->suffix('%')
                                            ->helperText('Fee taken from video sales'),
                                    ])->columns(2),
                            ]),
                        Tab::make('Stripe')
                            ->schema([
                                Section::make('Stripe Configuration')
                                    ->schema([
                                        Toggle::make('stripe_enabled')
                                            ->label('Enable Stripe'),
                                        TextInput::make('stripe_key')
                                            ->label('Publishable Key')
                                            ->password()
                                            ->revealable(),
                                        TextInput::make('stripe_secret')
                                            ->label('Secret Key')
                                            ->password()
                                            ->revealable(),
                                        TextInput::make('stripe_webhook_secret')
                                            ->label('Webhook Secret')
                                            ->password()
                                            ->revealable(),
                                    ])->columns(2),
                            ]),
                        Tab::make('PayPal')
                            ->schema([
                                Section::make('PayPal Configuration')
                                    ->schema([
                                        Toggle::make('paypal_enabled')
                                            ->label('Enable PayPal'),
                                        Toggle::make('paypal_sandbox')
                                            ->label('Sandbox Mode'),
                                        TextInput::make('paypal_client_id')
                                            ->label('Client ID')
                                            ->password()
                                            ->revealable(),
                                        TextInput::make('paypal_secret')
                                            ->label('Secret')
                                            ->password()
                                            ->revealable(),
                                    ])->columns(2),
                            ]),
                        Tab::make('CCBill')
                            ->schema([
                                Section::make('CCBill Configuration')
                                    ->description('Adult-friendly payment processor')
                                    ->schema([
                                        Toggle::make('ccbill_enabled')
                                            ->label('Enable CCBill'),
                                        TextInput::make('ccbill_account')
                                            ->label('Account Number'),
                                        TextInput::make('ccbill_subaccount')
                                            ->label('Sub Account'),
                                        TextInput::make('ccbill_flex_id')
                                            ->label('FlexForm ID'),
                                        TextInput::make('ccbill_salt')
                                            ->label('Salt Key')
                                            ->password()
                                            ->revealable(),
                                    ])->columns(2),
                            ]),
                        Tab::make('Pro Plans')
                            ->schema([
                                Section::make('Pro Membership')
                                    ->description('Configure the Pro subscription tier.')
                                    ->schema([
                                        Toggle::make('pro_enabled')
                                            ->label('Enable Pro memberships')
                                            ->helperText('When disabled, the /pro page and all upgrade CTAs are hidden.'),
                                    ]),
                                Section::make('Pricing')
                                    ->schema([
                                        TextInput::make('pro_monthly_price')
                                            ->label('Monthly price')
                                            ->numeric()
                                            ->prefix('$')
                                            ->default(9.99)
                                            ->live()
                                            ->required(),
                                        TextInput::make('pro_annual_discount_percent')
                                            ->label('Annual discount')
                                            ->numeric()
                                            ->suffix('%')
                                            ->default(20)
                                            ->helperText('Annual price = monthly × 12 × (1 - discount/100)')
                                            ->live()
                                            ->required(),
                                        Text::make(function (callable $get) {
                                                $monthly = (float) ($get('pro_monthly_price') ?? 9.99);
                                                $discount = (int) ($get('pro_annual_discount_percent') ?? 20);
                                                $annual = round($monthly * 12 * (1 - $discount / 100), 2);
                                                $savings = round((($monthly * 12) - $annual), 2);
                                                return "Annual price: \${$annual} / year (saves \${$savings} vs monthly)";
                                            })
                                            ->extraAttributes(['class' => 'text-sm text-gray-500']),
                                    ])->columns(2),
                                Section::make('Perks')
                                    ->schema([
                                        TextInput::make('pro_upload_limit_mb')
                                            ->label('Pro upload limit (MB)')
                                            ->numeric()
                                            ->default(1024)
                                            ->helperText('Default 1024 MB = 1 GB'),
                                        TextInput::make('pro_daily_upload_cap')
                                            ->label('Pro daily upload cap')
                                            ->numeric()
                                            ->default(50)
                                            ->helperText('Number of videos a Pro user can upload per day.'),
                                        Toggle::make('pro_ad_free')
                                            ->label('Ad-free viewing for Pro users')
                                            ->default(true),
                                        TextInput::make('pro_badge_text')
                                            ->label('Pro badge text')
                                            ->default('PRO')
                                            ->maxLength(10),
                                    ])->columns(2),
                            ]),
                    ])->columnSpanFull(),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();

        foreach ($data as $key => $value) {
            $group = str_starts_with($key, 'pro_') ? 'pro' : 'payments';
            $type = match (true) {
                is_bool($value) => 'boolean',
                is_int($value) || is_float($value) => 'integer',
                default => 'string',
            };

            Setting::set($key, $value, $group, $type);
        }

        // Keep upload limits in sync with the existing SiteSettings naming.
        if (isset($data['pro_upload_limit_mb'])) {
            Setting::set('max_upload_size_pro', (int) $data['pro_upload_limit_mb'], 'site', 'integer');
        }
        if (isset($data['pro_daily_upload_cap'])) {
            Setting::set('max_daily_uploads_pro', (int) $data['pro_daily_upload_cap'], 'site', 'integer');
        }

        AdminLogger::settingsSaved('Payment', array_keys($data));

        Notification::make()
            ->title('Payment settings saved successfully')
            ->success()
            ->send();
    }
}

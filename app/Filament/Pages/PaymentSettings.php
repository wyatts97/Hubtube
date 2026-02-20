<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use App\Services\AdminLogger;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Tabs;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

use App\Filament\Concerns\HasCustomizableNavigation;

class PaymentSettings extends Page implements HasForms
{
    use HasCustomizableNavigation;
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-credit-card';
    protected static ?string $navigationLabel = 'Payment Gateways';
    protected static ?string $navigationGroup = 'Monetization';
    protected static ?int $navigationSort = 3;
    protected static string $view = 'filament.pages.site-settings';

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
            'gift_platform_cut' => Setting::get('gift_platform_cut', 20),
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Tabs::make('Payment Settings')
                    ->tabs([
                        Tabs\Tab::make('General')
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
                                        TextInput::make('gift_platform_cut')
                                            ->label('Gift Platform Cut')
                                            ->numeric()
                                            ->suffix('%')
                                            ->helperText('Fee taken from live stream gifts'),
                                    ])->columns(2),
                            ]),
                        Tabs\Tab::make('Stripe')
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
                        Tabs\Tab::make('PayPal')
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
                        Tabs\Tab::make('CCBill')
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
                    ])->columnSpanFull(),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();

        foreach ($data as $key => $value) {
            $type = match (true) {
                is_bool($value) => 'boolean',
                is_int($value) || is_float($value) => 'integer',
                default => 'string',
            };

            Setting::set($key, $value, 'payments', $type);
        }

        AdminLogger::settingsSaved('Payment', array_keys($data));

        Notification::make()
            ->title('Payment settings saved successfully')
            ->success()
            ->send();
    }
}

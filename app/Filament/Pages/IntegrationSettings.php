<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use Filament\Forms\Components\Actions;
use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Tabs;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Mail;

class IntegrationSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-puzzle-piece';
    protected static ?string $navigationLabel = 'Integrations';
    protected static ?string $navigationGroup = 'System';
    protected static ?int $navigationSort = 4;
    protected static string $view = 'filament.pages.site-settings';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            // Bunny Stream
            'bunny_stream_api_key' => Setting::getDecrypted('bunny_stream_api_key', ''),
            'bunny_stream_library_id' => Setting::get('bunny_stream_library_id', ''),
            'bunny_stream_cdn_host' => Setting::get('bunny_stream_cdn_host', ''),
            'bunny_stream_cdn_token_key' => Setting::getDecrypted('bunny_stream_cdn_token_key', ''),
            // Mail
            'mail_mailer' => Setting::get('mail_mailer', 'log'),
            'mail_host' => Setting::get('mail_host', ''),
            'mail_port' => Setting::get('mail_port', 587),
            'mail_username' => Setting::get('mail_username', ''),
            'mail_password' => Setting::getDecrypted('mail_password', ''),
            'mail_encryption' => Setting::get('mail_encryption', 'tls'),
            'mail_from_address' => Setting::get('mail_from_address', ''),
            'mail_from_name' => Setting::get('mail_from_name', ''),
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Tabs::make('Integration Settings')
                    ->tabs([
                        Tabs\Tab::make('Bunny Stream')
                            ->schema([
                                Section::make('Bunny Stream')
                                    ->description('Used for migrating embedded Bunny Stream videos to local/cloud storage.')
                                    ->schema([
                                        TextInput::make('bunny_stream_api_key')
                                            ->label('API Key')
                                            ->password()
                                            ->revealable(),
                                        TextInput::make('bunny_stream_library_id')
                                            ->label('Library ID'),
                                        TextInput::make('bunny_stream_cdn_host')
                                            ->label('CDN Host')
                                            ->placeholder('vz-xxxxxxxx-xxx.b-cdn.net'),
                                        TextInput::make('bunny_stream_cdn_token_key')
                                            ->label('CDN Token Key')
                                            ->password()
                                            ->revealable()
                                            ->helperText('For signed URL authentication. Leave empty if not using token auth.'),
                                    ])->columns(2),
                            ]),
                        Tabs\Tab::make('Email / SMTP')
                            ->schema([
                                Section::make('Mail Configuration')
                                    ->description('Configure outgoing email for notifications, password resets, etc.')
                                    ->schema([
                                        Select::make('mail_mailer')
                                            ->label('Mail Driver')
                                            ->options([
                                                'log' => 'Log (no emails sent)',
                                                'smtp' => 'SMTP',
                                                'sendmail' => 'Sendmail',
                                                'ses' => 'Amazon SES',
                                                'postmark' => 'Postmark',
                                                'resend' => 'Resend',
                                            ])
                                            ->reactive(),
                                        TextInput::make('mail_host')
                                            ->label('SMTP Host')
                                            ->placeholder('smtp.gmail.com')
                                            ->visible(fn ($get) => $get('mail_mailer') === 'smtp'),
                                        TextInput::make('mail_port')
                                            ->label('SMTP Port')
                                            ->numeric()
                                            ->placeholder('587')
                                            ->visible(fn ($get) => $get('mail_mailer') === 'smtp'),
                                        TextInput::make('mail_username')
                                            ->label('SMTP Username')
                                            ->visible(fn ($get) => $get('mail_mailer') === 'smtp'),
                                        TextInput::make('mail_password')
                                            ->label('SMTP Password')
                                            ->password()
                                            ->revealable()
                                            ->visible(fn ($get) => $get('mail_mailer') === 'smtp'),
                                        Select::make('mail_encryption')
                                            ->label('Encryption')
                                            ->options([
                                                '' => 'None',
                                                'tls' => 'TLS',
                                                'ssl' => 'SSL',
                                            ])
                                            ->visible(fn ($get) => $get('mail_mailer') === 'smtp'),
                                        TextInput::make('mail_from_address')
                                            ->label('From Address')
                                            ->email()
                                            ->placeholder('noreply@yourdomain.com'),
                                        TextInput::make('mail_from_name')
                                            ->label('From Name')
                                            ->placeholder('HubTube'),
                                        Actions::make([
                                            Action::make('sendTestEmail')
                                                ->label('Send Test Email')
                                                ->icon('heroicon-o-paper-airplane')
                                                ->color('gray')
                                                ->action(function () {
                                                    $this->sendTestEmail();
                                                }),
                                        ])->columnSpanFull(),
                                    ])->columns(2),
                            ]),
                    ])->columnSpanFull(),
            ])
            ->statePath('data');
    }

    /**
     * Keys that must be stored encrypted in the database.
     */
    protected const ENCRYPTED_KEYS = [
        'bunny_stream_api_key',
        'bunny_stream_cdn_token_key',
        'mail_password',
    ];

    public function save(): void
    {
        $data = $this->form->getState();

        foreach ($data as $key => $value) {
            if (in_array($key, self::ENCRYPTED_KEYS, true)) {
                Setting::setEncrypted($key, $value, 'integrations');
                continue;
            }

            $type = match (true) {
                is_bool($value) => 'boolean',
                is_int($value) => 'integer',
                default => 'string',
            };

            Setting::set($key, $value, 'integrations', $type);
        }

        // Apply mail config at runtime so it takes effect immediately
        if (!empty($data['mail_mailer']) && $data['mail_mailer'] !== 'log') {
            $this->applyMailConfig($data);
        }

        Notification::make()
            ->title('Integration settings saved successfully')
            ->success()
            ->send();
    }

    protected function applyMailConfig(array $data): void
    {
        config([
            'mail.default' => $data['mail_mailer'],
            'mail.mailers.smtp.host' => $data['mail_host'] ?? '',
            'mail.mailers.smtp.port' => $data['mail_port'] ?? 587,
            'mail.mailers.smtp.username' => $data['mail_username'] ?? '',
            'mail.mailers.smtp.password' => $data['mail_password'] ?? '',
            'mail.mailers.smtp.encryption' => $data['mail_encryption'] ?? 'tls',
            'mail.from.address' => $data['mail_from_address'] ?? '',
            'mail.from.name' => $data['mail_from_name'] ?? config('app.name'),
        ]);
    }

    public function sendTestEmail(): void
    {
        // Save first so latest config is applied
        $this->save();

        $to = auth()->user()->email;

        try {
            Mail::raw(
                'This is a test email from ' . config('app.name') . '. If you received this, your mail configuration is working correctly.',
                function ($message) use ($to) {
                    $message->to($to)
                        ->subject(config('app.name') . ' — SMTP Test Email');
                }
            );

            Notification::make()
                ->title('Test email sent!')
                ->body("Check your inbox at {$to}")
                ->success()
                ->send();
        } catch (\Throwable $e) {
            Notification::make()
                ->title('Email sending failed')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }
}

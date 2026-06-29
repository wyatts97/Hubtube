<?php

namespace App\Filament\Pages;

use Filament\Schemas\Schema;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Actions;
use Filament\Actions\Action;
use Illuminate\Mail\MailManager;
use Throwable;
use App\Models\Setting;
use FinityLabs\FinMail\Mail\TemplateMail as FinMailTemplateMail;
use FinityLabs\FinMail\Settings\GeneralSettings;
use App\Models\User;
use App\Services\AdminLogger;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Mail;


class IntegrationSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string | \BackedEnum | null $navigationIcon = 'phosphor-puzzle-piece';
    protected static ?string $navigationLabel = 'Services & Email';
    protected static string | \UnitEnum | null $navigationGroup = 'Integrations';
    protected static ?int $navigationSort = 1;
    protected string $view = 'filament.pages.integration-settings';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            // Mail SMTP
            'mail_mailer' => Setting::get('mail_mailer', 'log'),
            'mail_host' => Setting::get('mail_host', ''),
            'mail_port' => Setting::get('mail_port', '587'),
            'mail_username' => Setting::get('mail_username', ''),
            'mail_password' => Setting::getDecrypted('mail_password', ''),
            'mail_encryption' => Setting::get('mail_encryption', 'tls'),
            'mail_from_address' => Setting::get('mail_from_address', ''),
            'mail_from_name' => Setting::get('mail_from_name', ''),
            'mail_verify_peer' => (bool) filter_var(Setting::get('mail_verify_peer', true), FILTER_VALIDATE_BOOLEAN),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('Integration Settings')
                    ->tabs([
                        Tab::make('Email / SMTP')
                            ->icon('phosphor-envelope')
                            ->schema([
                                Section::make('Mail Server Configuration')
                                    ->description('Works with any SMTP server: Gmail, Mailgun, SendGrid, BillionMail (self-hosted), or any other provider. Set the host and port to match your mail server.')
                                    ->schema([
                                        Select::make('mail_mailer')
                                            ->label('Mail Driver')
                                            ->options([
                                                'log' => 'Log (no emails sent — development only)',
                                                'smtp' => 'SMTP (external or self-hosted)',
                                                'sendmail' => 'Sendmail (local)',
                                                'ses' => 'Amazon SES',
                                                'postmark' => 'Postmark',
                                                'resend' => 'Resend',
                                            ])
                                            ->reactive()
                                            ->helperText('Select "SMTP" for most setups including BillionMail, Gmail, Mailgun, etc.'),
                                        TextInput::make('mail_host')
                                            ->label('SMTP Host')
                                            ->placeholder('127.0.0.1 or smtp.gmail.com')
                                            ->helperText('For self-hosted (BillionMail, Postal, etc.): use 127.0.0.1 or your server IP. For external: use the provider\'s SMTP host.')
                                            ->visible(fn ($get) => $get('mail_mailer') === 'smtp'),
                                        TextInput::make('mail_port')
                                            ->label('SMTP Port')
                                            ->placeholder('587')
                                            ->helperText('Common ports: 25 (unencrypted), 465 (SSL), 587 (TLS/STARTTLS), or custom (e.g. 8025, 8090)')
                                            ->visible(fn ($get) => $get('mail_mailer') === 'smtp'),
                                        TextInput::make('mail_username')
                                            ->label('SMTP Username')
                                            ->helperText('Some self-hosted servers don\'t require authentication — leave blank if not needed.')
                                            ->visible(fn ($get) => $get('mail_mailer') === 'smtp'),
                                        TextInput::make('mail_password')
                                            ->label('SMTP Password')
                                            ->password()
                                            ->revealable()
                                            ->visible(fn ($get) => $get('mail_mailer') === 'smtp'),
                                        Select::make('mail_encryption')
                                            ->label('Encryption')
                                            ->options([
                                                '' => 'None (port 25 or custom)',
                                                'tls' => 'TLS / STARTTLS (port 587)',
                                                'ssl' => 'SSL (port 465)',
                                            ])
                                            ->helperText('Use "None" for local self-hosted servers on non-standard ports. Use TLS for most external providers.')
                                            ->visible(fn ($get) => $get('mail_mailer') === 'smtp'),
                                        Toggle::make('mail_verify_peer')
                                            ->label('Verify SSL Certificate')
                                            ->helperText('Disable for self-hosted mail servers with self-signed certificates. Keep enabled for external providers.')
                                            ->visible(fn ($get) => $get('mail_mailer') === 'smtp'),
                                        TextInput::make('mail_from_address')
                                            ->label('From Address')
                                            ->email()
                                            ->placeholder('noreply@yourdomain.com'),
                                        TextInput::make('mail_from_name')
                                            ->label('From Name')
                                            ->placeholder(config('app.name')),
                                        Actions::make([
                                            Action::make('sendTestEmail')
                                                ->label('Send Test Email')
                                                ->icon('phosphor-paper-plane-right')
                                                ->color('gray')
                                                ->action(function () {
                                                    $this->sendTestEmail();
                                                }),
                                        ])->columnSpanFull(),
                                    ])->columns(2),
                            ]),

                        Tab::make('Email Templates')
                            ->icon('phosphor-envelope')
                            ->schema([
                                Section::make('Email Templates')
                                    ->description('Manage email templates, themes, and sent logs in the FinMail editor.')
                                    ->schema([
                                        Actions::make([
                                            Action::make('openEmailTemplates')
                                                ->label('Open Email Templates')
                                                ->icon('phosphor-envelope')
                                                ->color('primary')
                                                ->url(fn () => route('filament.admin.resources.email-templates.index'))
                                                ->openUrlInNewTab(false),
                                        ])->columnSpanFull(),
                                    ])->columns(1),
                            ]),
                    ])->columnSpanFull(),
            ])
            ->statePath('data');
    }

    protected const ENCRYPTED_KEYS = [
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

            // Convert booleans to string for Setting model
            if (is_bool($value)) {
                $value = $value ? 'true' : 'false';
            }

            $type = match (true) {
                $key === 'mail_verify_peer' => 'boolean',
                is_int($value) => 'integer',
                default => 'string',
            };

            $group = 'integrations';

            Setting::set($key, $value, $group, $type);
        }

        // Apply mail config at runtime so it takes effect immediately
        if (!empty($data['mail_mailer']) && $data['mail_mailer'] !== 'log') {
            $this->applyMailConfig($data);
        }

        // Keep FinMail's default sender in sync with the SMTP settings page.
        try {
            $finMailSettings = app(GeneralSettings::class);
            $finMailSettings->default_from_address = $data['mail_from_address'] ?? $finMailSettings->default_from_address;
            $finMailSettings->default_from_name = $data['mail_from_name'] ?? $finMailSettings->default_from_name;
            $finMailSettings->save();
        } catch (Throwable) {
            // If the settings table isn't ready yet, skip the sync.
        }

        AdminLogger::settingsSaved('Integration', array_keys($data));

        Notification::make()
            ->title('Settings saved successfully')
            ->success()
            ->send();
    }

    protected function applyMailConfig(array $data): void
    {
        config([
            'mail.default' => $data['mail_mailer'],
            'mail.mailers.smtp.host' => $data['mail_host'] ?? '',
            'mail.mailers.smtp.port' => (int) ($data['mail_port'] ?? 587),
            'mail.mailers.smtp.username' => $data['mail_username'] ?? '',
            'mail.mailers.smtp.password' => $data['mail_password'] ?? '',
            'mail.mailers.smtp.encryption' => $data['mail_encryption'] ?? 'tls',
            'mail.from.address' => $data['mail_from_address'] ?? '',
            'mail.from.name' => $data['mail_from_name'] ?? config('app.name'),
        ]);

        // SSL peer verification toggle (important for self-hosted mail servers).
        // Must be the top-level `verify_peer` config key — Laravel 11 forwards it to
        // Symfony's EsmtpTransportFactory as a DSN option. The nested `stream.ssl` key
        // is never read. Pass a real boolean (empty string = "verify on" in Symfony).
        config(['mail.mailers.smtp.verify_peer' => (bool) ($data['mail_verify_peer'] ?? true)]);

        // Purge the cached mailer instances so Laravel re-creates the transport
        // with the updated config instead of reusing the boot-time .env transport.
        app()->forgetInstance('mail.manager');
        app()->forgetInstance('mailer');
        app(MailManager::class)->forgetMailers();
    }

    public function sendTestEmail(): void
    {
        $this->save();

        /** @var User $user */
        $user = auth()->user();

        try {
            Mail::to($user->email)->sendNow(
                FinMailTemplateMail::make('welcome')->models(['user' => $user])
            );

            Notification::make()
                ->title('Test email sent!')
                ->body("Check your inbox at {$user->email}")
                ->success()
                ->send();
        } catch (Throwable $e) {
            Notification::make()
                ->title('Email sending failed')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

}

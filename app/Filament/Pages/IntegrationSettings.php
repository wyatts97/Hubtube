<?php

namespace App\Filament\Pages;

use App\Mail\TemplateMail;
use App\Models\EmailTemplate;
use App\Models\Setting;
use App\Services\AdminLogger;
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

use App\Filament\Concerns\HasCustomizableNavigation;

class IntegrationSettings extends Page implements HasForms
{
    use HasCustomizableNavigation;
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-puzzle-piece';
    protected static ?string $navigationLabel = 'Services & Email';
    protected static ?string $navigationGroup = 'Integrations';
    protected static ?int $navigationSort = 1;
    protected static string $view = 'filament.pages.integration-settings';

    public ?array $data = [];

    // Email templates state
    public array $emailTemplates = [];
    public array $editingTemplates = [];
    public ?int $expandedTemplate = null;
    public ?string $previewHtml = null;

    public function mount(): void
    {
        $this->form->fill([
            // Bunny Stream
            'bunny_stream_api_key' => Setting::getDecrypted('bunny_stream_api_key', ''),
            'bunny_stream_library_id' => Setting::get('bunny_stream_library_id', ''),
            'bunny_stream_cdn_host' => Setting::get('bunny_stream_cdn_host', ''),
            'bunny_stream_cdn_token_key' => Setting::getDecrypted('bunny_stream_cdn_token_key', ''),
            // Mail SMTP
            'mail_mailer' => Setting::get('mail_mailer', 'log'),
            'mail_host' => Setting::get('mail_host', ''),
            'mail_port' => Setting::get('mail_port', '587'),
            'mail_username' => Setting::get('mail_username', ''),
            'mail_password' => Setting::getDecrypted('mail_password', ''),
            'mail_encryption' => Setting::get('mail_encryption', 'tls'),
            'mail_from_address' => Setting::get('mail_from_address', ''),
            'mail_from_name' => Setting::get('mail_from_name', ''),
            'mail_verify_peer' => Setting::get('mail_verify_peer', 'true') === 'true',
            // Admin notifications
            'admin_notification_email' => Setting::get('admin_notification_email', ''),
            'admin_notify_contact-form-admin' => Setting::get('admin_notify_contact-form-admin', 'true') === 'true',
            'admin_notify_admin-new-user' => Setting::get('admin_notify_admin-new-user', 'false') === 'true',
            'admin_notify_admin-new-video' => Setting::get('admin_notify_admin-new-video', 'false') === 'true',
            'admin_notify_admin-new-report' => Setting::get('admin_notify_admin-new-report', 'true') === 'true',
            // User email notifications
            'email_notify_verify-email' => Setting::get('email_notify_verify-email', 'true') === 'true',
            'email_notify_reset-password' => Setting::get('email_notify_reset-password', 'true') === 'true',
            'email_notify_welcome' => Setting::get('email_notify_welcome', 'true') === 'true',
            'email_notify_video-published' => Setting::get('email_notify_video-published', 'true') === 'true',
            'email_notify_new-subscriber' => Setting::get('email_notify_new-subscriber', 'true') === 'true',
            'email_notify_video-approved' => Setting::get('email_notify_video-approved', 'true') === 'true',
            'email_notify_video-rejected' => Setting::get('email_notify_video-rejected', 'true') === 'true',
            'email_notify_withdrawal-approved' => Setting::get('email_notify_withdrawal-approved', 'true') === 'true',
            'email_notify_withdrawal-rejected' => Setting::get('email_notify_withdrawal-rejected', 'true') === 'true',
        ]);

        $this->loadEmailTemplates();
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Tabs::make('Integration Settings')
                    ->tabs([
                        Tabs\Tab::make('Bunny Stream')
                            ->icon('heroicon-o-cloud')
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
                            ->icon('heroicon-o-envelope')
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
                                                ->icon('heroicon-o-paper-airplane')
                                                ->color('gray')
                                                ->action(function () {
                                                    $this->sendTestEmail();
                                                }),
                                        ])->columnSpanFull(),
                                    ])->columns(2),
                            ]),
                        Tabs\Tab::make('Notification Settings')
                            ->icon('heroicon-o-bell')
                            ->schema([
                                Section::make('Admin Email Notifications')
                                    ->description('Configure which events trigger an email to the admin. Emails are only sent when the mail driver is configured above.')
                                    ->schema([
                                        TextInput::make('admin_notification_email')
                                            ->label('Admin Notification Email')
                                            ->email()
                                            ->placeholder('admin@yourdomain.com')
                                            ->helperText('Where admin notifications are sent. Falls back to the "From Address" if empty.')
                                            ->columnSpanFull(),
                                        Toggle::make('admin_notify_contact-form-admin')
                                            ->label('Contact Form Submissions')
                                            ->helperText('Email admin when someone submits the contact form'),
                                        Toggle::make('admin_notify_admin-new-user')
                                            ->label('New User Signups')
                                            ->helperText('Email admin when a new user registers'),
                                        Toggle::make('admin_notify_admin-new-video')
                                            ->label('New Video Uploads')
                                            ->helperText('Email admin when a new video is uploaded'),
                                        Toggle::make('admin_notify_admin-new-report')
                                            ->label('New Content Reports')
                                            ->helperText('Email admin when a user reports content (video, comment, user, etc.)'),
                                    ])->columns(2),
                                Section::make('User Email Notifications')
                                    ->description('Toggle which emails are sent to users. Disabling a type here prevents that email from being sent to any user.')
                                    ->schema([
                                        Toggle::make('email_notify_verify-email')
                                            ->label('Email Verification')
                                            ->helperText('Sent after registration to verify email address'),
                                        Toggle::make('email_notify_reset-password')
                                            ->label('Password Reset')
                                            ->helperText('Sent when user requests a password reset'),
                                        Toggle::make('email_notify_welcome')
                                            ->label('Welcome Email')
                                            ->helperText('Sent after email verification is complete'),
                                        Toggle::make('email_notify_video-published')
                                            ->label('Video Published')
                                            ->helperText('Sent when a video finishes processing and goes live'),
                                        Toggle::make('email_notify_new-subscriber')
                                            ->label('New Subscriber')
                                            ->helperText('Sent to channel owner when they get a new subscriber'),
                                        Toggle::make('email_notify_video-approved')
                                            ->label('Video Approved')
                                            ->helperText('Sent when admin approves a video (moderation mode)'),
                                        Toggle::make('email_notify_video-rejected')
                                            ->label('Video Rejected')
                                            ->helperText('Sent when admin rejects a video'),
                                        Toggle::make('email_notify_withdrawal-approved')
                                            ->label('Withdrawal Approved')
                                            ->helperText('Sent when admin approves a withdrawal request'),
                                        Toggle::make('email_notify_withdrawal-rejected')
                                            ->label('Withdrawal Rejected')
                                            ->helperText('Sent when admin rejects a withdrawal request'),
                                    ])->columns(2),
                            ]),
                    ])->columnSpanFull(),
            ])
            ->statePath('data');
    }

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

            // Convert booleans to string for Setting model
            if (is_bool($value)) {
                $value = $value ? 'true' : 'false';
            }

            $type = match (true) {
                str_starts_with($key, 'email_notify_') || str_starts_with($key, 'admin_notify_') || $key === 'mail_verify_peer' => 'boolean',
                is_int($value) => 'integer',
                default => 'string',
            };

            $group = match (true) {
                str_starts_with($key, 'email_notify_') || str_starts_with($key, 'admin_notify_') || $key === 'admin_notification_email' => 'notifications',
                default => 'integrations',
            };

            Setting::set($key, $value, $group, $type);
        }

        // Apply mail config at runtime so it takes effect immediately
        if (!empty($data['mail_mailer']) && $data['mail_mailer'] !== 'log') {
            $this->applyMailConfig($data);
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

        // SSL peer verification toggle (important for self-hosted mail servers)
        $verifyPeer = $data['mail_verify_peer'] ?? true;
        if (!$verifyPeer) {
            config([
                'mail.mailers.smtp.stream' => [
                    'ssl' => [
                        'allow_self_signed' => true,
                        'verify_peer' => false,
                        'verify_peer_name' => false,
                    ],
                ],
            ]);
        }

        // Purge the cached mailer instances so Laravel re-creates the transport
        // with the updated config instead of reusing the boot-time .env transport.
        app()->forgetInstance('mail.manager');
        app()->forgetInstance('mailer');
        app(\Illuminate\Mail\MailManager::class)->forgetMailers();
    }

    public function sendTestEmail(): void
    {
        $this->save();

        $to = auth()->user()->email;

        try {
            Mail::to($to)->sendNow(new TemplateMail('welcome', [
                'username' => auth()->user()->username ?? 'Admin',
                'login_url' => url('/admin'),
            ]));

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

    // -- Email Templates Management ------------------------------------------

    public function loadEmailTemplates(): void
    {
        $templates = EmailTemplate::orderBy('id')->get();
        $this->emailTemplates = $templates->toArray();
        $this->editingTemplates = [];

        foreach ($templates as $t) {
            $this->editingTemplates[$t->id] = [
                'subject' => $t->subject,
                'body_html' => $t->body_html,
            ];
        }
    }

    public function toggleTemplate(int $id): void
    {
        $this->expandedTemplate = $this->expandedTemplate === $id ? null : $id;
    }

    public function toggleTemplateActive(int $id): void
    {
        $template = EmailTemplate::find($id);
        if ($template) {
            $template->update(['is_active' => !$template->is_active]);
            $this->loadEmailTemplates();

            Notification::make()
                ->title($template->is_active ? 'Template enabled' : 'Template disabled')
                ->success()
                ->send();
        }
    }

    public function saveTemplate(int $id): void
    {
        $data = $this->editingTemplates[$id] ?? null;
        if (!$data) {
            return;
        }

        $template = EmailTemplate::find($id);
        if ($template) {
            $template->update([
                'subject' => $data['subject'],
                'body_html' => $data['body_html'],
            ]);

            AdminLogger::settingsSaved('Email Template', [$template->slug]);

            $this->loadEmailTemplates();

            Notification::make()
                ->title("Template \"{$template->name}\" saved")
                ->success()
                ->send();
        }
    }

    public function previewTemplate(int $id): void
    {
        $data = $this->editingTemplates[$id] ?? null;
        if (!$data) {
            return;
        }

        $template = EmailTemplate::find($id);
        if (!$template) {
            return;
        }

        // Build sample data for preview
        $sampleData = [
            'username' => 'JohnDoe',
            'verify_url' => url('/verify-email/sample-token'),
            'reset_url' => url('/reset-password/sample-token'),
            'video_title' => 'My Awesome Video',
            'video_url' => url('/my-awesome-video'),
            'subscriber_name' => 'JaneSmith',
            'channel_url' => url('/channel/johndoe'),
            'login_url' => url('/login'),
            'sender_name' => 'Contact User',
            'sender_email' => 'contact@example.com',
            'subject' => 'Question about your site',
            'message' => 'Hi, I have a question about your platform. Can you help?',
            'amount' => '$50.00',
            'rejection_reason' => 'Content does not meet community guidelines.',
            'expiry_minutes' => '60',
            'site_name' => config('app.name'),
        ];

        // Temporarily set the subject/body from the editing state
        $tempTemplate = new EmailTemplate([
            'slug' => $template->slug,
            'subject' => $data['subject'],
            'body_html' => $data['body_html'],
            'is_active' => true,
        ]);

        $body = $tempTemplate->renderBody($sampleData);

        $this->previewHtml = view('emails.layout', [
            'body' => $body,
            'subject' => $tempTemplate->renderSubject($sampleData),
        ])->render();
    }

    public function sendTestTemplate(int $id): void
    {
        $this->saveTemplate($id);

        $template = EmailTemplate::find($id);
        if (!$template) {
            return;
        }

        $to = auth()->user()->email;

        $sampleData = [
            'username' => auth()->user()->username ?? 'Admin',
            'verify_url' => url('/verify-email/test'),
            'reset_url' => url('/reset-password/test'),
            'video_title' => 'Test Video Title',
            'video_url' => url('/test-video'),
            'subscriber_name' => 'TestSubscriber',
            'channel_url' => url('/channel/test'),
            'login_url' => url('/login'),
            'sender_name' => 'Test Contact',
            'sender_email' => 'test@example.com',
            'subject' => 'Test contact submission',
            'message' => 'This is a test message from the email template preview system.',
            'amount' => '$25.00',
            'rejection_reason' => 'This is a test rejection reason.',
            'expiry_minutes' => '60',
        ];

        try {
            Mail::to($to)->send(new TemplateMail($template->slug, $sampleData));

            Notification::make()
                ->title("Test email sent for \"{$template->name}\"")
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

    public function seedTemplates(): void
    {
        foreach (EmailTemplate::defaults() as $default) {
            EmailTemplate::updateOrCreate(
                ['slug' => $default['slug']],
                $default
            );
        }

        $this->loadEmailTemplates();

        Notification::make()
            ->title('Email templates reset to defaults')
            ->success()
            ->send();
    }

    public function getPlaceholders(string $slug): array
    {
        return EmailTemplate::PLACEHOLDERS[$slug] ?? [];
    }
}

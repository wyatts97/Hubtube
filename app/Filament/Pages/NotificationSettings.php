<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use App\Services\AdminLogger;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

use App\Filament\Concerns\HasCustomizableNavigation;

class NotificationSettings extends Page implements HasForms
{
    use HasCustomizableNavigation;
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-bell';
    protected static ?string $navigationLabel = 'Notifications';
    protected static ?string $navigationGroup = 'Users & Messages';
    protected static ?int $navigationSort = 3;
    protected static string $view = 'filament.pages.notification-settings';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
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
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Admin Email Notifications')
                    ->description('Configure which events trigger an email to the admin. Emails are only sent when the mail driver is configured in Services & Email settings.')
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
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();

        foreach ($data as $key => $value) {
            if (is_bool($value)) {
                $value = $value ? 'true' : 'false';
            }

            $type = match (true) {
                str_starts_with($key, 'email_notify_') || str_starts_with($key, 'admin_notify_') => 'boolean',
                default => 'string',
            };

            Setting::set($key, $value, 'notifications', $type);
        }

        AdminLogger::settingsSaved('Notification', array_keys($data));

        Notification::make()
            ->title('Notification settings saved')
            ->success()
            ->send();
    }
}

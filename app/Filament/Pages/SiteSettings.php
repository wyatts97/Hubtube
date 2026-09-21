<?php

namespace App\Filament\Pages;

use App\Filament\Clusters\Settings as SettingsCluster;
use App\Filament\Concerns\RequiresSuperAdmin;
use App\Models\Setting;
use App\Services\AdminLogger;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;

class SiteSettings extends Page implements HasForms
{
    use InteractsWithForms;
    use RequiresSuperAdmin;

    protected static string|\BackedEnum|null $navigationIcon = 'phosphor-gear';

    protected static ?string $navigationLabel = 'Site Settings';

    protected static ?string $cluster = SettingsCluster::class;

    protected static ?int $navigationSort = 1;

    protected string $view = 'filament.pages.site-settings';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'maintenance_mode' => Setting::get('maintenance_mode', false),
            'maintenance_message' => Setting::get('maintenance_message', ''),
            'registration_enabled' => Setting::get('registration_enabled', true),
            'block_disposable_emails' => Setting::get('block_disposable_emails', true),
            'blocked_email_domains' => Setting::get('blocked_email_domains', []),
            'blocked_ips' => Setting::get('blocked_ips', []),
            // Must match EnsureEmailIsVerified's default, or an unsaved install
            // shows the toggle on while verification is not actually enforced.
            'email_verification_required' => Setting::get('email_verification_required', false),
            'admin_require_2fa' => Setting::get('admin_require_2fa', false),
            'age_verification_required' => Setting::get('age_verification_required', true),
            'private_profiles_enabled' => Setting::get('private_profiles_enabled', false),
            'channel_social_links_enabled' => Setting::get('channel_social_links_enabled', true),
            'embed_enabled' => Setting::get('embed_enabled', true),
            'minimum_age' => Setting::get('minimum_age', 18),
            'allow_unlisted_uploads' => Setting::get('allow_unlisted_uploads', false),
            'allow_private_uploads' => Setting::get('allow_private_uploads', false),
            'max_upload_size_free' => Setting::get('max_upload_size_free', 500),
            'max_upload_size_pro' => Setting::get('max_upload_size_pro', 5000),
            'max_daily_uploads_free' => Setting::get('max_daily_uploads_free', 5),
            'max_daily_uploads_pro' => Setting::get('max_daily_uploads_pro', 50),
            'video_auto_approve' => Setting::get('video_auto_approve', false),
            'video_auto_approve_usernames' => Setting::get('video_auto_approve_usernames', []),
            'comments_enabled' => Setting::get('comments_enabled', true),
            'comments_require_approval' => Setting::get('comments_require_approval', false),
            'comment_blocked_words' => Setting::get('comment_blocked_words', []),
            'comment_max_links' => Setting::get('comment_max_links', 2),
            'google_analytics_id' => Setting::get('google_analytics_id', ''),
            'custom_head_scripts' => Setting::get('custom_head_scripts', ''),
            'custom_footer_scripts' => Setting::get('custom_footer_scripts', ''),
            'infinite_scroll_enabled' => Setting::get('infinite_scroll_enabled', false),
            'videos_per_page' => Setting::get('videos_per_page', 24),
            'site_timezone' => Setting::get('site_timezone', config('app.timezone')),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('Settings')
                    ->tabs([
                        Tab::make('General')
                            ->schema([
                                Section::make('Site Status')
                                    ->schema([
                                        Toggle::make('maintenance_mode')
                                            ->label('Maintenance Mode')
                                            ->helperText('Only admins can access the site.'),
                                        Textarea::make('maintenance_message')
                                            ->label('Maintenance Message')
                                            ->helperText('Message shown to visitors during maintenance')
                                            ->placeholder('We are currently undergoing maintenance. Please check back soon.')
                                            ->rows(2)
                                            ->visible(fn ($get) => $get('maintenance_mode')),
                                    ]),
                                Section::make('Video Display')
                                    ->schema([
                                        Toggle::make('infinite_scroll_enabled')
                                            ->label('Enable Infinite Scroll')
                                            ->helperText('Load more videos on scroll instead of using page numbers.'),
                                        TextInput::make('videos_per_page')
                                            ->label('Videos Per Page/Load')
                                            ->numeric()
                                            ->minValue(6)
                                            ->maxValue(48)
                                            ->default(24)
                                            ->helperText('Number of videos to show per page or load'),
                                    ])->columns(2),
                                Section::make('Localization')
                                    ->schema([
                                        Select::make('site_timezone')
                                            ->label('Site Timezone')
                                            ->helperText('Used for all admin panel timestamps.')
                                            ->options(array_combine(\DateTimeZone::listIdentifiers(), \DateTimeZone::listIdentifiers()))
                                            ->searchable()
                                            ->native(false)
                                            ->default(fn () => Setting::get('site_timezone', config('app.timezone')))
                                            ->required(),
                                    ])->columnSpanFull(),
                            ]),
                        Tab::make('Users')
                            ->schema([
                                Section::make('Registration')
                                    ->schema([
                                        Toggle::make('registration_enabled')
                                            ->label('Allow Registration')
                                            ->helperText('Off blocks new sign-ups, including social sign-in. Existing users can still sign in.'),
                                        Toggle::make('email_verification_required')
                                            ->label('Require Email Verification'),
                                        Toggle::make('admin_require_2fa')
                                            ->label('Require 2FA for Admins')
                                            ->helperText('Admins must set up two-factor authentication before using the panel.'),
                                        Toggle::make('age_verification_required')
                                            ->label('Require Age Verification'),
                                        TextInput::make('minimum_age')
                                            ->label('Minimum Age')
                                            ->numeric()
                                            ->minValue(13)
                                            ->maxValue(21),
                                    ])->columns(2),
                                Section::make('Blocklists')
                                    ->description('Applied when an account is created, including through social sign-in.')
                                    ->schema([
                                        Toggle::make('block_disposable_emails')
                                            ->label('Block Disposable Email Addresses')
                                            ->helperText('Blocks sign-ups from known throwaway email providers.'),
                                        TagsInput::make('blocked_email_domains')
                                            ->label('Blocked Email Domains')
                                            ->placeholder('example.com')
                                            ->helperText('Additional domains to refuse, beyond the bundled list.')
                                            ->columnSpanFull(),
                                        TagsInput::make('blocked_ips')
                                            ->label('Blocked IPs')
                                            ->placeholder('203.0.113.5 or 203.0.113.0/24')
                                            ->helperText('IPs or CIDR ranges, IPv4 or IPv6. They can browse but not register.')
                                            ->columnSpanFull(),
                                    ])->columns(2),

                                Section::make('Channels & Privacy')
                                    ->schema([
                                        Toggle::make('private_profiles_enabled')
                                            ->label('Allow Private Profiles')
                                            ->helperText('Users can hide their channel. Turning this off makes private profiles public again.'),
                                        Toggle::make('channel_social_links_enabled')
                                            ->label('Allow Outbound Channel Links')
                                            ->helperText('Off hides all creator social links without deleting them.'),
                                    ])->columns(2),
                            ]),
                        Tab::make('Videos')
                            ->schema([
                                Section::make('Upload Limits')
                                    ->schema([
                                        TextInput::make('max_upload_size_free')
                                            ->label('Max Upload Size (Free) MB')
                                            ->numeric()
                                            ->suffix('MB'),
                                        TextInput::make('max_upload_size_pro')
                                            ->label('Max Upload Size (Pro) MB')
                                            ->numeric()
                                            ->suffix('MB'),
                                        TextInput::make('max_daily_uploads_free')
                                            ->label('Max Daily Uploads (Free)')
                                            ->numeric(),
                                        TextInput::make('max_daily_uploads_pro')
                                            ->label('Max Daily Uploads (Pro)')
                                            ->numeric(),
                                    ])->columns(2),
                                Section::make('Embedding')
                                    ->schema([
                                        Toggle::make('embed_enabled')
                                            ->label('Allow Embedding on Other Sites')
                                            ->helperText('Enables iframe embeds and oEmbed. Private, draft and unapproved videos can’t be embedded.'),
                                    ]),

                                Section::make('Video Privacy')
                                    ->description('Uploads are public unless these are on. Admins can always pick any privacy.')
                                    ->schema([
                                        Toggle::make('allow_unlisted_uploads')
                                            ->label('Allow Unlisted Videos')
                                            ->helperText('Anyone with the link can watch; kept out of listings, search and sitemaps.'),
                                        Toggle::make('allow_private_uploads')
                                            ->label('Allow Private Videos')
                                            ->helperText('Only the uploader and admins can watch. Requires the "Private videos" nginx block in deployment/nginx/hubtube.conf.'),
                                    ])->columns(2),
                                Section::make('Moderation')
                                    ->schema([
                                        Toggle::make('video_auto_approve')
                                            ->label('Auto-Approve All Videos')
                                            ->helperText('Off: only the users below are auto-approved.')
                                            ->reactive(),
                                        TagsInput::make('video_auto_approve_usernames')
                                            ->label('Auto-Approve Users')
                                            ->helperText('Users whose videos skip moderation.')
                                            ->placeholder('Add a username...')
                                            ->visible(fn ($get) => ! $get('video_auto_approve')),
                                        Toggle::make('comments_enabled')
                                            ->label('Enable Comments'),
                                        Toggle::make('comments_require_approval')
                                            ->label('Comments Require Approval'),
                                        TagsInput::make('comment_blocked_words')
                                            ->label('Blocked Words in Comments')
                                            ->helperText('Comments containing these are held for approval. Words match whole words; phrases match anywhere.')
                                            ->placeholder('Add a word or phrase...')
                                            ->columnSpanFull(),
                                        TextInput::make('comment_max_links')
                                            ->label('Maximum Links Per Comment')
                                            ->numeric()
                                            ->minValue(0)
                                            ->default(2)
                                            ->helperText('Comments with more links than this are held for approval. 0 = no limit.'),
                                    ])->columns(2),
                            ]),
                        Tab::make('Analytics')
                            ->schema([
                                Section::make('Tracking')
                                    ->schema([
                                        TextInput::make('google_analytics_id')
                                            ->label('Google Analytics ID')
                                            ->placeholder('G-XXXXXXXXXX'),
                                        Textarea::make('custom_head_scripts')
                                            ->label('Custom Head Scripts')
                                            ->rows(5)
                                            ->helperText('Scripts to add before </head>'),
                                        Textarea::make('custom_footer_scripts')
                                            ->label('Custom Footer Scripts')
                                            ->rows(5)
                                            ->helperText('Scripts to add before </body>'),
                                    ]),
                            ]),
                    ])->columnSpanFull(),
            ])
            ->statePath('data');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('save')
                ->color('success')
                ->label('Save Settings')
                ->icon('phosphor-check')
                ->action('save'),
        ];
    }

    public function save(): void
    {
        $data = $this->form->getState();

        foreach ($data as $key => $value) {
            $type = match (true) {
                is_bool($value) => 'boolean',
                is_int($value) => 'integer',
                is_array($value) => 'array',
                default => 'string',
            };

            Setting::set($key, $value, 'general', $type);
        }

        AdminLogger::settingsSaved('Site', array_keys($data));

        Notification::make()
            ->title('Settings saved successfully')
            ->success()
            ->send();
    }
}

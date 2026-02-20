<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use App\Services\AdminLogger;
use Filament\Forms\Components\Actions;
use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Tabs;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

use App\Filament\Concerns\HasCustomizableNavigation;

class SocialNetworkSettings extends Page implements HasForms
{
    use HasCustomizableNavigation;
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-share';
    protected static ?string $navigationLabel = 'Social Networks';
    protected static ?string $navigationGroup = 'Integrations';
    protected static ?int $navigationSort = 2;
    protected static ?string $title = 'Social Networks';
    protected static ?string $slug = 'social-networks';
    protected static string $view = 'filament.pages.social-network-settings';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            // -- Social Login: Google --
            'social_login_google_enabled' => (bool) Setting::get('social_login_google_enabled', false),
            'social_login_google_client_id' => Setting::getDecrypted('social_login_google_client_id', ''),
            'social_login_google_client_secret' => Setting::getDecrypted('social_login_google_client_secret', ''),

            // -- Social Login: Twitter/X --
            'social_login_twitter_enabled' => (bool) Setting::get('social_login_twitter_enabled', false),
            'social_login_twitter_client_id' => Setting::getDecrypted('social_login_twitter_client_id', ''),
            'social_login_twitter_client_secret' => Setting::getDecrypted('social_login_twitter_client_secret', ''),

            // -- Social Login: Reddit --
            'social_login_reddit_enabled' => (bool) Setting::get('social_login_reddit_enabled', false),
            'social_login_reddit_client_id' => Setting::getDecrypted('social_login_reddit_client_id', ''),
            'social_login_reddit_client_secret' => Setting::getDecrypted('social_login_reddit_client_secret', ''),

            // -- Twitter Auto-Post: API Credentials --
            'twitter_api_bearer_token' => Setting::getDecrypted('twitter_api_bearer_token', ''),
            'twitter_api_consumer_key' => Setting::getDecrypted('twitter_api_consumer_key', ''),
            'twitter_api_consumer_secret' => Setting::getDecrypted('twitter_api_consumer_secret', ''),
            'twitter_api_access_token' => Setting::getDecrypted('twitter_api_access_token', ''),
            'twitter_api_access_token_secret' => Setting::getDecrypted('twitter_api_access_token_secret', ''),

            // -- Twitter Auto-Post: Settings --
            'twitter_auto_tweet_new_enabled' => (bool) Setting::get('twitter_auto_tweet_new_enabled', false),
            'twitter_auto_tweet_scheduled_enabled' => (bool) Setting::get('twitter_auto_tweet_scheduled_enabled', false),
            'twitter_tweet_interval_hours' => (int) Setting::get('twitter_tweet_interval_hours', 4),
            'twitter_min_video_age_days' => (int) Setting::get('twitter_min_video_age_days', 7),
            'twitter_no_retweet_within_days' => (int) Setting::get('twitter_no_retweet_within_days', 30),
            'twitter_tweet_template' => Setting::get('twitter_tweet_template', '{title} — Watch now: {url} #{category}'),
            'twitter_hashtags' => Setting::get('twitter_hashtags', ''),
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Tabs::make('Social Network Settings')
                    ->tabs([
                        Tabs\Tab::make('Social Login')
                            ->icon('heroicon-o-arrow-right-on-rectangle')
                            ->schema([
                                Placeholder::make('social_login_info')
                                    ->content('Enable OAuth2 social login for your users. Each provider requires a Client ID and Client Secret from the respective developer console.')
                                    ->columnSpanFull(),

                                Section::make('Google')
                                    ->description('Create credentials at console.cloud.google.com → APIs & Services → Credentials. Set the redirect URI to: ' . url('/auth/google/callback'))
                                    ->icon('heroicon-o-globe-alt')
                                    ->collapsible()
                                    ->schema([
                                        Toggle::make('social_login_google_enabled')
                                            ->label('Enable Google Login')
                                            ->reactive(),
                                        TextInput::make('social_login_google_client_id')
                                            ->label('Client ID')
                                            ->placeholder('xxxx.apps.googleusercontent.com')
                                            ->visible(fn ($get) => $get('social_login_google_enabled'))
                                            ->required(fn ($get) => $get('social_login_google_enabled')),
                                        TextInput::make('social_login_google_client_secret')
                                            ->label('Client Secret')
                                            ->password()
                                            ->revealable()
                                            ->visible(fn ($get) => $get('social_login_google_enabled'))
                                            ->required(fn ($get) => $get('social_login_google_enabled')),
                                    ])->columns(2),

                                Section::make('Twitter / X')
                                    ->description('Create an app at developer.x.com. Enable OAuth 2.0 with PKCE. Set the redirect URI to: ' . url('/auth/twitter/callback'))
                                    ->icon('heroicon-o-chat-bubble-left-right')
                                    ->collapsible()
                                    ->schema([
                                        Toggle::make('social_login_twitter_enabled')
                                            ->label('Enable Twitter/X Login')
                                            ->reactive(),
                                        TextInput::make('social_login_twitter_client_id')
                                            ->label('Client ID (OAuth 2.0)')
                                            ->visible(fn ($get) => $get('social_login_twitter_enabled'))
                                            ->required(fn ($get) => $get('social_login_twitter_enabled')),
                                        TextInput::make('social_login_twitter_client_secret')
                                            ->label('Client Secret (OAuth 2.0)')
                                            ->password()
                                            ->revealable()
                                            ->visible(fn ($get) => $get('social_login_twitter_enabled'))
                                            ->required(fn ($get) => $get('social_login_twitter_enabled')),
                                    ])->columns(2),

                                Section::make('Reddit')
                                    ->description('Create an app at reddit.com/prefs/apps (type: web app). Set the redirect URI to: ' . url('/auth/reddit/callback'))
                                    ->icon('heroicon-o-chat-bubble-bottom-center-text')
                                    ->collapsible()
                                    ->schema([
                                        Toggle::make('social_login_reddit_enabled')
                                            ->label('Enable Reddit Login')
                                            ->reactive(),
                                        TextInput::make('social_login_reddit_client_id')
                                            ->label('Client ID')
                                            ->visible(fn ($get) => $get('social_login_reddit_enabled'))
                                            ->required(fn ($get) => $get('social_login_reddit_enabled')),
                                        TextInput::make('social_login_reddit_client_secret')
                                            ->label('Client Secret')
                                            ->password()
                                            ->revealable()
                                            ->visible(fn ($get) => $get('social_login_reddit_enabled'))
                                            ->required(fn ($get) => $get('social_login_reddit_enabled')),
                                    ])->columns(2),
                            ]),

                        Tabs\Tab::make('Twitter Auto-Post')
                            ->icon('heroicon-o-megaphone')
                            ->schema([
                                Placeholder::make('twitter_api_info')
                                    ->content('Automatically tweet when a new video is published and/or schedule periodic tweets of older videos. Requires Twitter API v2 credentials with tweet write permissions (OAuth 1.0a User Context).')
                                    ->columnSpanFull(),

                                Section::make('API Credentials')
                                    ->description('From developer.x.com → Your App → Keys and Tokens. These are the OAuth 1.0a keys used for posting tweets on behalf of your account.')
                                    ->icon('heroicon-o-key')
                                    ->collapsible()
                                    ->schema([
                                        TextInput::make('twitter_api_consumer_key')
                                            ->label('API Key (Consumer Key)')
                                            ->password()
                                            ->revealable(),
                                        TextInput::make('twitter_api_consumer_secret')
                                            ->label('API Secret (Consumer Secret)')
                                            ->password()
                                            ->revealable(),
                                        TextInput::make('twitter_api_access_token')
                                            ->label('Access Token')
                                            ->password()
                                            ->revealable(),
                                        TextInput::make('twitter_api_access_token_secret')
                                            ->label('Access Token Secret')
                                            ->password()
                                            ->revealable(),
                                        TextInput::make('twitter_api_bearer_token')
                                            ->label('Bearer Token')
                                            ->password()
                                            ->revealable()
                                            ->helperText('Optional. Used for read-only API calls.'),
                                    ])->columns(2),

                                Section::make('Auto-Tweet Settings')
                                    ->icon('heroicon-o-cog-6-tooth')
                                    ->schema([
                                        Toggle::make('twitter_auto_tweet_new_enabled')
                                            ->label('Auto-tweet when a new video is published')
                                            ->helperText('Sends a tweet immediately when a video finishes processing and becomes published.'),
                                        Toggle::make('twitter_auto_tweet_scheduled_enabled')
                                            ->label('Schedule periodic tweets of older videos')
                                            ->helperText('Periodically tweets a random older video for ongoing engagement.')
                                            ->reactive(),
                                        TextInput::make('twitter_tweet_interval_hours')
                                            ->label('Tweet Interval (hours)')
                                            ->numeric()
                                            ->minValue(1)
                                            ->maxValue(168)
                                            ->default(4)
                                            ->helperText('Hours between scheduled older video tweets.')
                                            ->visible(fn ($get) => $get('twitter_auto_tweet_scheduled_enabled')),
                                        TextInput::make('twitter_min_video_age_days')
                                            ->label('Minimum Video Age (days)')
                                            ->numeric()
                                            ->minValue(1)
                                            ->maxValue(365)
                                            ->default(7)
                                            ->helperText('Only tweet videos older than this many days.')
                                            ->visible(fn ($get) => $get('twitter_auto_tweet_scheduled_enabled')),
                                        TextInput::make('twitter_no_retweet_within_days')
                                            ->label("Don't Re-tweet Within (days)")
                                            ->numeric()
                                            ->minValue(1)
                                            ->maxValue(365)
                                            ->default(30)
                                            ->helperText('Avoid tweeting the same video again within this period.')
                                            ->visible(fn ($get) => $get('twitter_auto_tweet_scheduled_enabled')),
                                    ])->columns(2),

                                Section::make('Tweet Template')
                                    ->icon('heroicon-o-document-text')
                                    ->schema([
                                        Textarea::make('twitter_tweet_template')
                                            ->label('Tweet Template')
                                            ->rows(3)
                                            ->placeholder('{title} — Watch now: {url} #{category}')
                                            ->helperText('Available placeholders: {title}, {url}, {channel}, {category}. URLs count as 23 chars (t.co). Max 280 chars total.'),
                                        TextInput::make('twitter_hashtags')
                                            ->label('Additional Hashtags')
                                            ->placeholder('HubTube, Videos, Trending')
                                            ->helperText('Comma-separated hashtags appended to every tweet (without #).'),
                                        Actions::make([
                                            Action::make('sendTestTweet')
                                                ->label('Send Test Tweet')
                                                ->icon('heroicon-o-paper-airplane')
                                                ->color('gray')
                                                ->requiresConfirmation()
                                                ->modalHeading('Send Test Tweet')
                                                ->modalDescription('This will post a test tweet to your connected Twitter account. Continue?')
                                                ->action(function () {
                                                    $this->sendTestTweet();
                                                }),
                                        ])->columnSpanFull(),
                                    ]),
                            ]),
                    ])->columnSpanFull(),
            ])
            ->statePath('data');
    }

    protected const ENCRYPTED_KEYS = [
        'social_login_google_client_id',
        'social_login_google_client_secret',
        'social_login_twitter_client_id',
        'social_login_twitter_client_secret',
        'social_login_reddit_client_id',
        'social_login_reddit_client_secret',
        'twitter_api_bearer_token',
        'twitter_api_consumer_key',
        'twitter_api_consumer_secret',
        'twitter_api_access_token',
        'twitter_api_access_token_secret',
    ];

    protected const BOOLEAN_KEYS = [
        'social_login_google_enabled',
        'social_login_twitter_enabled',
        'social_login_reddit_enabled',
        'twitter_auto_tweet_new_enabled',
        'twitter_auto_tweet_scheduled_enabled',
    ];

    protected const INTEGER_KEYS = [
        'twitter_tweet_interval_hours',
        'twitter_min_video_age_days',
        'twitter_no_retweet_within_days',
    ];

    public function save(): void
    {
        $data = $this->form->getState();

        foreach ($data as $key => $value) {
            if (in_array($key, self::ENCRYPTED_KEYS, true)) {
                Setting::setEncrypted($key, $value, 'social');
                continue;
            }

            if (in_array($key, self::BOOLEAN_KEYS, true)) {
                Setting::set($key, $value ? '1' : '0', 'social', 'boolean');
                continue;
            }

            if (in_array($key, self::INTEGER_KEYS, true)) {
                Setting::set($key, (string) (int) $value, 'social', 'integer');
                continue;
            }

            Setting::set($key, $value ?? '', 'social', 'string');
        }

        AdminLogger::settingsSaved('Social Networks', array_keys($data));

        Notification::make()
            ->title('Social network settings saved')
            ->success()
            ->send();
    }

    public function sendTestTweet(): void
    {
        // Save first so latest credentials are applied
        $this->save();

        try {
            $service = app(\App\Services\TwitterService::class);
            $result = $service->sendTestTweet();

            if ($result) {
                Notification::make()
                    ->title('Test tweet sent!')
                    ->body('Check your Twitter account to verify.')
                    ->success()
                    ->send();
            } else {
                Notification::make()
                    ->title('Tweet failed')
                    ->body('Could not send test tweet. Check your API credentials.')
                    ->danger()
                    ->send();
            }
        } catch (\Throwable $e) {
            Notification::make()
                ->title('Tweet failed')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }
}

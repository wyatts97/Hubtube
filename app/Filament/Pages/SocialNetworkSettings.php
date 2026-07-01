<?php

namespace App\Filament\Pages;

use Filament\Schemas\Schema;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Section;
use Filament\Actions\Action;
use App\Models\Setting;
use App\Services\AdminLogger;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;


class SocialNetworkSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string | \BackedEnum | null $navigationIcon = 'phosphor-share';
    protected static ?string $navigationLabel = 'Social Networks';
    protected static string | \UnitEnum | null $navigationGroup = 'Integrations';
    protected static ?int $navigationSort = 2;
    protected static ?string $title = 'Social Networks';
    protected static ?string $slug = 'social-networks';
    protected string $view = 'filament.pages.social-network-settings';

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
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('Social Network Settings')
                    ->tabs([
                        Tab::make('Social Login')
                            ->icon('phosphor-sign-in')
                            ->schema([
                                Placeholder::make('social_login_info')
                                    ->content('Enable OAuth2 social login for your users. Each provider requires a Client ID and Client Secret from the respective developer console.')
                                    ->columnSpanFull(),

                                Section::make('Google')
                                    ->description('Create credentials at console.cloud.google.com → APIs & Services → Credentials. Set the redirect URI to: ' . url('/auth/google/callback'))
                                    ->icon('phosphor-globe-hemisphere-west')
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
                                    ->icon('phosphor-chat-text')
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
                                    ->icon('phosphor-chat-circle-text')
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

                    ])->columnSpanFull(),
            ])
            ->statePath('data');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('save')
                ->label('Save Settings')
                ->icon('phosphor-check')
                ->action('save'),
        ];
    }

    protected const ENCRYPTED_KEYS = [
        'social_login_google_client_id',
        'social_login_google_client_secret',
        'social_login_twitter_client_id',
        'social_login_twitter_client_secret',
        'social_login_reddit_client_id',
        'social_login_reddit_client_secret',
    ];

    protected const BOOLEAN_KEYS = [
        'social_login_google_enabled',
        'social_login_twitter_enabled',
        'social_login_reddit_enabled',
    ];

    protected const INTEGER_KEYS = [
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

}

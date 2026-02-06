<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class PwaSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-device-phone-mobile';
    protected static ?string $navigationLabel = 'PWA & Push';
    protected static ?string $navigationGroup = 'Settings';
    protected static ?int $navigationSort = 8;
    protected static string $view = 'filament.pages.pwa-settings';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'pwa_enabled' => Setting::get('pwa_enabled', true),
            'pwa_name' => Setting::get('pwa_name', config('app.name', 'HubTube')),
            'pwa_short_name' => Setting::get('pwa_short_name', 'HubTube'),
            'pwa_description' => Setting::get('pwa_description', 'Video sharing platform'),
            'pwa_theme_color' => Setting::get('pwa_theme_color', '#ef4444'),
            'pwa_background_color' => Setting::get('pwa_background_color', '#0a0a0a'),
            'push_enabled' => Setting::get('push_enabled', false),
            'vapid_public_key' => Setting::get('vapid_public_key', ''),
            'vapid_private_key' => Setting::get('vapid_private_key', ''),
            'push_subject' => Setting::get('push_subject', ''),
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Progressive Web App (PWA)')
                    ->description('Configure PWA settings for installability on mobile devices')
                    ->schema([
                        Toggle::make('pwa_enabled')
                            ->label('Enable PWA')
                            ->helperText('Allow users to install the app on their device'),
                        TextInput::make('pwa_name')
                            ->label('App Name')
                            ->maxLength(100),
                        TextInput::make('pwa_short_name')
                            ->label('Short Name')
                            ->maxLength(30)
                            ->helperText('Shown on home screen'),
                        Textarea::make('pwa_description')
                            ->label('Description')
                            ->rows(2)
                            ->maxLength(300),
                        TextInput::make('pwa_theme_color')
                            ->label('Theme Color')
                            ->type('color'),
                        TextInput::make('pwa_background_color')
                            ->label('Background Color')
                            ->type('color'),
                    ])
                    ->columns(2),

                Section::make('Push Notifications')
                    ->description('Configure Web Push notifications using VAPID keys. Generate keys at https://web-push-codelab.glitch.me/')
                    ->schema([
                        Toggle::make('push_enabled')
                            ->label('Enable Push Notifications')
                            ->helperText('Allow sending push notifications to subscribed users'),
                        TextInput::make('vapid_public_key')
                            ->label('VAPID Public Key')
                            ->helperText('The public key from your VAPID key pair')
                            ->columnSpanFull(),
                        TextInput::make('vapid_private_key')
                            ->label('VAPID Private Key')
                            ->password()
                            ->helperText('The private key from your VAPID key pair — keep this secret')
                            ->columnSpanFull(),
                        TextInput::make('push_subject')
                            ->label('Subject (Contact)')
                            ->placeholder('mailto:admin@example.com')
                            ->helperText('A mailto: or https: URL for the push service to contact you'),
                    ])
                    ->columns(2),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();

        Setting::set('pwa_enabled', $data['pwa_enabled'] ?? true, 'pwa', 'boolean');
        Setting::set('pwa_name', $data['pwa_name'] ?? 'HubTube', 'pwa');
        Setting::set('pwa_short_name', $data['pwa_short_name'] ?? 'HubTube', 'pwa');
        Setting::set('pwa_description', $data['pwa_description'] ?? '', 'pwa');
        Setting::set('pwa_theme_color', $data['pwa_theme_color'] ?? '#ef4444', 'pwa');
        Setting::set('pwa_background_color', $data['pwa_background_color'] ?? '#0a0a0a', 'pwa');
        Setting::set('push_enabled', $data['push_enabled'] ?? false, 'push', 'boolean');
        Setting::set('vapid_public_key', $data['vapid_public_key'] ?? '', 'push');
        Setting::set('vapid_private_key', $data['vapid_private_key'] ?? '', 'push');
        Setting::set('push_subject', $data['push_subject'] ?? '', 'push');

        Notification::make()
            ->title('PWA & Push settings saved')
            ->success()
            ->send();
    }
}

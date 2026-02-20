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

class LiveStreamSettings extends Page implements HasForms
{
    use HasCustomizableNavigation;
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-video-camera';
    protected static ?string $navigationLabel = 'Live Streaming';
    protected static ?string $navigationGroup = 'System';
    protected static ?int $navigationSort = 3;
    protected static string $view = 'filament.pages.site-settings';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'live_streaming_enabled' => Setting::get('live_streaming_enabled', true),
            'agora_app_id' => Setting::get('agora_app_id', ''),
            'agora_app_certificate' => Setting::get('agora_app_certificate', ''),
            'agora_token_expiry' => Setting::get('agora_token_expiry', 86400),
            'max_stream_duration' => Setting::get('max_stream_duration', 480),
            'gifts_enabled' => Setting::get('gifts_enabled', true),
            'min_viewers_for_gifts' => Setting::get('min_viewers_for_gifts', 0),
            'stream_recording_enabled' => Setting::get('stream_recording_enabled', false),
            'require_verification_to_stream' => Setting::get('require_verification_to_stream', true),
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Live Streaming')
                    ->schema([
                        Toggle::make('live_streaming_enabled')
                            ->label('Enable Live Streaming'),
                        Toggle::make('require_verification_to_stream')
                            ->label('Require Verification to Stream')
                            ->helperText('Users must be verified to go live'),
                        TextInput::make('max_stream_duration')
                            ->label('Max Stream Duration')
                            ->numeric()
                            ->suffix('minutes'),
                        Toggle::make('stream_recording_enabled')
                            ->label('Enable Stream Recording')
                            ->helperText('Automatically save streams as videos'),
                    ])->columns(2),
                Section::make('Agora.io Configuration')
                    ->description('Real-time video streaming powered by Agora.io')
                    ->schema([
                        TextInput::make('agora_app_id')
                            ->label('App ID')
                            ->password()
                            ->revealable(),
                        TextInput::make('agora_app_certificate')
                            ->label('App Certificate')
                            ->password()
                            ->revealable(),
                        TextInput::make('agora_token_expiry')
                            ->label('Token Expiry')
                            ->numeric()
                            ->suffix('seconds')
                            ->helperText('How long tokens are valid (default: 86400 = 24 hours)'),
                    ])->columns(2),
                Section::make('Virtual Gifts')
                    ->schema([
                        Toggle::make('gifts_enabled')
                            ->label('Enable Virtual Gifts'),
                        TextInput::make('min_viewers_for_gifts')
                            ->label('Minimum Viewers for Gifts')
                            ->numeric()
                            ->helperText('Minimum viewers required before gifts can be sent'),
                    ])->columns(2),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();

        foreach ($data as $key => $value) {
            $type = match (true) {
                is_bool($value) => 'boolean',
                is_int($value) => 'integer',
                default => 'string',
            };

            Setting::set($key, $value, 'streaming', $type);
        }

        AdminLogger::settingsSaved('Live Streaming', array_keys($data));

        Notification::make()
            ->title('Live streaming settings saved successfully')
            ->success()
            ->send();
    }
}

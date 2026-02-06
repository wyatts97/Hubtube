<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Grid;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class ShortsAdSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-play-circle';
    protected static ?string $navigationLabel = 'Shorts Ads';
    protected static ?string $navigationGroup = 'Settings';
    protected static ?int $navigationSort = 4;
    protected static string $view = 'filament.pages.shorts-ad-settings';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'shorts_ads_enabled' => Setting::get('shorts_ads_enabled', false),
            'shorts_ad_frequency' => Setting::get('shorts_ad_frequency', 3),
            'shorts_ad_skip_delay' => Setting::get('shorts_ad_skip_delay', 5),
            'shorts_ad_code' => Setting::get('shorts_ad_code', ''),
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Shorts Ad Interstitials')
                    ->description('Configure ads that appear between shorts in the TikTok-style viewer. These are full-screen interstitial ads shown between short videos as users swipe.')
                    ->schema([
                        Toggle::make('shorts_ads_enabled')
                            ->label('Enable Shorts Ads')
                            ->helperText('Show interstitial ads between shorts in the vertical viewer'),

                        Grid::make(2)->schema([
                            TextInput::make('shorts_ad_frequency')
                                ->label('Ad Frequency')
                                ->helperText('Show an ad after every N shorts (e.g., 3 = ad after every 3rd short)')
                                ->numeric()
                                ->default(3)
                                ->minValue(1)
                                ->maxValue(20)
                                ->required(),

                            TextInput::make('shorts_ad_skip_delay')
                                ->label('Skip Delay (seconds)')
                                ->helperText('How many seconds the user must wait before they can skip the ad. Set to 0 for immediately skippable.')
                                ->numeric()
                                ->default(5)
                                ->minValue(0)
                                ->maxValue(30)
                                ->required(),
                        ]),

                        Textarea::make('shorts_ad_code')
                            ->label('Ad HTML Code')
                            ->helperText('Paste your ad network HTML code here. This will be displayed full-screen between shorts. Recommended: vertical/responsive ad units.')
                            ->rows(10)
                            ->columnSpanFull(),
                    ]),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();

        Setting::set('shorts_ads_enabled', $data['shorts_ads_enabled'] ?? false, 'shorts_ads', 'boolean');
        Setting::set('shorts_ad_frequency', (int) ($data['shorts_ad_frequency'] ?? 3), 'shorts_ads', 'integer');
        Setting::set('shorts_ad_skip_delay', (int) ($data['shorts_ad_skip_delay'] ?? 5), 'shorts_ads', 'integer');
        Setting::set('shorts_ad_code', $data['shorts_ad_code'] ?? '', 'shorts_ads');

        Notification::make()
            ->title('Shorts ad settings saved successfully')
            ->success()
            ->send();
    }
}

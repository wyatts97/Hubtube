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

class AdSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-currency-dollar';
    protected static ?string $navigationLabel = 'Ad Settings';
    protected static ?string $navigationGroup = 'Settings';
    protected static ?int $navigationSort = 3;
    protected static string $view = 'filament.pages.ad-settings';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            // Video Grid Ads (between video cards)
            'video_grid_ad_enabled' => Setting::get('video_grid_ad_enabled', false),
            'video_grid_ad_code' => Setting::get('video_grid_ad_code', ''),
            'video_grid_ad_frequency' => Setting::get('video_grid_ad_frequency', 8),
            
            // Video Page Sidebar Ad (above related videos)
            'video_sidebar_ad_enabled' => Setting::get('video_sidebar_ad_enabled', false),
            'video_sidebar_ad_code' => Setting::get('video_sidebar_ad_code', ''),

            // Shorts Ad Interstitials
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
                Section::make('Video Grid Ads')
                    ->description('Configure ads that appear between video cards on browsing pages (Homepage, Trending, etc.). Recommended size: 300x250')
                    ->schema([
                        Toggle::make('video_grid_ad_enabled')
                            ->label('Enable Video Grid Ads')
                            ->helperText('Show ads between video cards on video listing pages'),
                        
                        Grid::make(2)->schema([
                            TextInput::make('video_grid_ad_frequency')
                                ->label('Ad Frequency')
                                ->helperText('Show an ad after every X videos')
                                ->numeric()
                                ->default(8)
                                ->minValue(2)
                                ->maxValue(50),
                        ]),
                        
                        Textarea::make('video_grid_ad_code')
                            ->label('Ad HTML Code')
                            ->helperText('Paste your ad network HTML code here (e.g., Google AdSense, ExoClick, etc.). Recommended size: 300x250')
                            ->rows(8)
                            ->columnSpanFull(),
                    ]),
                
                Section::make('Video Page Sidebar Ad')
                    ->description('Configure the ad that appears above related videos on video watch pages. Recommended size: 300x300 or 300x250')
                    ->schema([
                        Toggle::make('video_sidebar_ad_enabled')
                            ->label('Enable Sidebar Ad')
                            ->helperText('Show ad above related videos on video pages'),
                        
                        Textarea::make('video_sidebar_ad_code')
                            ->label('Ad HTML Code')
                            ->helperText('Paste your ad network HTML code here. Supports various sizes: 300x250, 300x300, 300x500, 300x600')
                            ->rows(8)
                            ->columnSpanFull(),
                    ]),

                Section::make('Shorts Ad Interstitials')
                    ->description('Configure full-screen ads that appear between shorts in the vertical viewer. These are shown as users swipe through shorts.')
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
                                ->maxValue(20),

                            TextInput::make('shorts_ad_skip_delay')
                                ->label('Skip Delay (seconds)')
                                ->helperText('Seconds before user can skip. 0 = immediately skippable.')
                                ->numeric()
                                ->default(5)
                                ->minValue(0)
                                ->maxValue(30),
                        ]),

                        Textarea::make('shorts_ad_code')
                            ->label('Ad HTML Code')
                            ->helperText('Paste your ad network HTML code here. Displayed full-screen between shorts. Recommended: vertical/responsive ad units.')
                            ->rows(8)
                            ->columnSpanFull(),
                    ]),
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

            Setting::set($key, $value, 'ads', $type);
        }

        Notification::make()
            ->title('Ad settings saved successfully')
            ->success()
            ->send();
    }
}

<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use App\Services\AdminLogger;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Tabs;
use Filament\Forms\Components\Tabs\Tab;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Storage;

class AdSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-currency-dollar';
    protected static ?string $navigationLabel = 'Ad Settings';
    protected static ?string $navigationGroup = 'Appearance';
    protected static ?int $navigationSort = 2;
    protected static string $view = 'filament.pages.ad-settings';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'video_ad_pre_roll_enabled'      => Setting::get('video_ad_pre_roll_enabled', false),
            'video_ad_mid_roll_enabled'      => Setting::get('video_ad_mid_roll_enabled', false),
            'video_ad_post_roll_enabled'     => Setting::get('video_ad_post_roll_enabled', false),
            'video_ad_pre_roll_skip_after'   => Setting::get('video_ad_pre_roll_skip_after', 5),
            'video_ad_mid_roll_skip_after'   => Setting::get('video_ad_mid_roll_skip_after', 5),
            'video_ad_post_roll_skip_after'  => Setting::get('video_ad_post_roll_skip_after', 0),
            'video_ad_mid_roll_interval'     => Setting::get('video_ad_mid_roll_interval', 300),
            'video_ad_mid_roll_max_count'    => Setting::get('video_ad_mid_roll_max_count', 3),
            'video_ad_shuffle'               => Setting::get('video_ad_shuffle', true),
            'banner_above_player_enabled'       => Setting::get('banner_above_player_enabled', false),
            'banner_above_player_type'          => Setting::get('banner_above_player_type', 'html'),
            'banner_above_player_html'          => Setting::get('banner_above_player_html', ''),
            'banner_above_player_image'         => Setting::get('banner_above_player_image', ''),
            'banner_above_player_link'          => Setting::get('banner_above_player_link', ''),
            'banner_above_player_mobile_type'   => Setting::get('banner_above_player_mobile_type', 'html'),
            'banner_above_player_mobile_html'   => Setting::get('banner_above_player_mobile_html', ''),
            'banner_above_player_mobile_image'  => Setting::get('banner_above_player_mobile_image', ''),
            'banner_above_player_mobile_link'   => Setting::get('banner_above_player_mobile_link', ''),
            'banner_below_player_enabled'       => Setting::get('banner_below_player_enabled', false),
            'banner_below_player_type'          => Setting::get('banner_below_player_type', 'html'),
            'banner_below_player_html'          => Setting::get('banner_below_player_html', ''),
            'banner_below_player_image'         => Setting::get('banner_below_player_image', ''),
            'banner_below_player_link'          => Setting::get('banner_below_player_link', ''),
            'banner_below_player_mobile_type'   => Setting::get('banner_below_player_mobile_type', 'html'),
            'banner_below_player_mobile_html'   => Setting::get('banner_below_player_mobile_html', ''),
            'banner_below_player_mobile_image'  => Setting::get('banner_below_player_mobile_image', ''),
            'banner_below_player_mobile_link'   => Setting::get('banner_below_player_mobile_link', ''),
            'browse_banner_ad_enabled'          => Setting::get('browse_banner_ad_enabled', false),
            'browse_banner_ad_type'             => Setting::get('browse_banner_ad_type', 'html'),
            'browse_banner_ad_code'             => Setting::get('browse_banner_ad_code', ''),
            'browse_banner_ad_image'            => Setting::get('browse_banner_ad_image', ''),
            'browse_banner_ad_link'             => Setting::get('browse_banner_ad_link', ''),
            'browse_banner_ad_mobile_type'      => Setting::get('browse_banner_ad_mobile_type', 'html'),
            'browse_banner_ad_mobile_code'      => Setting::get('browse_banner_ad_mobile_code', ''),
            'browse_banner_ad_mobile_image'     => Setting::get('browse_banner_ad_mobile_image', ''),
            'browse_banner_ad_mobile_link'      => Setting::get('browse_banner_ad_mobile_link', ''),
            'search_banner_ad_enabled'          => Setting::get('search_banner_ad_enabled', false),
            'search_banner_ad_type'             => Setting::get('search_banner_ad_type', 'html'),
            'search_banner_ad_code'             => Setting::get('search_banner_ad_code', ''),
            'search_banner_ad_image'            => Setting::get('search_banner_ad_image', ''),
            'search_banner_ad_link'             => Setting::get('search_banner_ad_link', ''),
            'search_banner_ad_mobile_type'      => Setting::get('search_banner_ad_mobile_type', 'html'),
            'search_banner_ad_mobile_code'      => Setting::get('search_banner_ad_mobile_code', ''),
            'search_banner_ad_mobile_image'     => Setting::get('search_banner_ad_mobile_image', ''),
            'search_banner_ad_mobile_link'      => Setting::get('search_banner_ad_mobile_link', ''),
            'channel_banner_ad_enabled'         => Setting::get('channel_banner_ad_enabled', false),
            'channel_banner_ad_type'            => Setting::get('channel_banner_ad_type', 'html'),
            'channel_banner_ad_code'            => Setting::get('channel_banner_ad_code', ''),
            'channel_banner_ad_image'           => Setting::get('channel_banner_ad_image', ''),
            'channel_banner_ad_link'            => Setting::get('channel_banner_ad_link', ''),
            'channel_banner_ad_mobile_type'     => Setting::get('channel_banner_ad_mobile_type', 'html'),
            'channel_banner_ad_mobile_code'     => Setting::get('channel_banner_ad_mobile_code', ''),
            'channel_banner_ad_mobile_image'    => Setting::get('channel_banner_ad_mobile_image', ''),
            'channel_banner_ad_mobile_link'     => Setting::get('channel_banner_ad_mobile_link', ''),
            'category_banner_ad_enabled'        => Setting::get('category_banner_ad_enabled', false),
            'category_banner_ad_type'           => Setting::get('category_banner_ad_type', 'html'),
            'category_banner_ad_code'           => Setting::get('category_banner_ad_code', ''),
            'category_banner_ad_image'          => Setting::get('category_banner_ad_image', ''),
            'category_banner_ad_link'           => Setting::get('category_banner_ad_link', ''),
            'category_banner_ad_mobile_type'    => Setting::get('category_banner_ad_mobile_type', 'html'),
            'category_banner_ad_mobile_code'    => Setting::get('category_banner_ad_mobile_code', ''),
            'category_banner_ad_mobile_image'   => Setting::get('category_banner_ad_mobile_image', ''),
            'category_banner_ad_mobile_link'    => Setting::get('category_banner_ad_mobile_link', ''),
            'video_grid_ad_enabled'             => Setting::get('video_grid_ad_enabled', false),
            'video_grid_ad_frequency'           => Setting::get('video_grid_ad_frequency', 8),
            'video_grid_ad_code'                => Setting::get('video_grid_ad_code', ''),
            'video_grid_ad_mobile_code'         => Setting::get('video_grid_ad_mobile_code', ''),
            'video_sidebar_ad_enabled'          => Setting::get('video_sidebar_ad_enabled', false),
            'video_sidebar_ad_code'             => Setting::get('video_sidebar_ad_code', ''),
            'video_sidebar_ad_mobile_code'      => Setting::get('video_sidebar_ad_mobile_code', ''),
            'footer_ad_enabled'                 => Setting::get('footer_ad_enabled', false),
            'footer_ad_code'                    => Setting::get('footer_ad_code', ''),
            'footer_ad_mobile_code'             => Setting::get('footer_ad_mobile_code', ''),
            'custom_popunder_enabled'           => Setting::get('custom_popunder_enabled', false),
            'custom_popunder_code'              => Setting::get('custom_popunder_code', ''),
            'custom_popunder_mobile_code'       => Setting::get('custom_popunder_mobile_code', ''),
            'custom_interstitial_enabled'       => Setting::get('custom_interstitial_enabled', false),
            'custom_interstitial_code'          => Setting::get('custom_interstitial_code', ''),
            'custom_interstitial_mobile_code'   => Setting::get('custom_interstitial_mobile_code', ''),
            'custom_sticky_banner_enabled'      => Setting::get('custom_sticky_banner_enabled', false),
            'custom_sticky_banner_code'         => Setting::get('custom_sticky_banner_code', ''),
            'custom_sticky_banner_mobile_code'  => Setting::get('custom_sticky_banner_mobile_code', ''),
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Tabs::make('Ad Settings')->tabs([

                    Tab::make('Video Roll Ads')
                        ->icon('heroicon-o-play')
                        ->schema([
                            Section::make('Pre-Roll, Mid-Roll & Post-Roll Settings')
                                ->description('Configure video ads that play before, during, and after video content. VAST/VPAID ads use Google IMA SDK  the ad network controls skip. Manage individual ad creatives under Appearance  Ad Creatives.')
                                ->schema([
                                    Grid::make(3)->schema([
                                        Toggle::make('video_ad_pre_roll_enabled')
                                            ->label('Pre-Roll Ads')
                                            ->helperText('Play an ad before the video starts'),
                                        Toggle::make('video_ad_mid_roll_enabled')
                                            ->label('Mid-Roll Ads')
                                            ->helperText('Play ads during video playback'),
                                        Toggle::make('video_ad_post_roll_enabled')
                                            ->label('Post-Roll Ads')
                                            ->helperText('Play an ad after the video ends'),
                                    ]),
                                    Grid::make(3)->schema([
                                        TextInput::make('video_ad_pre_roll_skip_after')
                                            ->label('Pre-Roll Skip After (sec)')
                                            ->helperText('0 = unskippable')
                                            ->numeric()->default(5)->minValue(0)->maxValue(60),
                                        TextInput::make('video_ad_mid_roll_skip_after')
                                            ->label('Mid-Roll Skip After (sec)')
                                            ->helperText('0 = unskippable')
                                            ->numeric()->default(5)->minValue(0)->maxValue(60),
                                        TextInput::make('video_ad_post_roll_skip_after')
                                            ->label('Post-Roll Skip After (sec)')
                                            ->helperText('0 = unskippable')
                                            ->numeric()->default(0)->minValue(0)->maxValue(60),
                                    ]),
                                    Grid::make(3)->schema([
                                        TextInput::make('video_ad_mid_roll_interval')
                                            ->label('Mid-Roll Interval (sec)')
                                            ->helperText('e.g. 300 = every 5 min')
                                            ->numeric()->default(300)->minValue(30)->maxValue(3600),
                                        TextInput::make('video_ad_mid_roll_max_count')
                                            ->label('Max Mid-Roll Ads')
                                            ->helperText('Maximum mid-roll ads per video')
                                            ->numeric()->default(3)->minValue(1)->maxValue(20),
                                        Toggle::make('video_ad_shuffle')
                                            ->label('Shuffle / Randomize')
                                            ->helperText('Pick ads weighted by priority instead of in order'),
                                    ]),
                                ]),
                        ]),

                    Tab::make('Banner Ads')
                        ->icon('heroicon-o-rectangle-group')
                        ->schema([
                            Grid::make(2)->schema([
                                Section::make('Above Video Player')
                                    ->description('728x90 desktop  300x100 mobile')
                                    ->icon('heroicon-o-arrow-up')
                                    ->collapsible()->collapsed()
                                    ->schema(self::bannerAdFields('banner_above_player', 'Enable Above-Player Banner')),
                                Section::make('Below Video Player')
                                    ->description('728x90 desktop  300x100 mobile')
                                    ->icon('heroicon-o-arrow-down')
                                    ->collapsible()->collapsed()
                                    ->schema(self::bannerAdFields('banner_below_player', 'Enable Below-Player Banner')),
                                Section::make('Browse Page Banner')
                                    ->description('Banner at the top of the Browse Videos page')
                                    ->icon('heroicon-o-squares-2x2')
                                    ->collapsible()->collapsed()
                                    ->schema(self::bannerAdFields('browse_banner_ad', 'Enable Browse Page Banner')),
                                Section::make('Search Results Banner')
                                    ->description('Banner at the top of search results')
                                    ->icon('heroicon-o-magnifying-glass')
                                    ->collapsible()->collapsed()
                                    ->schema(self::bannerAdFields('search_banner_ad', 'Enable Search Results Banner')),
                                Section::make('Channel Page Banner')
                                    ->description('Banner below the channel header, above videos')
                                    ->icon('heroicon-o-user')
                                    ->collapsible()->collapsed()
                                    ->schema(self::bannerAdFields('channel_banner_ad', 'Enable Channel Page Banner')),
                                Section::make('Category Page Banner')
                                    ->description('Banner at the top of category listing pages')
                                    ->icon('heroicon-o-tag')
                                    ->collapsible()->collapsed()
                                    ->schema(self::bannerAdFields('category_banner_ad', 'Enable Category Page Banner')),
                                Section::make('Video Grid Ads')
                                    ->description('Injected between video cards on browsing pages. Recommended: 300x250')
                                    ->icon('heroicon-o-squares-2x2')
                                    ->collapsible()->collapsed()
                                    ->schema([
                                        Toggle::make('video_grid_ad_enabled')->label('Enable Video Grid Ads')->live(),
                                        TextInput::make('video_grid_ad_frequency')
                                            ->label('Ad Frequency')->helperText('Show an ad after every X videos')
                                            ->numeric()->default(8)->minValue(2)->maxValue(50)
                                            ->visible(fn ($get) => $get('video_grid_ad_enabled')),
                                        Textarea::make('video_grid_ad_code')
                                            ->label('Desktop Ad HTML Code (300x250)')->rows(4)->columnSpanFull()
                                            ->visible(fn ($get) => $get('video_grid_ad_enabled')),
                                        Textarea::make('video_grid_ad_mobile_code')
                                            ->label('Mobile Ad HTML Code')->rows(4)->columnSpanFull()
                                            ->helperText('Leave empty to use desktop code on all devices.')
                                            ->visible(fn ($get) => $get('video_grid_ad_enabled')),
                                    ]),
                                Section::make('Video Page Sidebar Ad')
                                    ->description('Ad above related videos on watch pages. Recommended: 300x250 or 300x600')
                                    ->icon('heroicon-o-rectangle-stack')
                                    ->collapsible()->collapsed()
                                    ->schema([
                                        Toggle::make('video_sidebar_ad_enabled')->label('Enable Sidebar Ad')->live(),
                                        Textarea::make('video_sidebar_ad_code')
                                            ->label('Desktop Ad HTML Code (300x250 / 300x600)')->rows(4)->columnSpanFull()
                                            ->visible(fn ($get) => $get('video_sidebar_ad_enabled')),
                                        Textarea::make('video_sidebar_ad_mobile_code')
                                            ->label('Mobile Ad HTML Code')->rows(4)->columnSpanFull()
                                            ->helperText('Leave empty to use desktop code on all devices.')
                                            ->visible(fn ($get) => $get('video_sidebar_ad_enabled')),
                                    ]),
                                Section::make('Footer Ad Banner')
                                    ->description('Ad banner above the footer legal links on every page')
                                    ->icon('heroicon-o-bars-arrow-down')
                                    ->collapsible()->collapsed()
                                    ->schema([
                                        Toggle::make('footer_ad_enabled')->label('Enable Footer Ad Banner')->live(),
                                        Textarea::make('footer_ad_code')
                                            ->label('Desktop Ad Code (728x90)')->rows(4)->columnSpanFull()
                                            ->visible(fn ($get) => $get('footer_ad_enabled')),
                                        Textarea::make('footer_ad_mobile_code')
                                            ->label('Mobile Ad Code (300x50 / 300x100)')->rows(4)->columnSpanFull()
                                            ->helperText('Leave empty to use desktop code on all devices.')
                                            ->visible(fn ($get) => $get('footer_ad_enabled')),
                                    ]),
                            ]),
                        ]),

                    Tab::make('Script Ads')
                        ->icon('heroicon-o-code-bracket')
                        ->schema([
                            Grid::make(2)->schema([
                                Section::make('Popunder Ad')
                                    ->description('Full-page popunder that opens in a new tab/window. Injected site-wide on every page load.')
                                    ->icon('heroicon-o-window')
                                    ->collapsible()->collapsed()
                                    ->schema([
                                        Toggle::make('custom_popunder_enabled')->label('Enable Popunder Ad')->live(),
                                        Textarea::make('custom_popunder_code')
                                            ->label('Desktop Popunder Script Code')->rows(5)->columnSpanFull()
                                            ->placeholder('<script src="https://a.magsrv.com/ad-provider.js"></script>...')
                                            ->helperText('Injected before </body> on every page.')
                                            ->visible(fn ($get) => $get('custom_popunder_enabled')),
                                        Textarea::make('custom_popunder_mobile_code')
                                            ->label('Mobile Popunder Script Code')->rows(5)->columnSpanFull()
                                            ->helperText('Leave empty to use desktop code on all devices.')
                                            ->visible(fn ($get) => $get('custom_popunder_enabled')),
                                    ]),
                                Section::make('Interstitial / Full-Page Ad')
                                    ->description('Full-screen interstitial ad overlay shown on page transitions.')
                                    ->icon('heroicon-o-arrows-pointing-out')
                                    ->collapsible()->collapsed()
                                    ->schema([
                                        Toggle::make('custom_interstitial_enabled')->label('Enable Interstitial Ad')->live(),
                                        Textarea::make('custom_interstitial_code')
                                            ->label('Desktop Interstitial Script Code')->rows(5)->columnSpanFull()
                                            ->placeholder('<script src="https://a.magsrv.com/ad-provider.js"></script>...')
                                            ->helperText('Injected before </body> on every page.')
                                            ->visible(fn ($get) => $get('custom_interstitial_enabled')),
                                        Textarea::make('custom_interstitial_mobile_code')
                                            ->label('Mobile Interstitial Script Code')->rows(5)->columnSpanFull()
                                            ->helperText('Leave empty to use desktop code on all devices.')
                                            ->visible(fn ($get) => $get('custom_interstitial_enabled')),
                                    ]),
                                Section::make('Sticky Banner / Video Slider Ad')
                                    ->description('Sticky banner fixed at the bottom of the viewport.')
                                    ->icon('heroicon-o-bars-arrow-down')
                                    ->collapsible()->collapsed()
                                    ->schema([
                                        Toggle::make('custom_sticky_banner_enabled')->label('Enable Sticky Banner Ad')->live(),
                                        Textarea::make('custom_sticky_banner_code')
                                            ->label('Desktop Sticky Banner Code')->rows(5)->columnSpanFull()
                                            ->placeholder('<script src="https://a.magsrv.com/ad-provider.js"></script>...')
                                            ->helperText('Shown on screens >=768px wide.')
                                            ->visible(fn ($get) => $get('custom_sticky_banner_enabled')),
                                        Textarea::make('custom_sticky_banner_mobile_code')
                                            ->label('Mobile Sticky Banner Code')->rows(5)->columnSpanFull()
                                            ->helperText('Shown on screens <768px wide. Leave empty to use desktop code.')
                                            ->visible(fn ($get) => $get('custom_sticky_banner_enabled')),
                                    ]),
                            ]),
                        ]),

                ])->columnSpanFull(),
            ])
            ->statePath('data');
    }

    /**
     * Reusable banner ad fields with proper type-conditional visibility.
     * Desktop and mobile each independently show EITHER the HTML textarea OR the image upload + URL + link.
     */
    protected static function bannerAdFields(string $prefix, string $enableLabel): array
    {
        return [
            Toggle::make("{$prefix}_enabled")->label($enableLabel)->live(),

            // Desktop
            Select::make("{$prefix}_type")
                ->label('Desktop Ad Type')
                ->options(['html' => 'HTML Ad Code', 'image' => 'Image + Link'])
                ->default('html')->live()
                ->visible(fn ($get) => $get("{$prefix}_enabled")),

            Textarea::make("{$prefix}_html")
                ->label('Desktop HTML Ad Code (728x90)')
                ->rows(4)->columnSpanFull()
                ->visible(fn ($get) => $get("{$prefix}_enabled") && $get("{$prefix}_type") === 'html'),

            FileUpload::make("{$prefix}_image_upload")
                ->label('Desktop Banner Image (upload)')
                ->image()->disk('public')->directory('media/images')
                ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/gif', 'image/webp'])
                ->maxSize(10240)
                ->helperText('Or paste a URL below. Uploaded file takes priority.')
                ->columnSpanFull()
                ->visible(fn ($get) => $get("{$prefix}_enabled") && $get("{$prefix}_type") === 'image'),

            Grid::make(2)->schema([
                TextInput::make("{$prefix}_image")
                    ->label('Desktop Image URL (728x90)')
                    ->placeholder('https://example.com/banner-728x90.jpg')
                    ->helperText('Used if no file is uploaded above'),
                TextInput::make("{$prefix}_link")
                    ->label('Desktop Click URL')
                    ->placeholder('https://example.com'),
            ])->visible(fn ($get) => $get("{$prefix}_enabled") && $get("{$prefix}_type") === 'image'),

            // Mobile
            Select::make("{$prefix}_mobile_type")
                ->label('Mobile Ad Type')
                ->options(['html' => 'HTML Ad Code', 'image' => 'Image + Link'])
                ->default('html')->live()
                ->visible(fn ($get) => $get("{$prefix}_enabled")),

            Textarea::make("{$prefix}_mobile_html")
                ->label('Mobile HTML Ad Code (300x100 / 300x50)')
                ->rows(4)->columnSpanFull()
                ->visible(fn ($get) => $get("{$prefix}_enabled") && $get("{$prefix}_mobile_type") === 'html'),

            FileUpload::make("{$prefix}_mobile_image_upload")
                ->label('Mobile Banner Image (upload)')
                ->image()->disk('public')->directory('media/images')
                ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/gif', 'image/webp'])
                ->maxSize(10240)
                ->helperText('Or paste a URL below. Uploaded file takes priority.')
                ->columnSpanFull()
                ->visible(fn ($get) => $get("{$prefix}_enabled") && $get("{$prefix}_mobile_type") === 'image'),

            Grid::make(2)->schema([
                TextInput::make("{$prefix}_mobile_image")
                    ->label('Mobile Image URL (300x100)')
                    ->placeholder('https://example.com/banner-300x100.jpg')
                    ->helperText('Used if no file is uploaded above'),
                TextInput::make("{$prefix}_mobile_link")
                    ->label('Mobile Click URL')
                    ->placeholder('https://example.com'),
            ])->visible(fn ($get) => $get("{$prefix}_enabled") && $get("{$prefix}_mobile_type") === 'image'),
        ];
    }

    public function save(): void
    {
        $data = $this->form->getState();

        $bannerPrefixes = [
            'banner_above_player', 'banner_below_player',
            'browse_banner_ad', 'search_banner_ad',
            'channel_banner_ad', 'category_banner_ad',
        ];

        foreach ($bannerPrefixes as $prefix) {
            foreach (['', '_mobile'] as $variant) {
                $uploadKey = "{$prefix}{$variant}_image_upload";
                $imageKey  = "{$prefix}{$variant}_image";

                if (!empty($data[$uploadKey])) {
                    $data[$imageKey] = Storage::disk('public')->url($data[$uploadKey]);
                }

                unset($data[$uploadKey]);
            }
        }

        foreach ($data as $key => $value) {
            $type = match (true) {
                is_bool($value) => 'boolean',
                is_int($value)  => 'integer',
                default         => 'string',
            };
            Setting::set($key, $value, 'ads', $type);
        }

        AdminLogger::settingsSaved('Ad', array_keys($data));

        Notification::make()
            ->title('Ad settings saved successfully')
            ->success()
            ->send();
    }
}

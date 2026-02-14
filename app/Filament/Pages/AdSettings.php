<?php

namespace App\Filament\Pages;

use App\Models\Category;
use App\Models\Setting;
use App\Services\AdminLogger;
use App\Models\VideoAd;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Storage;

class AdSettings extends Page implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-currency-dollar';
    protected static ?string $navigationLabel = 'Ad Settings';
    protected static ?string $navigationGroup = 'Appearance';
    protected static ?int $navigationSort = 2;
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

            // Video Player Banner Ads
            'banner_above_player_enabled' => Setting::get('banner_above_player_enabled', false),
            'banner_above_player_type' => Setting::get('banner_above_player_type', 'html'),
            'banner_above_player_html' => Setting::get('banner_above_player_html', ''),
            'banner_above_player_image' => Setting::get('banner_above_player_image', ''),
            'banner_above_player_link' => Setting::get('banner_above_player_link', ''),
            'banner_above_player_mobile_type' => Setting::get('banner_above_player_mobile_type', 'html'),
            'banner_above_player_mobile_html' => Setting::get('banner_above_player_mobile_html', ''),
            'banner_above_player_mobile_image' => Setting::get('banner_above_player_mobile_image', ''),
            'banner_above_player_mobile_link' => Setting::get('banner_above_player_mobile_link', ''),

            'banner_below_player_enabled' => Setting::get('banner_below_player_enabled', false),
            'banner_below_player_type' => Setting::get('banner_below_player_type', 'html'),
            'banner_below_player_html' => Setting::get('banner_below_player_html', ''),
            'banner_below_player_image' => Setting::get('banner_below_player_image', ''),
            'banner_below_player_link' => Setting::get('banner_below_player_link', ''),
            'banner_below_player_mobile_type' => Setting::get('banner_below_player_mobile_type', 'html'),
            'banner_below_player_mobile_html' => Setting::get('banner_below_player_mobile_html', ''),
            'banner_below_player_mobile_image' => Setting::get('banner_below_player_mobile_image', ''),
            'banner_below_player_mobile_link' => Setting::get('banner_below_player_mobile_link', ''),

            // Video Roll Ads — Global Settings
            'video_ad_pre_roll_enabled' => Setting::get('video_ad_pre_roll_enabled', false),
            'video_ad_mid_roll_enabled' => Setting::get('video_ad_mid_roll_enabled', false),
            'video_ad_post_roll_enabled' => Setting::get('video_ad_post_roll_enabled', false),
            'video_ad_pre_roll_skip_after' => Setting::get('video_ad_pre_roll_skip_after', 5),
            'video_ad_mid_roll_skip_after' => Setting::get('video_ad_mid_roll_skip_after', 5),
            'video_ad_post_roll_skip_after' => Setting::get('video_ad_post_roll_skip_after', 0),
            'video_ad_mid_roll_interval' => Setting::get('video_ad_mid_roll_interval', 300),
            'video_ad_mid_roll_max_count' => Setting::get('video_ad_mid_roll_max_count', 3),
            'video_ad_shuffle' => Setting::get('video_ad_shuffle', true),

            // Footer Ad Banner
            'footer_ad_enabled' => Setting::get('footer_ad_enabled', false),
            'footer_ad_code' => Setting::get('footer_ad_code', ''),

            // Browse Page Banner Ad
            'browse_banner_ad_enabled' => Setting::get('browse_banner_ad_enabled', false),
            'browse_banner_ad_code' => Setting::get('browse_banner_ad_code', ''),

            // Search Results Banner Ad
            'search_banner_ad_enabled' => Setting::get('search_banner_ad_enabled', false),
            'search_banner_ad_code' => Setting::get('search_banner_ad_code', ''),

            // Channel Page Banner Ad
            'channel_banner_ad_enabled' => Setting::get('channel_banner_ad_enabled', false),
            'channel_banner_ad_code' => Setting::get('channel_banner_ad_code', ''),

            // Category Page Banner Ad
            'category_banner_ad_enabled' => Setting::get('category_banner_ad_enabled', false),
            'category_banner_ad_code' => Setting::get('category_banner_ad_code', ''),

            // Custom Ad Scripts (ExoClick, etc.)
            'custom_popunder_enabled' => Setting::get('custom_popunder_enabled', false),
            'custom_popunder_code' => Setting::get('custom_popunder_code', ''),
            'custom_interstitial_enabled' => Setting::get('custom_interstitial_enabled', false),
            'custom_interstitial_code' => Setting::get('custom_interstitial_code', ''),
            'custom_sticky_banner_enabled' => Setting::get('custom_sticky_banner_enabled', false),
            'custom_sticky_banner_code' => Setting::get('custom_sticky_banner_code', ''),
            'custom_sticky_banner_mobile_code' => Setting::get('custom_sticky_banner_mobile_code', ''),
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                // ── Video Roll Ads ──
                Section::make('Video Pre-Roll, Mid-Roll & Post-Roll Ads')
                    ->description('Configure video ads that play before, during, and after video content. Supports VAST/VPAID tags, direct MP4 files, and HTML ad scripts. Manage individual ad creatives in the table below.')
                    ->icon('heroicon-o-play')
                    ->collapsible()
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
                                ->numeric()->default(5)->minValue(0)->maxValue(60),
                            TextInput::make('video_ad_post_roll_skip_after')
                                ->label('Post-Roll Skip After (sec)')
                                ->numeric()->default(0)->minValue(0)->maxValue(60),
                        ]),

                        Grid::make(3)->schema([
                            TextInput::make('video_ad_mid_roll_interval')
                                ->label('Mid-Roll Interval (sec)')
                                ->helperText('Seconds between mid-roll ads (e.g. 300 = every 5 min)')
                                ->numeric()->default(300)->minValue(30)->maxValue(3600),
                            TextInput::make('video_ad_mid_roll_max_count')
                                ->label('Max Mid-Roll Ads')
                                ->helperText('Maximum mid-roll ads per video')
                                ->numeric()->default(3)->minValue(1)->maxValue(20),
                            Toggle::make('video_ad_shuffle')
                                ->label('Shuffle / Randomize')
                                ->helperText('Randomly pick ads weighted by priority instead of playing in order'),
                        ]),
                    ]),

                // ── Banner Ads Above/Below Player ──
                Section::make('Banner Ad — Above Video Player')
                    ->description('728×90 leaderboard banner shown above the video player on desktop. 300×100 or 300×50 on mobile. Supports image+link or HTML ad code.')
                    ->icon('heroicon-o-arrow-up')
                    ->collapsible()
                    ->collapsed()
                    ->schema([
                        Toggle::make('banner_above_player_enabled')
                            ->label('Enable Above-Player Banner'),
                        Grid::make(2)->schema([
                            Select::make('banner_above_player_type')
                                ->label('Desktop Ad Type')
                                ->options(['html' => 'HTML Ad Code', 'image' => 'Image + Link'])
                                ->default('html'),
                            Select::make('banner_above_player_mobile_type')
                                ->label('Mobile Ad Type')
                                ->options(['html' => 'HTML Ad Code', 'image' => 'Image + Link'])
                                ->default('html'),
                        ]),
                        Textarea::make('banner_above_player_html')
                            ->label('Desktop HTML Ad Code (728×90)')
                            ->rows(4)->columnSpanFull(),
                        Grid::make(2)->schema([
                            TextInput::make('banner_above_player_image')
                                ->label('Desktop Image URL (728×90)')
                                ->placeholder('https://example.com/banner-728x90.jpg'),
                            TextInput::make('banner_above_player_link')
                                ->label('Desktop Click URL')
                                ->placeholder('https://example.com'),
                        ]),
                        Textarea::make('banner_above_player_mobile_html')
                            ->label('Mobile HTML Ad Code (300×100 / 300×50)')
                            ->rows(4)->columnSpanFull(),
                        Grid::make(2)->schema([
                            TextInput::make('banner_above_player_mobile_image')
                                ->label('Mobile Image URL (300×100)')
                                ->placeholder('https://example.com/banner-300x100.jpg'),
                            TextInput::make('banner_above_player_mobile_link')
                                ->label('Mobile Click URL')
                                ->placeholder('https://example.com'),
                        ]),
                    ]),

                Section::make('Banner Ad — Below Video Player')
                    ->description('728×90 leaderboard banner shown below the video player on desktop. 300×100 or 300×50 on mobile.')
                    ->icon('heroicon-o-arrow-down')
                    ->collapsible()
                    ->collapsed()
                    ->schema([
                        Toggle::make('banner_below_player_enabled')
                            ->label('Enable Below-Player Banner'),
                        Grid::make(2)->schema([
                            Select::make('banner_below_player_type')
                                ->label('Desktop Ad Type')
                                ->options(['html' => 'HTML Ad Code', 'image' => 'Image + Link'])
                                ->default('html'),
                            Select::make('banner_below_player_mobile_type')
                                ->label('Mobile Ad Type')
                                ->options(['html' => 'HTML Ad Code', 'image' => 'Image + Link'])
                                ->default('html'),
                        ]),
                        Textarea::make('banner_below_player_html')
                            ->label('Desktop HTML Ad Code (728×90)')
                            ->rows(4)->columnSpanFull(),
                        Grid::make(2)->schema([
                            TextInput::make('banner_below_player_image')
                                ->label('Desktop Image URL (728×90)')
                                ->placeholder('https://example.com/banner-728x90.jpg'),
                            TextInput::make('banner_below_player_link')
                                ->label('Desktop Click URL')
                                ->placeholder('https://example.com'),
                        ]),
                        Textarea::make('banner_below_player_mobile_html')
                            ->label('Mobile HTML Ad Code (300×100 / 300×50)')
                            ->rows(4)->columnSpanFull(),
                        Grid::make(2)->schema([
                            TextInput::make('banner_below_player_mobile_image')
                                ->label('Mobile Image URL (300×100)')
                                ->placeholder('https://example.com/banner-300x100.jpg'),
                            TextInput::make('banner_below_player_mobile_link')
                                ->label('Mobile Click URL')
                                ->placeholder('https://example.com'),
                        ]),
                    ]),

                // ── Existing Display Ads ──
                Section::make('Video Grid Ads')
                    ->description('Ads between video cards on browsing pages. Recommended: 300×250')
                    ->collapsible()
                    ->collapsed()
                    ->schema([
                        Toggle::make('video_grid_ad_enabled')
                            ->label('Enable Video Grid Ads'),
                        Grid::make(2)->schema([
                            TextInput::make('video_grid_ad_frequency')
                                ->label('Ad Frequency')
                                ->helperText('Show an ad after every X videos')
                                ->numeric()->default(8)->minValue(2)->maxValue(50),
                        ]),
                        Textarea::make('video_grid_ad_code')
                            ->label('Ad HTML Code')
                            ->rows(6)->columnSpanFull(),
                    ]),

                Section::make('Video Page Sidebar Ad')
                    ->description('Ad above related videos on watch pages. Recommended: 300×250 or 300×600')
                    ->collapsible()
                    ->collapsed()
                    ->schema([
                        Toggle::make('video_sidebar_ad_enabled')
                            ->label('Enable Sidebar Ad'),
                        Textarea::make('video_sidebar_ad_code')
                            ->label('Ad HTML Code')
                            ->rows(6)->columnSpanFull(),
                    ]),

                // ── Site-Wide Banner Ads ──
                Section::make('Footer Ad Banner')
                    ->description('728×90 desktop / 300×50 mobile ad banner displayed above the footer legal links on every page.')
                    ->icon('heroicon-o-rectangle-group')
                    ->collapsible()
                    ->collapsed()
                    ->schema([
                        Toggle::make('footer_ad_enabled')
                            ->label('Enable Footer Ad Banner'),
                        Textarea::make('footer_ad_code')
                            ->label('Ad Code (HTML)')
                            ->rows(4)
                            ->placeholder('<ins class="adsbygoogle" ...></ins>')
                            ->helperText('Paste your ad network code here (e.g. Google AdSense, ExoClick, etc.)')
                            ->visible(fn ($get) => $get('footer_ad_enabled')),
                    ]),

                Section::make('Browse Page Banner Ad')
                    ->description('728×90 desktop / 300×50 mobile ad banner displayed at the top of the Browse Videos page.')
                    ->icon('heroicon-o-rectangle-group')
                    ->collapsible()
                    ->collapsed()
                    ->schema([
                        Toggle::make('browse_banner_ad_enabled')
                            ->label('Enable Browse Page Banner'),
                        Textarea::make('browse_banner_ad_code')
                            ->label('Ad Code (HTML)')
                            ->rows(4)
                            ->placeholder('<ins class="adsbygoogle" ...></ins>')
                            ->visible(fn ($get) => $get('browse_banner_ad_enabled')),
                    ]),

                Section::make('Search Results Banner Ad')
                    ->description('728×90 desktop / 300×50 mobile ad banner displayed at the top of search results.')
                    ->icon('heroicon-o-magnifying-glass')
                    ->collapsible()
                    ->collapsed()
                    ->schema([
                        Toggle::make('search_banner_ad_enabled')
                            ->label('Enable Search Results Banner'),
                        Textarea::make('search_banner_ad_code')
                            ->label('Ad Code (HTML)')
                            ->rows(4)
                            ->placeholder('<ins class="adsbygoogle" ...></ins>')
                            ->visible(fn ($get) => $get('search_banner_ad_enabled')),
                    ]),

                Section::make('Channel Page Banner Ad')
                    ->description('728×90 desktop / 300×50 mobile ad banner displayed below the channel header, above videos.')
                    ->icon('heroicon-o-user')
                    ->collapsible()
                    ->collapsed()
                    ->schema([
                        Toggle::make('channel_banner_ad_enabled')
                            ->label('Enable Channel Page Banner'),
                        Textarea::make('channel_banner_ad_code')
                            ->label('Ad Code (HTML)')
                            ->rows(4)
                            ->placeholder('<ins class="adsbygoogle" ...></ins>')
                            ->visible(fn ($get) => $get('channel_banner_ad_enabled')),
                    ]),

                Section::make('Category Page Banner Ad')
                    ->description('728×90 desktop / 300×50 mobile ad banner displayed at the top of category listing pages.')
                    ->icon('heroicon-o-tag')
                    ->collapsible()
                    ->collapsed()
                    ->schema([
                        Toggle::make('category_banner_ad_enabled')
                            ->label('Enable Category Page Banner'),
                        Textarea::make('category_banner_ad_code')
                            ->label('Ad Code (HTML)')
                            ->rows(4)
                            ->placeholder('<ins class="adsbygoogle" ...></ins>')
                            ->visible(fn ($get) => $get('category_banner_ad_enabled')),
                    ]),

                // ── Custom Ad Scripts (ExoClick, etc.) ──
                Section::make('Popunder Ad')
                    ->description('Full-page popunder ad that opens in a new tab/window. Commonly used with ExoClick, JuicyAds, etc. The script is injected site-wide on every page load.')
                    ->icon('heroicon-o-window')
                    ->collapsible()
                    ->collapsed()
                    ->schema([
                        Toggle::make('custom_popunder_enabled')
                            ->label('Enable Popunder Ad'),
                        Textarea::make('custom_popunder_code')
                            ->label('Popunder Script Code')
                            ->rows(6)
                            ->placeholder('<script src="https://a.magsrv.com/ad-provider.js"></script>...')
                            ->helperText('Paste the full ad network script tag. This will be injected before </body> on every page.')
                            ->visible(fn ($get) => $get('custom_popunder_enabled')),
                    ]),

                Section::make('Interstitial / Full-Page Ad')
                    ->description('Full-screen interstitial ad overlay. Typically shown on page transitions or after a set interval. Used by ExoClick, Adsterra, etc.')
                    ->icon('heroicon-o-arrows-pointing-out')
                    ->collapsible()
                    ->collapsed()
                    ->schema([
                        Toggle::make('custom_interstitial_enabled')
                            ->label('Enable Interstitial Ad'),
                        Textarea::make('custom_interstitial_code')
                            ->label('Interstitial Script Code')
                            ->rows(6)
                            ->placeholder('<script src="https://a.magsrv.com/ad-provider.js"></script>...')
                            ->helperText('Paste the full ad network script tag. This will be injected before </body> on every page.')
                            ->visible(fn ($get) => $get('custom_interstitial_enabled')),
                    ]),

                Section::make('Sticky Banner / Video Slider Ad')
                    ->description('Sticky banner or video slider ad that stays fixed at the bottom of the viewport. Separate codes for desktop and mobile. Used by ExoClick, TrafficStars, etc.')
                    ->icon('heroicon-o-bars-arrow-down')
                    ->collapsible()
                    ->collapsed()
                    ->schema([
                        Toggle::make('custom_sticky_banner_enabled')
                            ->label('Enable Sticky Banner Ad'),
                        Textarea::make('custom_sticky_banner_code')
                            ->label('Desktop Sticky Banner Code')
                            ->rows(6)
                            ->placeholder('<script src="https://a.magsrv.com/ad-provider.js"></script>...')
                            ->helperText('Shown on screens ≥768px wide.')
                            ->visible(fn ($get) => $get('custom_sticky_banner_enabled')),
                        Textarea::make('custom_sticky_banner_mobile_code')
                            ->label('Mobile Sticky Banner Code')
                            ->rows(6)
                            ->placeholder('<script src="https://a.magsrv.com/ad-provider.js"></script>...')
                            ->helperText('Shown on screens <768px wide. Leave empty to use the desktop code on all devices.')
                            ->visible(fn ($get) => $get('custom_sticky_banner_enabled')),
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

        AdminLogger::settingsSaved('Ad', array_keys($data));

        Notification::make()
            ->title('Ad settings saved successfully')
            ->success()
            ->send();
    }

    // ── Video Ad Creatives Table ──

    public function table(Table $table): Table
    {
        return $table
            ->query(VideoAd::query())
            ->heading('Video Ad Creatives')
            ->description('Manage individual ad creatives for pre-roll, mid-roll, and post-roll placements.')
            ->defaultSort('is_active', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->weight('bold')
                    ->searchable(),

                Tables\Columns\TextColumn::make('type')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => strtoupper($state))
                    ->color(fn (string $state): string => match ($state) {
                        'mp4' => 'info',
                        'vast' => 'purple' ,
                        'vpaid' => 'indigo',
                        'html' => 'warning',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('placement')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => str_replace('_', '-', $state))
                    ->color(fn (string $state): string => match ($state) {
                        'pre_roll' => 'success',
                        'mid_roll' => 'warning',
                        'post_roll' => 'danger',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('weight')
                    ->alignCenter()
                    ->sortable(),

                Tables\Columns\TextColumn::make('category_ids')
                    ->label('Targeting')
                    ->formatStateUsing(function ($state, VideoAd $record) {
                        $cats = $record->category_ids && count($record->category_ids)
                            ? count($record->category_ids) . ' categories'
                            : 'All categories';
                        $roles = $record->target_roles && count($record->target_roles)
                            ? implode(', ', $record->target_roles)
                            : 'All roles';
                        return "{$cats} · {$roles}";
                    })
                    ->color('gray')
                    ->size('sm'),

                Tables\Columns\ToggleColumn::make('is_active')
                    ->label('Active'),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->form(self::adCreativeFormSchema())
                    ->mutateRecordDataUsing(function (array $data): array {
                        $data['category_ids'] = $data['category_ids'] ?? [];
                        $data['target_roles'] = $data['target_roles'] ?? [];
                        return $data;
                    })
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['category_ids'] = !empty($data['category_ids']) ? array_map('intval', $data['category_ids']) : null;
                        $data['target_roles'] = !empty($data['target_roles']) ? $data['target_roles'] : null;
                        return $data;
                    }),

                Tables\Actions\DeleteAction::make()
                    ->after(function (VideoAd $record) {
                        if ($record->file_path && Storage::disk('public')->exists($record->file_path)) {
                            Storage::disk('public')->delete($record->file_path);
                        }
                    }),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->model(VideoAd::class)
                    ->form(self::adCreativeFormSchema())
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['category_ids'] = !empty($data['category_ids']) ? array_map('intval', $data['category_ids']) : null;
                        $data['target_roles'] = !empty($data['target_roles']) ? $data['target_roles'] : null;
                        return $data;
                    }),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ])
            ->emptyStateHeading('No ad creatives')
            ->emptyStateDescription('Click "New" to create your first video ad creative.')
            ->emptyStateIcon('heroicon-o-film')
            ->striped();
    }

    protected static function adCreativeFormSchema(): array
    {
        return [
            Grid::make(2)->schema([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255)
                    ->placeholder('e.g. Summer Sale Pre-Roll'),

                Select::make('placement')
                    ->required()
                    ->options([
                        'pre_roll' => 'Pre-Roll (before video)',
                        'mid_roll' => 'Mid-Roll (during video)',
                        'post_roll' => 'Post-Roll (after video)',
                    ])
                    ->default('pre_roll'),
            ]),

            Grid::make(2)->schema([
                Select::make('type')
                    ->required()
                    ->options([
                        'mp4' => 'MP4 Video URL',
                        'vast' => 'VAST Tag URL',
                        'vpaid' => 'VPAID Tag URL',
                        'html' => 'HTML Ad Script',
                    ])
                    ->default('mp4')
                    ->live(),

                TextInput::make('weight')
                    ->numeric()
                    ->default(1)
                    ->minValue(1)
                    ->maxValue(100)
                    ->helperText('Higher weight = more likely when shuffling'),
            ]),

            Textarea::make('content')
                ->label(fn ($get) => match ($get('type')) {
                    'mp4' => 'MP4 Video URL',
                    'vast' => 'VAST Tag URL',
                    'vpaid' => 'VPAID Tag URL',
                    'html' => 'HTML Ad Script',
                    default => 'Content',
                })
                ->required()
                ->rows(4)
                ->columnSpanFull()
                ->placeholder(fn ($get) => match ($get('type')) {
                    'mp4' => 'https://example.com/ads/my-ad.mp4',
                    'vast' => 'https://example.com/vast-tag.xml',
                    'vpaid' => 'https://example.com/vpaid-tag.xml',
                    'html' => '<script>...</script>',
                    default => '',
                }),

            TextInput::make('click_url')
                ->label('Click-Through URL')
                ->url()
                ->maxLength(2048)
                ->placeholder('https://example.com/landing-page')
                ->helperText('Optional. Clicking the ad opens this URL in a new tab.')
                ->columnSpanFull(),

            Grid::make(2)->schema([
                CheckboxList::make('category_ids')
                    ->label('Target Categories')
                    ->options(fn () => Category::active()->orderBy('name')->pluck('name', 'id')->toArray())
                    ->helperText('Leave empty to show on all categories')
                    ->columns(2),

                CheckboxList::make('target_roles')
                    ->label('Target User Roles')
                    ->options([
                        'guest' => 'Guests (not logged in)',
                        'default' => 'Default Users (free)',
                        'pro' => 'Pro Users',
                        'admin' => 'Admins',
                    ])
                    ->helperText('Leave empty to show to all users'),
            ]),

            Toggle::make('is_active')
                ->label('Active')
                ->default(true),
        ];
    }
}

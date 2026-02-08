<?php

namespace App\Filament\Pages;

use App\Models\Category;
use App\Models\Setting;
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

class AdSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-currency-dollar';
    protected static ?string $navigationLabel = 'Ad Settings';
    protected static ?string $navigationGroup = 'Settings';
    protected static ?int $navigationSort = 3;
    protected static string $view = 'filament.pages.ad-settings';

    public ?array $data = [];
    public ?array $adFormData = [];
    public ?int $editingAdId = null;
    public bool $showAdForm = false;

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
        ]);

        $this->resetAdForm();
    }

    protected function resetAdForm(): void
    {
        $this->adFormData = [
            'name' => '',
            'type' => 'mp4',
            'placement' => 'pre_roll',
            'content' => '',
            'weight' => 1,
            'is_active' => true,
            'category_ids' => [],
            'target_roles' => [],
        ];
        $this->editingAdId = null;
        $this->showAdForm = false;
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

                Section::make('Shorts Ad Interstitials')
                    ->description('Full-screen ads between shorts in the vertical viewer.')
                    ->collapsible()
                    ->collapsed()
                    ->schema([
                        Toggle::make('shorts_ads_enabled')
                            ->label('Enable Shorts Ads'),
                        Grid::make(2)->schema([
                            TextInput::make('shorts_ad_frequency')
                                ->label('Ad Frequency')
                                ->numeric()->default(3)->minValue(1)->maxValue(20),
                            TextInput::make('shorts_ad_skip_delay')
                                ->label('Skip Delay (seconds)')
                                ->numeric()->default(5)->minValue(0)->maxValue(30),
                        ]),
                        Textarea::make('shorts_ad_code')
                            ->label('Ad HTML Code')
                            ->rows(6)->columnSpanFull(),
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

    // ── Ad Creative CRUD (Livewire methods) ──

    public function getVideoAdsProperty()
    {
        return VideoAd::orderByDesc('is_active')
            ->orderBy('placement')
            ->orderByDesc('weight')
            ->get();
    }

    public function getCategoriesProperty()
    {
        return Category::active()->orderBy('name')->pluck('name', 'id')->toArray();
    }

    public function openAdForm(?int $id = null): void
    {
        if ($id) {
            $ad = VideoAd::findOrFail($id);
            $this->adFormData = [
                'name' => $ad->name,
                'type' => $ad->type,
                'placement' => $ad->placement,
                'content' => $ad->content,
                'weight' => $ad->weight,
                'is_active' => $ad->is_active,
                'category_ids' => $ad->category_ids ?? [],
                'target_roles' => $ad->target_roles ?? [],
            ];
            $this->editingAdId = $id;
        } else {
            $this->resetAdForm();
        }
        $this->showAdForm = true;
    }

    public function saveAd(): void
    {
        $rules = [
            'adFormData.name' => 'required|string|max:255',
            'adFormData.type' => 'required|in:vast,vpaid,mp4,html',
            'adFormData.placement' => 'required|in:pre_roll,mid_roll,post_roll',
            'adFormData.content' => 'required|string',
            'adFormData.weight' => 'required|integer|min:1|max:100',
            'adFormData.is_active' => 'boolean',
            'adFormData.category_ids' => 'nullable|array',
            'adFormData.target_roles' => 'nullable|array',
        ];

        $this->validate($rules);

        $data = $this->adFormData;
        $data['category_ids'] = !empty($data['category_ids']) ? array_map('intval', $data['category_ids']) : null;
        $data['target_roles'] = !empty($data['target_roles']) ? $data['target_roles'] : null;

        if ($this->editingAdId) {
            VideoAd::where('id', $this->editingAdId)->update($data);
            $message = 'Ad creative updated';
        } else {
            VideoAd::create($data);
            $message = 'Ad creative created';
        }

        $this->resetAdForm();

        Notification::make()->title($message)->success()->send();
    }

    public function toggleAdActive(int $id): void
    {
        $ad = VideoAd::findOrFail($id);
        $ad->update(['is_active' => !$ad->is_active]);

        Notification::make()
            ->title($ad->is_active ? 'Ad activated' : 'Ad deactivated')
            ->success()
            ->send();
    }

    public function deleteAd(int $id): void
    {
        VideoAd::destroy($id);

        Notification::make()->title('Ad deleted')->success()->send();
    }

    public function cancelAdForm(): void
    {
        $this->resetAdForm();
    }
}

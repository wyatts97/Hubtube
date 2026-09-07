<?php

namespace App\Filament\Pages;

use App\Filament\Clusters\Settings as SettingsCluster;
use App\Models\Setting;
use App\Support\GoogleFonts;
use App\Support\ThemeTokens;
use App\Support\Typography;
use App\Services\AdminLogger;
use Filament\Actions\Action;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;

class ThemeSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|\BackedEnum|null $navigationIcon = 'phosphor-paint-brush';

    protected static ?string $navigationLabel = 'Theme & Appearance';

    protected static ?string $cluster = SettingsCluster::class;

    protected static ?int $navigationSort = 2;

    protected string $view = 'filament.pages.site-settings';

    public ?array $data = [];

    protected static array $availableIcons = [
        'home' => 'Home',
        'trending-up' => 'Trending Up',
        'zap' => 'Zap/Lightning',
        'video' => 'Video',
        'play-circle' => 'Play Circle',
        'film' => 'Film',
        'tv' => 'TV',
        'monitor' => 'Monitor',
        'list-video' => 'List Video',
        'history' => 'History',
        'clock' => 'Clock',
        'bookmark' => 'Bookmark',
        'heart' => 'Heart',
        'star' => 'Star',
        'flame' => 'Flame',
        'sparkles' => 'Sparkles',
        'compass' => 'Compass',
        'globe' => 'Globe',
        'music' => 'Music',
        'gamepad' => 'Gamepad',
        'trophy' => 'Trophy',
        'graduation-cap' => 'Education',
        'newspaper' => 'News',
        'camera' => 'Camera',
        'mic' => 'Microphone',
        'podcast' => 'Podcast',
    ];

    // Popular Google Fonts
    protected static array $googleFonts = [
        '' => 'System Default',
        'Roboto' => 'Roboto',
        'Open Sans' => 'Open Sans',
        'Lato' => 'Lato',
        'Montserrat' => 'Montserrat',
        'Oswald' => 'Oswald',
        'Raleway' => 'Raleway',
        'Poppins' => 'Poppins',
        'Ubuntu' => 'Ubuntu',
        'Nunito' => 'Nunito',
        'Playfair Display' => 'Playfair Display',
        'Merriweather' => 'Merriweather',
        'PT Sans' => 'PT Sans',
        'Source Sans Pro' => 'Source Sans Pro',
        'Noto Sans' => 'Noto Sans',
        'Inter' => 'Inter',
        'Rubik' => 'Rubik',
        'Work Sans' => 'Work Sans',
        'Quicksand' => 'Quicksand',
        'Barlow' => 'Barlow',
        'Mulish' => 'Mulish',
        'Fira Sans' => 'Fira Sans',
        'Bebas Neue' => 'Bebas Neue',
        'Anton' => 'Anton',
        'Archivo Black' => 'Archivo Black',
        'Righteous' => 'Righteous',
        'Permanent Marker' => 'Permanent Marker',
        'Pacifico' => 'Pacifico',
        'Lobster' => 'Lobster',
        'Dancing Script' => 'Dancing Script',
    ];

    public function mount(): void
    {
        $this->form->fill([
            // Site Information (moved from SiteSettings)
            'site_name' => Setting::get('site_name', config('app.name')),
            'site_description' => Setting::get('site_description', ''),
            'site_keywords' => Setting::get('site_keywords', ''),
            'site_logo' => Setting::get('site_logo', ''),
            'site_logo_dark' => Setting::get('site_logo_dark', ''),
            'site_favicon' => Setting::get('site_favicon', ''),
            'primary_color' => Setting::get('primary_color', '#ef4444'),

            // Site Title Settings
            'site_title' => Setting::get('site_title', 'HubTube'),
            'site_title_font' => Setting::get('site_title_font', ''),
            'site_title_size' => Setting::get('site_title_size', 20),
            'site_title_color' => Setting::get('site_title_color', ''),

            // Which theme visitors get. 'user' shows the header switcher.
            'theme_mode' => ThemeTokens::mode(),

            // Palette defaults come from ThemeTokens so this page, the frontend
            // stylesheet and the first-paint <style> block cannot drift apart.
            ...self::paletteFormDefaults('dark'),
            ...self::paletteFormDefaults('light'),

            // Navigation Icons
            'nav_home_icon' => Setting::get('nav_home_icon', 'home'),
            'nav_home_color' => Setting::get('nav_home_color', ''),
            'nav_trending_icon' => Setting::get('nav_trending_icon', 'trending-up'),
            'nav_trending_color' => Setting::get('nav_trending_color', ''),
            'nav_playlists_icon' => Setting::get('nav_playlists_icon', 'list-video'),
            'nav_playlists_color' => Setting::get('nav_playlists_color', ''),
            'nav_history_icon' => Setting::get('nav_history_icon', 'history'),
            'nav_history_color' => Setting::get('nav_history_color', ''),

            // Global Icon Settings
            'icon_color_mode' => Setting::get('icon_color_mode', 'inherit'),
            'icon_global_color' => Setting::get('icon_global_color', ''),
            'icon_global_color_dark' => Setting::get('icon_global_color_dark', ''),

            // Age Verification Modal Settings
            'age_overlay_color' => Setting::get('age_overlay_color', 'rgba(0, 0, 0, 0.85)'),
            'age_overlay_blur' => Setting::get('age_overlay_blur', 8),
            'age_show_logo' => Setting::get('age_show_logo', false),
            'age_logo_url' => Setting::get('age_logo_url', ''),
            'age_header_text' => Setting::get('age_header_text', 'Age Verification Required'),
            'age_header_size' => Setting::get('age_header_size', 28),
            'age_header_color' => Setting::get('age_header_color', ''),
            'age_description_text' => Setting::get('age_description_text', 'This website contains age-restricted content. You must be at least 18 years old to enter.'),
            'age_disclaimer_text' => Setting::get('age_disclaimer_text', 'By clicking "{confirm}", you confirm that you are at least 18 years of age and consent to viewing adult content.'),
            'age_confirm_text' => Setting::get('age_confirm_text', 'I am 18 or older'),
            'age_decline_text' => Setting::get('age_decline_text', 'Exit'),
            'age_terms_text' => Setting::get('age_terms_text', 'By entering this site, you agree to our'),
            'age_button_color' => Setting::get('age_button_color', ''),
            'age_text_color' => Setting::get('age_text_color', ''),
            'age_font_family' => Setting::get('age_font_family', ''),

            // Category Title Typography
            'category_title_font' => Setting::get('category_title_font', ''),
            'category_title_size' => Setting::get('category_title_size', 18),
            'category_title_color' => Setting::get('category_title_color', '#ffffff'),
            'category_title_opacity' => Setting::get('category_title_opacity', 90),

            // Footer Settings
            'footer_logo_match_site' => Setting::get('footer_logo_match_site', false),
            'footer_logo_url' => Setting::get('footer_logo_url', ''),

            // Video Card Customization
            'video_card_show_avatar' => Setting::get('video_card_show_avatar', false),
            'video_card_show_rating' => Setting::get('video_card_show_rating', true),
            'video_card_show_quality' => Setting::get('video_card_show_quality', true),
            'video_card_show_tags' => Setting::get('video_card_show_tags', true),
            'video_card_show_uploader' => Setting::get('video_card_show_uploader', true),
            'video_card_show_views' => Setting::get('video_card_show_views', true),
            'video_card_show_duration' => Setting::get('video_card_show_duration', true),
            'video_card_show_timestamp' => Setting::get('video_card_show_timestamp', true),
            // Typography. Empty family means "use the theme default" — see
            // App\Support\Typography::SLOTS for what those defaults are.
            'font_body_family' => Setting::get('font_body_family', ''),
            'font_body_weight' => Setting::get('font_body_weight', 400),
            'font_display_family' => Setting::get('font_display_family', ''),
            'font_display_weight' => Setting::get('font_display_weight', 700),
            'video_card_title_weight' => Setting::get('video_card_title_weight', 600),
            'video_card_meta_weight' => Setting::get('video_card_meta_weight', 400),
            'video_card_title_font' => Setting::get('video_card_title_font', ''),
            'video_card_title_size' => Setting::get('video_card_title_size', 14),
            'video_card_title_color' => Setting::get('video_card_title_color', ''),
            'video_card_title_lines' => Setting::get('video_card_title_lines', 2),
            'video_card_meta_font' => Setting::get('video_card_meta_font', ''),
            'video_card_meta_size' => Setting::get('video_card_meta_size', 13),
            'video_card_meta_color' => Setting::get('video_card_meta_color', ''),
            'video_card_border_radius' => Setting::get('video_card_border_radius', 6),
            'video_grid_density' => Setting::get('video_grid_density', 'dense'),
            'mobile_video_grid' => Setting::get('mobile_video_grid', '1'),

            // Progress Bar
            'progress_bar_color' => Setting::get('progress_bar_color', ''),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('Theme Settings')
                    ->tabs([
                        Tab::make('Identity & Appearance')
                            ->icon('phosphor-identification-card')
                            ->schema([
                                Section::make('Site Information')
                                    ->schema([
                                        TextInput::make('site_name')
                                            ->label('Site Name')
                                            ->required()
                                            ->maxLength(100),
                                        Textarea::make('site_description')
                                            ->label('Site Description')
                                            ->rows(3)
                                            ->maxLength(500),
                                        TextInput::make('site_keywords')
                                            ->label('SEO Keywords')
                                            ->placeholder('comma, separated, keywords'),
                                        TextInput::make('primary_color')
                                            ->label('Primary Color')
                                            ->type('color'),
                                    ])->columns(2),

                                Section::make('Site Logo')
                                    ->description('Upload your site logo and favicon. These are displayed in the header, browser tab, and PWA icon.')
                                    ->schema([
                                        FileUpload::make('site_logo')
                                            ->label('Site Logo')
                                            ->image()
                                            ->disk('public')
                                            ->directory('logos')
                                            ->visibility('public')
                                            ->imageResizeMode('contain')
                                            ->imageCropAspectRatio(null)
                                            ->helperText('Recommended: PNG with transparency, max height 40px display size'),
                                        // A single logo cannot work on both grounds: dark-ground
                                        // artwork (light lettering) disappears on the light theme
                                        // and vice versa. Optional — falls back to the logo above.
                                        FileUpload::make('site_logo_dark')
                                            ->label('Site Logo (dark mode)')
                                            ->image()
                                            ->disk('public')
                                            ->directory('logos')
                                            ->visibility('public')
                                            ->imageResizeMode('contain')
                                            ->imageCropAspectRatio(null)
                                            ->helperText('Optional. Shown when the site is in dark mode — use light-coloured artwork. Leave empty to use the logo above in both themes.'),
                                        FileUpload::make('site_favicon')
                                            ->label('Favicon')
                                            ->acceptedFileTypes(['image/x-icon', 'image/png', 'image/svg+xml', 'image/vnd.microsoft.icon'])
                                            ->disk('public')
                                            ->directory('logos')
                                            ->visibility('public')
                                            ->helperText('Upload a .ico, .png, or .svg favicon (recommended: 32x32 or 64x64)'),
                                    ])->columns(2),

                                Section::make('Footer Logo')
                                    ->description('Display a logo in the footer above the legal links. Leave empty to show the site title text instead.')
                                    ->schema([
                                        Toggle::make('footer_logo_match_site')
                                            ->label('Use Site Logo for Footer')
                                            ->helperText('When enabled, the footer will use the same logo as the site header')
                                            ->reactive()
                                            ->columnSpanFull(),
                                        FileUpload::make('footer_logo_url')
                                            ->label('Footer Logo')
                                            ->image()
                                            ->disk('public')
                                            ->directory('logos')
                                            ->visibility('public')
                                            ->helperText('Upload a separate footer logo, or leave empty to show the site title')
                                            ->visible(fn ($get) => ! $get('footer_logo_match_site')),
                                    ]),

                                Section::make('Site Title Customization')
                                    ->description('Customize your site title appearance with Google Fonts')
                                    ->schema([
                                        TextInput::make('site_title')
                                            ->label('Site Title')
                                            ->placeholder('Enter your site title')
                                            ->default('HubTube')
                                            ->live(onBlur: true)
                                            ->columnSpanFull(),
                                        Grid::make(3)->schema([
                                            Select::make('site_title_font')
                                                ->label('Font Family')
                                                ->options(self::$googleFonts)
                                                ->searchable()
                                                ->live()
                                                ->placeholder('Select a font'),
                                            TextInput::make('site_title_size')
                                                ->label('Font Size (px)')
                                                ->numeric()
                                                ->default(20)
                                                ->minValue(12)
                                                ->maxValue(48)
                                                ->live(onBlur: true),
                                            ColorPicker::make('site_title_color')
                                                ->label('Title Color')
                                                ->live(),
                                        ]),
                                        Section::make('Preview')
                                            ->schema([
                                                View::make('filament.components.site-title-preview'),
                                            ]),
                                    ]),

                                Section::make('Page Loading Bar')
                                    ->description('A thin progress bar shown at the top of the page during navigation. Leave empty to use the accent color.')
                                    ->schema([
                                        ColorPicker::make('progress_bar_color')
                                            ->label('Progress Bar Color'),
                                    ]),
                            ]),

                        Tab::make('Theme')
                            ->icon('phosphor-moon')
                            ->schema([
                                Section::make('Theme Mode')
                                    ->description('Which theme visitors see, and whether they can switch.')
                                    ->schema([
                                        Select::make('theme_mode')
                                            ->label('Theme mode')
                                            ->options([
                                                'user' => 'Visitor chooses (dark by default)',
                                                'dark' => 'Always dark',
                                                'light' => 'Always light',
                                            ])
                                            ->default('user')
                                            ->native(false)
                                            ->helperText('"Visitor chooses" shows a light/dark switch in the header and remembers each visitor choice. The other options hide the switch and pin the whole site to one theme.'),
                                    ]),

                                Section::make('Dark Mode Colors')
                                    ->description('Leave a field empty to use the built-in default.')
                                    ->schema(self::paletteFields('dark')),

                                Section::make('Light Mode Colors')
                                    ->description('Light mode is designed around a warm off-white ground with white cards, so thumbnails keep a visible edge. If you change the accent, pick one dark enough to read as text on white — the bright red used for badges will fail contrast at small sizes.')
                                    ->schema(self::paletteFields('light')),
                            ]),

                        Tab::make('Typography')
                            ->icon('phosphor-text-aa')
                            ->schema([
                                Section::make('Body & interface')
                                    ->description('Metadata, buttons, form labels, navigation — everything that is not a heading. Leave empty to use the theme default (Archivo).')
                                    ->schema([
                                        Grid::make(3)->schema([
                                            self::fontSelect('font_body_family', 'Font family', 'font_body_weight')
                                                ->columnSpan(2),
                                            self::fontWeightSelect('font_body_weight', 'font_body_family', 400),
                                        ]),
                                        View::make('filament.partials.font-preview')
                                            ->viewData(['slot' => 'body']),
                                    ]),

                                Section::make('Headings & titles')
                                    ->description('Page titles, section headers, video card titles and the wordmark. This is the choice that most defines how the site reads. Leave empty for the theme default (Archivo Narrow).')
                                    ->schema([
                                        Grid::make(3)->schema([
                                            self::fontSelect('font_display_family', 'Font family', 'font_display_weight')
                                                ->columnSpan(2),
                                            self::fontWeightSelect('font_display_weight', 'font_display_family', 700),
                                        ]),
                                        View::make('filament.partials.font-preview')
                                            ->viewData(['slot' => 'display']),
                                    ]),

                                Section::make('Video card overrides')
                                    ->description('Optional. Overrides the fonts above for video card text only — useful when a dense grid needs a narrower face than the rest of the site. Leave empty to inherit.')
                                    ->schema([
                                        Grid::make(3)->schema([
                                            self::fontSelect('video_card_title_font', 'Card title font', 'video_card_title_weight')
                                                ->columnSpan(2),
                                            self::fontWeightSelect('video_card_title_weight', 'video_card_title_font', 600),
                                        ]),
                                        Grid::make(3)->schema([
                                            self::fontSelect('video_card_meta_font', 'Card metadata font', 'video_card_meta_weight')
                                                ->columnSpan(2),
                                            self::fontWeightSelect('video_card_meta_weight', 'video_card_meta_font', 400),
                                        ]),
                                    ]),
                            ]),

                        Tab::make('Navigation Icons')
                            ->icon('phosphor-squares-four')
                            ->schema([
                                Section::make('Global Icon Settings')
                                    ->schema([
                                        Select::make('icon_color_mode')
                                            ->label('Icon Color Mode')
                                            ->options([
                                                'inherit' => 'Inherit from Theme',
                                                'global' => 'Use Global Color',
                                                'individual' => 'Individual Colors',
                                            ])
                                            ->reactive(),
                                        ColorPicker::make('icon_global_color_dark')
                                            ->label('Global Icon Color')
                                            ->visible(fn ($get) => $get('icon_color_mode') === 'global'),
                                    ])->columns(3),

                                Section::make('Main Navigation Icons')
                                    ->description('Customize icons for the main navigation menu')
                                    ->schema([
                                        Grid::make(2)->schema([
                                            Select::make('nav_home_icon')
                                                ->label('Home Icon')
                                                ->options(self::$availableIcons),
                                            ColorPicker::make('nav_home_color')
                                                ->label('Home Icon Color')
                                                ->visible(fn ($get) => $get('icon_color_mode') === 'individual'),
                                        ]),
                                        Grid::make(2)->schema([
                                            Select::make('nav_trending_icon')
                                                ->label('Trending Icon')
                                                ->options(self::$availableIcons),
                                            ColorPicker::make('nav_trending_color')
                                                ->label('Trending Icon Color')
                                                ->visible(fn ($get) => $get('icon_color_mode') === 'individual'),
                                        ]),
                                    ]),

                                Section::make('Library Navigation Icons')
                                    ->schema([
                                        Grid::make(2)->schema([
                                            Select::make('nav_playlists_icon')
                                                ->label('Playlists Icon')
                                                ->options(self::$availableIcons),
                                            ColorPicker::make('nav_playlists_color')
                                                ->label('Playlists Icon Color')
                                                ->visible(fn ($get) => $get('icon_color_mode') === 'individual'),
                                        ]),
                                        Grid::make(2)->schema([
                                            Select::make('nav_history_icon')
                                                ->label('History Icon')
                                                ->options(self::$availableIcons),
                                            ColorPicker::make('nav_history_color')
                                                ->label('History Icon Color')
                                                ->visible(fn ($get) => $get('icon_color_mode') === 'individual'),
                                        ]),
                                    ]),
                            ]),

                        Tab::make('Category Pages')
                            ->icon('phosphor-squares-four')
                            ->schema([
                                Section::make('Category Title Typography')
                                    ->description('Customize how category names appear as overlays on the category browse page thumbnails')
                                    ->schema([
                                        Grid::make(2)->schema([
                                            Select::make('category_title_font')
                                                ->label('Font Family')
                                                ->options(self::$googleFonts)
                                                ->searchable()
                                                ->placeholder('System Default'),
                                            TextInput::make('category_title_size')
                                                ->label('Font Size (px)')
                                                ->numeric()
                                                ->default(18)
                                                ->minValue(10)
                                                ->maxValue(48),
                                        ]),
                                        Grid::make(2)->schema([
                                            ColorPicker::make('category_title_color')
                                                ->label('Text Color')
                                                ->default('#ffffff'),
                                            TextInput::make('category_title_opacity')
                                                ->label('Text Opacity (%)')
                                                ->numeric()
                                                ->default(90)
                                                ->minValue(10)
                                                ->maxValue(100)
                                                ->suffix('%'),
                                        ]),
                                    ]),
                            ]),

                        Tab::make('Video Cards')
                            ->icon('phosphor-stack')
                            ->schema([
                                Section::make('Visibility')
                                    ->description('Choose which elements to show on video cards across the site')
                                    ->schema([
                                        Grid::make(3)->schema([
                                            Toggle::make('video_card_show_avatar')
                                                ->label('Show Avatar')
                                                ->default(false)
                                                ->helperText('Off by default in the dense grid — the space goes to the rating bar and tags.'),
                                            Toggle::make('video_card_show_rating')
                                                ->label('Show Rating Bar')
                                                ->default(true)
                                                ->helperText('Like ratio. Hidden automatically until a video has at least 5 votes.'),
                                            Toggle::make('video_card_show_quality')
                                                ->label('Show Quality Badge')
                                                ->default(true)
                                                ->helperText('HD / 4K marker, from the transcoded renditions.'),
                                            Toggle::make('video_card_show_tags')
                                                ->label('Show Tag Chips')
                                                ->default(true)
                                                ->helperText('Up to three tags per card.'),
                                            Toggle::make('video_card_show_uploader')
                                                ->label('Show Uploader Name')
                                                ->default(true),
                                            Toggle::make('video_card_show_views')
                                                ->label('Show View Count')
                                                ->default(true),
                                            Toggle::make('video_card_show_duration')
                                                ->label('Show Duration Badge')
                                                ->default(true),
                                            Toggle::make('video_card_show_timestamp')
                                                ->label('Show Time Ago')
                                                ->default(true),
                                        ]),
                                    ]),

                                Section::make('Title Styling')
                                    ->description('Size, colour and clamping for the video title. The font family moved to the Typography tab.')
                                    ->schema([
                                        Grid::make(2)->schema([
                                            TextInput::make('video_card_title_size')
                                                ->label('Font Size (px)')
                                                ->numeric()
                                                ->default(14)
                                                ->minValue(10)
                                                ->maxValue(24)
                                                ->suffix('px'),
                                            TextInput::make('video_card_title_color')
                                                ->label('Title Color')
                                                ->placeholder('#ffffff or leave empty for theme default'),
                                            TextInput::make('video_card_title_lines')
                                                ->label('Max Lines')
                                                ->numeric()
                                                ->default(2)
                                                ->minValue(1)
                                                ->maxValue(4)
                                                ->helperText('Number of lines before truncating'),
                                        ]),
                                    ]),

                                Section::make('Meta Text Styling')
                                    ->description('Size and colour for the uploader name, views and timestamp. The font family moved to the Typography tab.')
                                    ->schema([
                                        Grid::make(2)->schema([
                                            TextInput::make('video_card_meta_size')
                                                ->label('Font Size (px)')
                                                ->numeric()
                                                ->default(13)
                                                ->minValue(10)
                                                ->maxValue(20)
                                                ->suffix('px'),
                                            TextInput::make('video_card_meta_color')
                                                ->label('Meta Color')
                                                ->placeholder('#a3a3a3 or leave empty for theme default'),
                                        ]),
                                    ]),

                                Section::make('Card Shape')
                                    ->schema([
                                        TextInput::make('video_card_border_radius')
                                            ->label('Thumbnail Border Radius (px)')
                                            ->numeric()
                                            ->default(6)
                                            ->minValue(0)
                                            ->maxValue(24)
                                            ->suffix('px')
                                            ->helperText('0 = square corners, 6 = theme default'),
                                    ]),

                                Section::make('Grid Density')
                                    ->description('How many columns the video grid uses on larger screens')
                                    ->schema([
                                        Select::make('video_grid_density')
                                            ->label('Desktop grid density')
                                            ->options([
                                                'dense' => 'Dense — 5 columns (6 on very wide screens)',
                                                'comfortable' => 'Comfortable — 4 columns, larger thumbnails',
                                            ])
                                            ->default('dense')
                                            ->native(false),
                                    ]),

                                Section::make('Mobile Grid Layout')
                                    ->description('Control how video cards are displayed on mobile devices')
                                    ->schema([
                                        Select::make('mobile_video_grid')
                                            ->label('Mobile Video Grid')
                                            ->options([
                                                '1' => 'Single column (1 per row)',
                                                '2' => 'Two columns (2x2 grid)',
                                            ])
                                            ->default('1')
                                            ->helperText('Ad spaces in the grid will remain full-width (300px) between rows regardless of this setting'),
                                    ]),
                            ]),

                        Tab::make('Age Verification')
                            ->icon('phosphor-shield-check')
                            ->schema([
                                Section::make('Overlay Settings')
                                    ->description('Customize the modal overlay appearance')
                                    ->schema([
                                        Grid::make(2)->schema([
                                            TextInput::make('age_overlay_color')
                                                ->label('Overlay Color')
                                                ->placeholder('rgba(0, 0, 0, 0.85)')
                                                ->helperText('Use rgba for transparency, e.g., rgba(0, 0, 0, 0.85)'),
                                            TextInput::make('age_overlay_blur')
                                                ->label('Overlay Blur (px)')
                                                ->numeric()
                                                ->default(8)
                                                ->minValue(0)
                                                ->maxValue(20),
                                        ]),
                                    ]),

                                Section::make('Logo & Branding')
                                    ->schema([
                                        Toggle::make('age_show_logo')
                                            ->label('Show Site Logo')
                                            ->helperText('When enabled, displays the site logo (set in Site Logo section above) instead of the shield icon.'),
                                    ]),

                                Section::make('Typography')
                                    ->schema([
                                        Select::make('age_font_family')
                                            ->label('Font Family')
                                            ->options(self::$googleFonts)
                                            ->searchable()
                                            ->placeholder('System Default'),
                                        Grid::make(2)->schema([
                                            TextInput::make('age_header_size')
                                                ->label('Header Font Size (px)')
                                                ->numeric()
                                                ->default(28)
                                                ->minValue(16)
                                                ->maxValue(48),
                                            ColorPicker::make('age_header_color')
                                                ->label('Header Color'),
                                        ]),
                                        ColorPicker::make('age_text_color')
                                            ->label('Body Text Color'),
                                        ColorPicker::make('age_button_color')
                                            ->label('Button Color'),
                                    ]),

                                Section::make('Content')
                                    ->description('Customize all text displayed in the modal')
                                    ->schema([
                                        TextInput::make('age_header_text')
                                            ->label('Header Text')
                                            ->default('Age Verification Required')
                                            ->columnSpanFull(),
                                        Textarea::make('age_description_text')
                                            ->label('Description Text')
                                            ->default('This website contains age-restricted content. You must be at least 18 years old to enter.')
                                            ->rows(2)
                                            ->columnSpanFull(),
                                        Textarea::make('age_disclaimer_text')
                                            ->label('Disclaimer Text')
                                            ->default('By clicking "{confirm}", you confirm that you are at least 18 years of age and consent to viewing adult content.')
                                            ->helperText('Use {confirm} to insert the confirm button text')
                                            ->rows(2)
                                            ->columnSpanFull(),
                                        Grid::make(2)->schema([
                                            TextInput::make('age_confirm_text')
                                                ->label('Confirm Button Text')
                                                ->default('I am 18 or older'),
                                            TextInput::make('age_decline_text')
                                                ->label('Decline Button Text')
                                                ->default('Exit'),
                                        ]),
                                        TextInput::make('age_terms_text')
                                            ->label('Terms Text Prefix')
                                            ->default('By entering this site, you agree to our')
                                            ->columnSpanFull(),
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
                ->label('Save Settings')
                ->icon('phosphor-check')
                ->action('save'),
        ];
    }

    /**
     * Human labels for the seven colours an operator can override per theme.
     * The rest of the palette (hover, subtle, elevated, borders) is derived in
     * ThemeTokens so a custom accent still produces a coherent ramp.
     */
    protected const PALETTE_LABELS = [
        'bgPrimary' => 'Page background',
        'bgSecondary' => 'Header & sidebar',
        'bgCard' => 'Card background',
        'accent' => 'Accent',
        'textPrimary' => 'Primary text',
        'textSecondary' => 'Secondary text',
        'border' => 'Borders',
    ];

    /** Colour pickers for one theme, laid out three to a row. */
    protected static function paletteFields(string $mode): array
    {
        $defaults = ThemeTokens::defaults($mode);

        $pickers = [];
        foreach (self::PALETTE_LABELS as $key => $label) {
            $pickers[] = ColorPicker::make(ThemeTokens::settingKey($mode, $key))
                ->label($label)
                ->placeholder($defaults[$key]);
        }

        return [
            Grid::make(3)->schema(array_slice($pickers, 0, 3)),
            Grid::make(3)->schema(array_slice($pickers, 3, 3)),
            Grid::make(3)->schema(array_slice($pickers, 6)),
        ];
    }

    /** Current stored values for one theme, keyed by settings-table key. */
    protected static function paletteFormDefaults(string $mode): array
    {
        $defaults = [];

        foreach (array_keys(self::PALETTE_LABELS) as $key) {
            $settingKey = ThemeTokens::settingKey($mode, $key);
            $defaults[$settingKey] = Setting::get($settingKey, '');
        }

        return $defaults;
    }

    /**
     * Font family picker that renders each option in its own typeface.
     *
     * Options are grouped by category and carry inline `font-family`, which
     * Filament will only render because of allowHtml(). The faces themselves are
     * pulled into the admin page by the Bunny Fonts <link> in
     * resources/views/filament/pages/site-settings.blade.php — without that the
     * dropdown silently falls back to the panel font and every option looks
     * identical.
     *
     * `$weightField` is the sibling weight Select to keep in step: families
     * publish different weight sets, so choosing a new family has to re-scope
     * the weights on offer and drop a stored value the new family lacks.
     */
    protected static function fontSelect(string $name, string $label, ?string $weightField = null): Select
    {
        $select = Select::make($name)
            ->label($label)
            ->options(GoogleFonts::groupedOptions())
            ->allowHtml()
            ->searchable()
            ->native(false)
            ->placeholder('Theme default')
            ->live();

        if ($weightField !== null) {
            $select->afterStateUpdated(function ($state, callable $set, $get) use ($weightField) {
                // Keep the chosen weight if the new family publishes it, else
                // snap to the nearest one it does.
                $set($weightField, GoogleFonts::resolveWeight($state, $get($weightField)));
            });
        }

        return $select;
    }

    /** Weight picker scoped to whatever family the sibling field currently holds. */
    protected static function fontWeightSelect(string $name, string $familyField, int $default): Select
    {
        return Select::make($name)
            ->label('Weight')
            ->options(fn ($get) => GoogleFonts::weightOptions($get($familyField)))
            ->default($default)
            ->native(false)
            ->live()
            // A family with a single weight has nothing to choose.
            ->disabled(fn ($get) => count(GoogleFonts::weightOptions($get($familyField))) <= 1);
    }

    public function save(): void
    {
        $data = $this->form->getState();

        // If footer_logo_match_site is on, copy site_logo path to footer_logo_url
        if (! empty($data['footer_logo_match_site'])) {
            $data['footer_logo_url'] = $data['site_logo'] ?? '';
        }

        // These keys originated from SiteSettings and must stay in the 'general' group
        $generalKeys = ['site_name', 'site_description', 'site_keywords', 'site_logo', 'site_logo_dark', 'site_favicon', 'primary_color'];

        foreach ($data as $key => $value) {
            $type = match (true) {
                is_bool($value) => 'boolean',
                is_int($value) => 'integer',
                is_array($value) => 'array',
                default => 'string',
            };

            $group = in_array($key, $generalKeys) ? 'general' : 'theme';
            Setting::set($key, $value, $group, $type);
        }

        AdminLogger::settingsSaved('Theme', array_keys($data));

        Notification::make()
            ->title('Theme settings saved successfully')
            ->success()
            ->send();
    }
}

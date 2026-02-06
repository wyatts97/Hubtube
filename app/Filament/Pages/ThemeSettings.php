<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Tabs;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class ThemeSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-paint-brush';
    protected static ?string $navigationLabel = 'Theme & Appearance';
    protected static ?string $navigationGroup = 'Settings';
    protected static ?int $navigationSort = 2;
    protected static string $view = 'filament.pages.site-settings';

    public ?array $data = [];

    protected static array $availableIcons = [
        'home' => 'Home',
        'trending-up' => 'Trending Up',
        'zap' => 'Zap/Lightning',
        'radio' => 'Radio/Live',
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
            // Site Title Settings
            'site_title' => Setting::get('site_title', 'HubTube'),
            'site_title_font' => Setting::get('site_title_font', ''),
            'site_title_size' => Setting::get('site_title_size', 20),
            'site_title_color' => Setting::get('site_title_color', ''),
            
            // Theme Mode
            'theme_mode' => Setting::get('theme_mode', 'dark'),
            'allow_user_theme_toggle' => Setting::get('allow_user_theme_toggle', true),
            
            // Dark Mode Colors
            'dark_bg_primary' => Setting::get('dark_bg_primary', '#0a0a0a'),
            'dark_bg_secondary' => Setting::get('dark_bg_secondary', '#171717'),
            'dark_bg_card' => Setting::get('dark_bg_card', '#1f1f1f'),
            'dark_accent_color' => Setting::get('dark_accent_color', '#ef4444'),
            'dark_text_primary' => Setting::get('dark_text_primary', '#ffffff'),
            'dark_text_secondary' => Setting::get('dark_text_secondary', '#a3a3a3'),
            'dark_border_color' => Setting::get('dark_border_color', '#262626'),
            
            // Light Mode Colors
            'light_bg_primary' => Setting::get('light_bg_primary', '#ffffff'),
            'light_bg_secondary' => Setting::get('light_bg_secondary', '#f5f5f5'),
            'light_bg_card' => Setting::get('light_bg_card', '#ffffff'),
            'light_accent_color' => Setting::get('light_accent_color', '#dc2626'),
            'light_text_primary' => Setting::get('light_text_primary', '#171717'),
            'light_text_secondary' => Setting::get('light_text_secondary', '#525252'),
            'light_border_color' => Setting::get('light_border_color', '#e5e5e5'),
            
            // Navigation Icons
            'nav_home_icon' => Setting::get('nav_home_icon', 'home'),
            'nav_home_color' => Setting::get('nav_home_color', ''),
            'nav_trending_icon' => Setting::get('nav_trending_icon', 'trending-up'),
            'nav_trending_color' => Setting::get('nav_trending_color', ''),
            'nav_shorts_icon' => Setting::get('nav_shorts_icon', 'zap'),
            'nav_shorts_color' => Setting::get('nav_shorts_color', ''),
            'nav_live_icon' => Setting::get('nav_live_icon', 'radio'),
            'nav_live_color' => Setting::get('nav_live_color', ''),
            'nav_playlists_icon' => Setting::get('nav_playlists_icon', 'list-video'),
            'nav_playlists_color' => Setting::get('nav_playlists_color', ''),
            'nav_history_icon' => Setting::get('nav_history_icon', 'history'),
            'nav_history_color' => Setting::get('nav_history_color', ''),
            
            // Global Icon Settings
            'icon_color_mode' => Setting::get('icon_color_mode', 'inherit'),
            'icon_global_color' => Setting::get('icon_global_color', ''),
            
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
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Tabs::make('Theme Settings')
                    ->tabs([
                        Tabs\Tab::make('Site Title')
                            ->icon('heroicon-o-pencil')
                            ->schema([
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
                                                \Filament\Forms\Components\View::make('filament.components.site-title-preview'),
                                            ]),
                                    ]),
                            ]),
                        
                        Tabs\Tab::make('Theme Mode')
                            ->icon('heroicon-o-sun')
                            ->schema([
                                Section::make('Default Theme')
                                    ->description('Configure the default theme mode for your site')
                                    ->schema([
                                        Select::make('theme_mode')
                                            ->label('Default Theme Mode')
                                            ->options([
                                                'dark' => 'Dark Mode',
                                                'light' => 'Light Mode',
                                                'system' => 'System Preference',
                                            ])
                                            ->required(),
                                        Toggle::make('allow_user_theme_toggle')
                                            ->label('Allow Users to Toggle Theme')
                                            ->helperText('Let users switch between light and dark mode'),
                                    ])->columns(2),
                            ]),
                        
                        Tabs\Tab::make('Dark Mode')
                            ->icon('heroicon-o-moon')
                            ->schema([
                                Section::make('Dark Mode Colors')
                                    ->description('Customize colors for dark mode')
                                    ->schema([
                                        Grid::make(3)->schema([
                                            ColorPicker::make('dark_bg_primary')
                                                ->label('Primary Background'),
                                            ColorPicker::make('dark_bg_secondary')
                                                ->label('Secondary Background'),
                                            ColorPicker::make('dark_bg_card')
                                                ->label('Card Background'),
                                        ]),
                                        Grid::make(3)->schema([
                                            ColorPicker::make('dark_accent_color')
                                                ->label('Accent Color'),
                                            ColorPicker::make('dark_text_primary')
                                                ->label('Primary Text'),
                                            ColorPicker::make('dark_text_secondary')
                                                ->label('Secondary Text'),
                                        ]),
                                        ColorPicker::make('dark_border_color')
                                            ->label('Border Color'),
                                    ]),
                            ]),
                        
                        Tabs\Tab::make('Light Mode')
                            ->icon('heroicon-o-sun')
                            ->schema([
                                Section::make('Light Mode Colors')
                                    ->description('Customize colors for light mode')
                                    ->schema([
                                        Grid::make(3)->schema([
                                            ColorPicker::make('light_bg_primary')
                                                ->label('Primary Background'),
                                            ColorPicker::make('light_bg_secondary')
                                                ->label('Secondary Background'),
                                            ColorPicker::make('light_bg_card')
                                                ->label('Card Background'),
                                        ]),
                                        Grid::make(3)->schema([
                                            ColorPicker::make('light_accent_color')
                                                ->label('Accent Color'),
                                            ColorPicker::make('light_text_primary')
                                                ->label('Primary Text'),
                                            ColorPicker::make('light_text_secondary')
                                                ->label('Secondary Text'),
                                        ]),
                                        ColorPicker::make('light_border_color')
                                            ->label('Border Color'),
                                    ]),
                            ]),
                        
                        Tabs\Tab::make('Navigation Icons')
                            ->icon('heroicon-o-squares-2x2')
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
                                        ColorPicker::make('icon_global_color')
                                            ->label('Global Icon Color')
                                            ->visible(fn ($get) => $get('icon_color_mode') === 'global'),
                                    ])->columns(2),
                                
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
                                        Grid::make(2)->schema([
                                            Select::make('nav_shorts_icon')
                                                ->label('Shorts Icon')
                                                ->options(self::$availableIcons),
                                            ColorPicker::make('nav_shorts_color')
                                                ->label('Shorts Icon Color')
                                                ->visible(fn ($get) => $get('icon_color_mode') === 'individual'),
                                        ]),
                                        Grid::make(2)->schema([
                                            Select::make('nav_live_icon')
                                                ->label('Live Icon')
                                                ->options(self::$availableIcons),
                                            ColorPicker::make('nav_live_color')
                                                ->label('Live Icon Color')
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
                        
                        Tabs\Tab::make('Category Pages')
                            ->icon('heroicon-o-rectangle-group')
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

                        Tabs\Tab::make('Age Verification')
                            ->icon('heroicon-o-shield-check')
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
                                            ->helperText('Display your site logo instead of the shield icon'),
                                        TextInput::make('age_logo_url')
                                            ->label('Logo URL')
                                            ->placeholder('/images/logo.png')
                                            ->visible(fn ($get) => $get('age_show_logo')),
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
                                        \Filament\Forms\Components\Textarea::make('age_description_text')
                                            ->label('Description Text')
                                            ->default('This website contains age-restricted content. You must be at least 18 years old to enter.')
                                            ->rows(2)
                                            ->columnSpanFull(),
                                        \Filament\Forms\Components\Textarea::make('age_disclaimer_text')
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

    public function save(): void
    {
        $data = $this->form->getState();

        foreach ($data as $key => $value) {
            $type = match (true) {
                is_bool($value) => 'boolean',
                is_int($value) => 'integer',
                is_array($value) => 'array',
                default => 'string',
            };

            Setting::set($key, $value, 'theme', $type);
        }

        Notification::make()
            ->title('Theme settings saved successfully')
            ->success()
            ->send();
    }
}

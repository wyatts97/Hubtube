<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Tabs;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class SiteSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';
    protected static ?string $navigationLabel = 'Site Settings';
    protected static ?string $navigationGroup = 'Settings';
    protected static ?int $navigationSort = 1;
    protected static string $view = 'filament.pages.site-settings';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'site_name' => Setting::get('site_name', config('app.name')),
            'site_description' => Setting::get('site_description', ''),
            'site_keywords' => Setting::get('site_keywords', ''),
            'site_logo' => Setting::get('site_logo', ''),
            'site_favicon' => Setting::get('site_favicon', ''),
            'primary_color' => Setting::get('primary_color', '#ef4444'),
            'maintenance_mode' => Setting::get('maintenance_mode', false),
            'registration_enabled' => Setting::get('registration_enabled', true),
            'email_verification_required' => Setting::get('email_verification_required', true),
            'age_verification_required' => Setting::get('age_verification_required', true),
            'minimum_age' => Setting::get('minimum_age', 18),
            'max_upload_size_free' => Setting::get('max_upload_size_free', 500),
            'max_upload_size_pro' => Setting::get('max_upload_size_pro', 5000),
            'max_daily_uploads_free' => Setting::get('max_daily_uploads_free', 5),
            'max_daily_uploads_pro' => Setting::get('max_daily_uploads_pro', 50),
            'ffmpeg_enabled' => Setting::get('ffmpeg_enabled', true),
            'animated_previews_enabled' => Setting::get('animated_previews_enabled', true),
            'ffmpeg_path' => Setting::get('ffmpeg_path', ''),
            'ffprobe_path' => Setting::get('ffprobe_path', ''),
            'video_quality_preset' => Setting::get('video_quality_preset', 'medium'),
            'multi_resolution_enabled' => Setting::get('multi_resolution_enabled', true),
            'enabled_resolutions' => Setting::get('enabled_resolutions', ['360p', '480p', '720p']),
            'generate_hls' => Setting::get('generate_hls', true),
            'watermark_enabled' => Setting::get('watermark_enabled', false),
            'watermark_image' => Setting::get('watermark_image', ''),
            'watermark_position' => Setting::get('watermark_position', 'bottom-right'),
            'watermark_opacity' => Setting::get('watermark_opacity', 70),
            'watermark_scale' => Setting::get('watermark_scale', 15),
            'watermark_padding' => Setting::get('watermark_padding', 10),
            'video_auto_approve' => Setting::get('video_auto_approve', false),
            'comments_enabled' => Setting::get('comments_enabled', true),
            'comments_require_approval' => Setting::get('comments_require_approval', false),
            'google_analytics_id' => Setting::get('google_analytics_id', ''),
            'custom_head_scripts' => Setting::get('custom_head_scripts', ''),
            'custom_footer_scripts' => Setting::get('custom_footer_scripts', ''),
            'infinite_scroll_enabled' => Setting::get('infinite_scroll_enabled', false),
            'videos_per_page' => Setting::get('videos_per_page', 24),
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Tabs::make('Settings')
                    ->tabs([
                        Tabs\Tab::make('General')
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
                                        TextInput::make('site_logo')
                                            ->label('Logo URL')
                                            ->url(),
                                        TextInput::make('site_favicon')
                                            ->label('Favicon URL')
                                            ->url(),
                                        TextInput::make('primary_color')
                                            ->label('Primary Color')
                                            ->type('color'),
                                    ])->columns(2),
                                Section::make('Site Status')
                                    ->schema([
                                        Toggle::make('maintenance_mode')
                                            ->label('Maintenance Mode')
                                            ->helperText('When enabled, only admins can access the site'),
                                    ]),
                                Section::make('Video Display')
                                    ->schema([
                                        Toggle::make('infinite_scroll_enabled')
                                            ->label('Enable Infinite Scroll')
                                            ->helperText('When enabled, videos load automatically as user scrolls. When disabled, traditional pagination is used.'),
                                        TextInput::make('videos_per_page')
                                            ->label('Videos Per Page/Load')
                                            ->numeric()
                                            ->minValue(6)
                                            ->maxValue(48)
                                            ->default(24)
                                            ->helperText('Number of videos to show per page or load'),
                                    ])->columns(2),
                            ]),
                        Tabs\Tab::make('Users')
                            ->schema([
                                Section::make('Registration')
                                    ->schema([
                                        Toggle::make('registration_enabled')
                                            ->label('Allow Registration'),
                                        Toggle::make('email_verification_required')
                                            ->label('Require Email Verification'),
                                        Toggle::make('age_verification_required')
                                            ->label('Require Age Verification'),
                                        TextInput::make('minimum_age')
                                            ->label('Minimum Age')
                                            ->numeric()
                                            ->minValue(13)
                                            ->maxValue(21),
                                    ])->columns(2),
                            ]),
                        Tabs\Tab::make('Videos')
                            ->schema([
                                Section::make('Upload Limits')
                                    ->schema([
                                        TextInput::make('max_upload_size_free')
                                            ->label('Max Upload Size (Free) MB')
                                            ->numeric()
                                            ->suffix('MB'),
                                        TextInput::make('max_upload_size_pro')
                                            ->label('Max Upload Size (Pro) MB')
                                            ->numeric()
                                            ->suffix('MB'),
                                        TextInput::make('max_daily_uploads_free')
                                            ->label('Max Daily Uploads (Free)')
                                            ->numeric(),
                                        TextInput::make('max_daily_uploads_pro')
                                            ->label('Max Daily Uploads (Pro)')
                                            ->numeric(),
                                    ])->columns(2),
                                Section::make('FFmpeg & Transcoding')
                                    ->schema([
                                        Toggle::make('ffmpeg_enabled')
                                            ->label('Enable FFmpeg Processing')
                                            ->helperText('Process videos with FFmpeg for transcoding and thumbnails')
                                            ->reactive(),
                                        Toggle::make('animated_previews_enabled')
                                            ->label('Enable Animated Previews')
                                            ->helperText('Generate animated WebP previews shown on thumbnail hover')
                                            ->default(true),
                                        TextInput::make('ffmpeg_path')
                                            ->label('FFmpeg Binary Path')
                                            ->placeholder('/usr/bin/ffmpeg')
                                            ->helperText('Leave empty to use system default'),
                                        TextInput::make('ffprobe_path')
                                            ->label('FFprobe Binary Path')
                                            ->placeholder('/usr/bin/ffprobe')
                                            ->helperText('Leave empty to use system default'),
                                        Select::make('video_quality_preset')
                                            ->label('Quality Preset')
                                            ->options([
                                                'ultrafast' => 'Ultra Fast (Lower Quality)',
                                                'fast' => 'Fast',
                                                'medium' => 'Medium (Balanced)',
                                                'slow' => 'Slow (Higher Quality)',
                                            ])
                                            ->default('medium'),
                                        Toggle::make('multi_resolution_enabled')
                                            ->label('Enable Multi-Resolution Transcoding')
                                            ->helperText('Create multiple resolution versions of uploaded videos')
                                            ->default(true)
                                            ->reactive(),
                                        \Filament\Forms\Components\CheckboxList::make('enabled_resolutions')
                                            ->label('Enabled Resolutions')
                                            ->options([
                                                '240p' => '240p (426x240) - Low bandwidth',
                                                '360p' => '360p (640x360) - Mobile',
                                                '480p' => '480p (854x480) - SD',
                                                '720p' => '720p (1280x720) - HD',
                                                '1080p' => '1080p (1920x1080) - Full HD',
                                            ])
                                            ->default(['360p', '480p', '720p'])
                                            ->helperText('Select which resolutions to generate. Original quality is always preserved.')
                                            ->visible(fn ($get) => $get('multi_resolution_enabled'))
                                            ->columns(2),
                                        Toggle::make('generate_hls')
                                            ->label('Generate HLS Streaming')
                                            ->helperText('Create HLS playlists for adaptive bitrate streaming')
                                            ->default(true)
                                            ->visible(fn ($get) => $get('multi_resolution_enabled')),
                                    ])->columns(2),
                                Section::make('Watermark')
                                    ->schema([
                                        Toggle::make('watermark_enabled')
                                            ->label('Enable Watermark')
                                            ->helperText('Add a watermark to all processed videos')
                                            ->reactive(),
                                        \Filament\Forms\Components\FileUpload::make('watermark_image')
                                            ->label('Watermark Image')
                                            ->image()
                                            ->directory('watermarks')
                                            ->visibility('public')
                                            ->helperText('Upload a PNG image with transparency for best results')
                                            ->visible(fn ($get) => $get('watermark_enabled')),
                                        Select::make('watermark_position')
                                            ->label('Watermark Position')
                                            ->options([
                                                'top-left' => 'Top Left',
                                                'top-center' => 'Top Center',
                                                'top-right' => 'Top Right',
                                                'center-left' => 'Center Left',
                                                'center' => 'Center',
                                                'center-right' => 'Center Right',
                                                'bottom-left' => 'Bottom Left',
                                                'bottom-center' => 'Bottom Center',
                                                'bottom-right' => 'Bottom Right',
                                            ])
                                            ->default('bottom-right')
                                            ->visible(fn ($get) => $get('watermark_enabled')),
                                        TextInput::make('watermark_opacity')
                                            ->label('Watermark Opacity')
                                            ->numeric()
                                            ->minValue(0)
                                            ->maxValue(100)
                                            ->suffix('%')
                                            ->default(70)
                                            ->visible(fn ($get) => $get('watermark_enabled')),
                                        TextInput::make('watermark_scale')
                                            ->label('Watermark Scale')
                                            ->numeric()
                                            ->minValue(5)
                                            ->maxValue(50)
                                            ->suffix('% of video width')
                                            ->default(15)
                                            ->visible(fn ($get) => $get('watermark_enabled')),
                                        TextInput::make('watermark_padding')
                                            ->label('Watermark Padding')
                                            ->numeric()
                                            ->minValue(0)
                                            ->maxValue(100)
                                            ->suffix('px')
                                            ->default(10)
                                            ->visible(fn ($get) => $get('watermark_enabled')),
                                    ])->columns(2),
                                Section::make('Moderation')
                                    ->schema([
                                        Toggle::make('video_auto_approve')
                                            ->label('Auto-Approve Videos')
                                            ->helperText('Automatically approve new video uploads'),
                                        Toggle::make('comments_enabled')
                                            ->label('Enable Comments'),
                                        Toggle::make('comments_require_approval')
                                            ->label('Comments Require Approval'),
                                    ])->columns(2),
                            ]),
                        Tabs\Tab::make('Analytics')
                            ->schema([
                                Section::make('Tracking')
                                    ->schema([
                                        TextInput::make('google_analytics_id')
                                            ->label('Google Analytics ID')
                                            ->placeholder('G-XXXXXXXXXX'),
                                        Textarea::make('custom_head_scripts')
                                            ->label('Custom Head Scripts')
                                            ->rows(5)
                                            ->helperText('Scripts to add before </head>'),
                                        Textarea::make('custom_footer_scripts')
                                            ->label('Custom Footer Scripts')
                                            ->rows(5)
                                            ->helperText('Scripts to add before </body>'),
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

            Setting::set($key, $value, 'general', $type);
        }

        Notification::make()
            ->title('Settings saved successfully')
            ->success()
            ->send();
    }
}

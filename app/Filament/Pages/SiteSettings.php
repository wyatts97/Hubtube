<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use App\Services\FfmpegService;
use App\Services\WatermarkService;
use Filament\Forms\Components\Actions;
use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Components\Placeholder;
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
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;

class SiteSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';
    protected static ?string $navigationLabel = 'Site Settings';
    protected static ?string $navigationGroup = 'System';
    protected static ?int $navigationSort = 1;
    protected static string $view = 'filament.pages.site-settings';

    public ?array $data = [];
    public ?string $watermarkPreviewUrl = null;
    public ?string $testVideoSourceUrl = null;
    public bool $isGeneratingPreview = false;

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
            'ffmpeg_threads' => Setting::get('ffmpeg_threads', 4),
            'thumbnail_count' => Setting::get('thumbnail_count', 4),
            'audio_bitrate' => Setting::get('audio_bitrate', '128k'),
            'video_quality_preset' => Setting::get('video_quality_preset', 'veryfast'),
            'ffmpeg_rate_control' => Setting::get('ffmpeg_rate_control', 'crf'),
            'ffmpeg_crf' => Setting::get('ffmpeg_crf', 22),
            'ffmpeg_pix_fmt' => Setting::get('ffmpeg_pix_fmt', 'yuv420p'),
            'ffmpeg_mp4_extra_args' => Setting::get('ffmpeg_mp4_extra_args', ''),
            'ffmpeg_hls_extra_args' => Setting::get('ffmpeg_hls_extra_args', ''),
            'ffmpeg_hls_playlist_type' => Setting::get('ffmpeg_hls_playlist_type', 'vod'),
            'ffmpeg_hls_flags' => Setting::get('ffmpeg_hls_flags', 'independent_segments'),
            'multi_resolution_enabled' => Setting::get('multi_resolution_enabled', true),
            'enabled_resolutions' => Setting::get('enabled_resolutions', ['360p', '480p', '720p']),
            'generate_hls' => Setting::get('generate_hls', true),
            'hls_segment_duration' => Setting::get('hls_segment_duration', 6),
            'watermark_enabled' => Setting::get('watermark_enabled', false),
            'watermark_image' => Setting::get('watermark_image', ''),
            'watermark_position' => Setting::get('watermark_position', 'bottom-right'),
            'watermark_opacity' => Setting::get('watermark_opacity', 70),
            'watermark_scale' => Setting::get('watermark_scale', 15),
            'watermark_padding' => Setting::get('watermark_padding', 10),
            'watermark_text_enabled' => Setting::get('watermark_text_enabled', false),
            'watermark_text' => Setting::get('watermark_text', ''),
            'watermark_text_font' => Setting::get('watermark_text_font', '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf'),
            'watermark_text_color' => Setting::get('watermark_text_color', '#ffffff'),
            'watermark_text_size' => Setting::get('watermark_text_size', 24),
            'watermark_text_opacity' => Setting::get('watermark_text_opacity', 70),
            'watermark_text_padding' => Setting::get('watermark_text_padding', 10),
            'watermark_text_position' => Setting::get('watermark_text_position', 'bottom-right'),
            'watermark_text_x' => Setting::get('watermark_text_x', ''),
            'watermark_text_y' => Setting::get('watermark_text_y', ''),
            'watermark_text_scroll_enabled' => Setting::get('watermark_text_scroll_enabled', false),
            'watermark_text_scroll_speed' => Setting::get('watermark_text_scroll_speed', 5),
            'watermark_text_scroll_interval' => Setting::get('watermark_text_scroll_interval', 0),
            'watermark_text_scroll_duration' => Setting::get('watermark_text_scroll_duration', 10),
            'watermark_test_video' => Setting::get('watermark_test_video', ''),
            'video_auto_approve' => Setting::get('video_auto_approve', false),
            'comments_enabled' => Setting::get('comments_enabled', true),
            'comments_require_approval' => Setting::get('comments_require_approval', false),
            'google_analytics_id' => Setting::get('google_analytics_id', ''),
            'custom_head_scripts' => Setting::get('custom_head_scripts', ''),
            'custom_footer_scripts' => Setting::get('custom_footer_scripts', ''),
            'infinite_scroll_enabled' => Setting::get('infinite_scroll_enabled', false),
            'videos_per_page' => Setting::get('videos_per_page', 24),
            'homepage_shorts_carousel' => Setting::get('homepage_shorts_carousel', false),
        ]);

        $this->watermarkPreviewUrl = $this->resolveWatermarkPreviewUrl();
        $this->testVideoSourceUrl = $this->resolveTestVideoSourceUrl();
    }

    protected function resolveTestVideoSourceUrl(): ?string
    {
        $path = Setting::get('watermark_test_video', '');
        if (!$path || !Storage::disk('public')->exists($path)) {
            return null;
        }

        return Storage::disk('public')->url($path);
    }

    protected function resolveWatermarkPreviewUrl(): ?string
    {
        $previewPath = Setting::get('watermark_preview_path', '');
        if (!$previewPath || !Storage::disk('public')->exists($previewPath)) {
            return null;
        }

        return Storage::disk('public')->url($previewPath);
    }

    public function generateWatermarkPreview(): void
    {
        if (!FfmpegService::isAvailable()) {
            Notification::make()
                ->title('FFmpeg is not available')
                ->danger()
                ->send();
            return;
        }

        $sourcePath = Setting::get('watermark_test_video', '');
        if (!$sourcePath || !Storage::disk('public')->exists($sourcePath)) {
            Notification::make()
                ->title('Upload a test video first')
                ->body('Use the file upload above to provide a short video clip for watermark testing.')
                ->warning()
                ->send();
            return;
        }

        // Persist the current form watermark settings to DB first so
        // WatermarkService reads the values the admin just configured.
        $data = $this->form->getState();
        $watermarkKeys = [
            'watermark_enabled', 'watermark_image', 'watermark_position',
            'watermark_opacity', 'watermark_scale', 'watermark_padding',
            'watermark_text_enabled', 'watermark_text', 'watermark_text_font',
            'watermark_text_color', 'watermark_text_size', 'watermark_text_opacity',
            'watermark_text_padding', 'watermark_text_position',
            'watermark_text_x', 'watermark_text_y',
            'watermark_text_scroll_enabled', 'watermark_text_scroll_speed',
            'watermark_text_scroll_interval', 'watermark_text_scroll_duration',
        ];
        foreach ($watermarkKeys as $key) {
            if (array_key_exists($key, $data)) {
                $type = match (true) {
                    is_bool($data[$key]) => 'boolean',
                    is_int($data[$key]) => 'integer',
                    is_array($data[$key]) => 'array',
                    default => 'string',
                };
                Setting::set($key, $data[$key], 'general', $type);
            }
        }

        if (!WatermarkService::hasImageWatermark() && !WatermarkService::hasTextWatermark()) {
            Notification::make()
                ->title('Enable an image or text watermark first')
                ->warning()
                ->send();
            return;
        }

        $this->isGeneratingPreview = true;

        $ffmpeg = FfmpegService::ffmpegPath();
        $ffprobe = FfmpegService::ffprobePath();
        $inputPath = Storage::disk('public')->path($sourcePath);

        // Probe video dimensions
        $probeCmd = sprintf(
            '%s -v error -select_streams v:0 -show_entries stream=width,height -of csv=p=0:s=x %s 2>&1',
            $ffprobe,
            escapeshellarg($inputPath)
        );
        $probeResult = Process::timeout(15)->run($probeCmd);
        $dimensions = trim($probeResult->output());
        if (preg_match('/^(\d+)x(\d+)/', $dimensions, $m)) {
            $width = (int) $m[1];
            $height = (int) $m[2];
        } else {
            $width = 1280;
            $height = 720;
        }

        Storage::disk('public')->makeDirectory('watermarks');
        $relativePath = 'watermarks/watermark_preview.mp4';
        $outputPath = Storage::disk('public')->path($relativePath);

        $watermarkInput = WatermarkService::getWatermarkInput();
        $filterComplex = WatermarkService::buildFilterComplex($width, $height);

        $cmd = sprintf(
            '%s -y -i %s %s -filter_complex "%s" -map "[outv]" -map 0:a? -c:v libx264 -preset veryfast -crf 22 -pix_fmt yuv420p -c:a aac -b:a 128k -movflags +faststart %s 2>&1',
            $ffmpeg,
            escapeshellarg($inputPath),
            $watermarkInput,
            $filterComplex,
            escapeshellarg($outputPath)
        );

        Log::info('Watermark preview command', ['cmd' => $cmd]);

        $result = Process::timeout(600)->run($cmd);

        $this->isGeneratingPreview = false;

        if (!$result->successful() || !file_exists($outputPath) || filesize($outputPath) === 0) {
            Log::error('Watermark preview generation failed', [
                'exit_code' => $result->exitCode(),
                'output' => substr($result->output() . "\n" . $result->errorOutput(), -2000),
            ]);

            Notification::make()
                ->title('Failed to generate preview')
                ->body('Check storage/logs/laravel.log for details.')
                ->danger()
                ->send();
            return;
        }

        Setting::set('watermark_preview_path', $relativePath, 'general', 'string');
        $this->watermarkPreviewUrl = Storage::disk('public')->url($relativePath) . '?t=' . time();

        Notification::make()
            ->title('Watermark preview generated')
            ->body('Your test video has been processed with the current watermark settings.')
            ->success()
            ->send();
    }

    public function deleteWatermarkTestFiles(): void
    {
        $deleted = [];

        $testVideo = Setting::get('watermark_test_video', '');
        if ($testVideo && Storage::disk('public')->exists($testVideo)) {
            Storage::disk('public')->delete($testVideo);
            Setting::set('watermark_test_video', '', 'general', 'string');
            $this->testVideoSourceUrl = null;
            $this->data['watermark_test_video'] = null;
            $deleted[] = 'test video';
        }

        $previewPath = Setting::get('watermark_preview_path', '');
        if ($previewPath && Storage::disk('public')->exists($previewPath)) {
            Storage::disk('public')->delete($previewPath);
            Setting::set('watermark_preview_path', '', 'general', 'string');
            $this->watermarkPreviewUrl = null;
            $deleted[] = 'preview';
        }

        if (empty($deleted)) {
            Notification::make()
                ->title('No test files to delete')
                ->warning()
                ->send();
            return;
        }

        Notification::make()
            ->title('Deleted: ' . implode(' & ', $deleted))
            ->success()
            ->send();
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
                                        Toggle::make('homepage_shorts_carousel')
                                            ->label('Homepage Shorts Carousel')
                                            ->helperText('Show a horizontal carousel of shorts on the homepage. Clicking a short opens the full-screen shorts player.'),
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
                                            ->placeholder('/usr/local/bin/ffmpeg')
                                            ->helperText('Leave empty to use system default'),
                                        TextInput::make('ffprobe_path')
                                            ->label('FFprobe Binary Path')
                                            ->placeholder('/usr/local/bin/ffprobe')
                                            ->helperText('Leave empty to use system default'),
                                        TextInput::make('ffmpeg_threads')
                                            ->label('FFmpeg Threads')
                                            ->numeric()
                                            ->default(4)
                                            ->helperText('Number of CPU threads for encoding. Leave headroom for web server.'),
                                        TextInput::make('thumbnail_count')
                                            ->label('Thumbnail Count')
                                            ->numeric()
                                            ->default(4)
                                            ->helperText('Number of thumbnails to generate per video'),
                                        Select::make('audio_bitrate')
                                            ->label('Audio Bitrate')
                                            ->options([
                                                '64k' => '64 kbps (Low)',
                                                '96k' => '96 kbps',
                                                '128k' => '128 kbps (Default)',
                                                '192k' => '192 kbps',
                                                '256k' => '256 kbps (High)',
                                            ])
                                            ->default('128k'),
                                        Select::make('video_quality_preset')
                                            ->label('Quality Preset')
                                            ->options([
                                                'ultrafast' => 'Ultra Fast (Lower Quality)',
                                                'veryfast' => 'Very Fast (Recommended)',
                                                'fast' => 'Fast',
                                                'medium' => 'Medium (Balanced)',
                                                'slow' => 'Slow (Higher Quality)',
                                            ])
                                            ->default('veryfast'),
                                        Select::make('ffmpeg_rate_control')
                                            ->label('Rate Control')
                                            ->options([
                                                'crf' => 'CRF (Recommended)',
                                                'bitrate' => 'Bitrate',
                                            ])
                                            ->default('crf')
                                            ->helperText('CRF keeps consistent quality; bitrate locks output size.'),
                                        TextInput::make('ffmpeg_crf')
                                            ->label('CRF (Quality)')
                                            ->numeric()
                                            ->minValue(16)
                                            ->maxValue(30)
                                            ->default(22)
                                            ->visible(fn ($get) => $get('ffmpeg_rate_control') === 'crf'),
                                        TextInput::make('ffmpeg_pix_fmt')
                                            ->label('Pixel Format')
                                            ->placeholder('yuv420p')
                                            ->default('yuv420p'),
                                        Textarea::make('ffmpeg_mp4_extra_args')
                                            ->label('MP4 Extra FFmpeg Args')
                                            ->rows(2)
                                            ->placeholder('-profile:v high -level 4.1')
                                            ->helperText('Advanced: appended to MP4 encoding command.'),
                                        Textarea::make('ffmpeg_hls_extra_args')
                                            ->label('HLS Extra FFmpeg Args')
                                            ->rows(2)
                                            ->placeholder('-max_muxing_queue_size 1024')
                                            ->helperText('Advanced: appended to HLS encoding command.'),
                                        TextInput::make('ffmpeg_hls_playlist_type')
                                            ->label('HLS Playlist Type')
                                            ->default('vod')
                                            ->helperText('Common values: vod, event'),
                                        TextInput::make('ffmpeg_hls_flags')
                                            ->label('HLS Flags')
                                            ->default('independent_segments'),
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
                                            ->visible(fn ($get) => $get('multi_resolution_enabled'))
                                            ->reactive(),
                                        TextInput::make('hls_segment_duration')
                                            ->label('HLS Segment Duration (seconds)')
                                            ->numeric()
                                            ->default(6)
                                            ->helperText('Duration of each HLS segment. Lower = faster seeking, higher = fewer requests.')
                                            ->visible(fn ($get) => $get('multi_resolution_enabled') && $get('generate_hls')),
                                    ])->columns(2),
                                Section::make('Watermark')
                                    ->schema([
                                        Toggle::make('watermark_enabled')
                                            ->label('Enable Image Watermark')
                                            ->helperText('Add a PNG watermark to all processed videos')
                                            ->reactive(),
                                        Toggle::make('watermark_text_enabled')
                                            ->label('Enable Text Watermark')
                                            ->helperText('Draw text on top of video, supports scrolling')
                                            ->reactive(),
                                        Section::make('Watermark Preview Test')
                                            ->description('Upload a video clip to preview how your watermark will look on real content. The entire video will be processed so you can test time interval settings.')
                                            ->collapsible()
                                            ->schema([
                                                \Filament\Forms\Components\FileUpload::make('watermark_test_video')
                                                    ->label('Test Video')
                                                    ->acceptedFileTypes(['video/mp4', 'video/webm', 'video/quicktime', 'video/x-msvideo', 'video/x-matroska'])
                                                    ->directory('watermarks')
                                                    ->visibility('public')
                                                    ->maxSize(102400)
                                                    ->helperText('Upload a short MP4/WebM/MOV clip (max 100 MB). Only used for preview testing.')
                                                    ->afterStateUpdated(function ($state) {
                                                        if ($state) {
                                                            Setting::set('watermark_test_video', $state, 'general', 'string');
                                                            $this->testVideoSourceUrl = Storage::disk('public')->url($state);
                                                        }
                                                    })
                                                    ->reactive(),
                                                Placeholder::make('test_video_info')
                                                    ->label('')
                                                    ->content(function () {
                                                        if (!$this->testVideoSourceUrl) {
                                                            return new HtmlString('<span class="text-sm text-gray-500 dark:text-gray-400">No test video uploaded.</span>');
                                                        }
                                                        return new HtmlString(
                                                            '<div class="text-sm text-green-600 dark:text-green-400 flex items-center gap-1">' .
                                                            '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>' .
                                                            'Test video ready</div>'
                                                        );
                                                    })
                                                    ->columnSpanFull(),
                                                Actions::make([
                                                    Action::make('generateWatermarkPreview')
                                                        ->label('Apply Watermark & Preview')
                                                        ->icon('heroicon-o-play')
                                                        ->color('primary')
                                                        ->action(fn () => $this->generateWatermarkPreview()),
                                                    Action::make('deleteWatermarkTestFiles')
                                                        ->label('Delete Test Files')
                                                        ->icon('heroicon-o-trash')
                                                        ->color('danger')
                                                        ->requiresConfirmation()
                                                        ->modalHeading('Delete watermark test files?')
                                                        ->modalDescription('This will remove the uploaded test video and the generated preview.')
                                                        ->action(fn () => $this->deleteWatermarkTestFiles()),
                                                ])->columnSpanFull(),
                                                Placeholder::make('watermark_preview')
                                                    ->label('Watermarked Preview')
                                                    ->content(function () {
                                                        if (!$this->watermarkPreviewUrl) {
                                                            return new HtmlString('<span class="text-sm text-gray-500 dark:text-gray-400">Upload a test video and click "Apply Watermark & Preview" to see the result.</span>');
                                                        }

                                                        return new HtmlString(
                                                            '<video controls playsinline class="w-full max-w-lg rounded-lg" style="background:#111" src="' .
                                                            e($this->watermarkPreviewUrl) .
                                                            '"></video>'
                                                        );
                                                    })
                                                    ->columnSpanFull(),
                                            ])
                                            ->columnSpanFull(),
                                        Section::make('Image Watermark Settings')
                                            ->collapsible()
                                            ->collapsed()
                                            ->schema([
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
                                            ])
                                            ->columns(2)
                                            ->visible(fn ($get) => $get('watermark_enabled'))
                                            ->columnSpanFull(),
                                        Section::make('Text Watermark Settings')
                                            ->collapsible()
                                            ->collapsed()
                                            ->schema([
                                                Textarea::make('watermark_text')
                                                    ->label('Watermark Text')
                                                    ->rows(2),
                                                TextInput::make('watermark_text_font')
                                                    ->label('Font File Path')
                                                    ->placeholder('/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf'),
                                                TextInput::make('watermark_text_color')
                                                    ->label('Text Color')
                                                    ->placeholder('#ffffff'),
                                                TextInput::make('watermark_text_size')
                                                    ->label('Text Size')
                                                    ->numeric()
                                                    ->minValue(8)
                                                    ->maxValue(128),
                                                TextInput::make('watermark_text_opacity')
                                                    ->label('Text Opacity')
                                                    ->numeric()
                                                    ->minValue(0)
                                                    ->maxValue(100)
                                                    ->suffix('%'),
                                                TextInput::make('watermark_text_padding')
                                                    ->label('Text Padding')
                                                    ->numeric()
                                                    ->minValue(0)
                                                    ->maxValue(100)
                                                    ->suffix('px'),
                                                Select::make('watermark_text_position')
                                                    ->label('Text Position')
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
                                                    ]),
                                                TextInput::make('watermark_text_x')
                                                    ->label('Custom Text X')
                                                    ->placeholder('Optional (e.g., 20 or w-text_w-20)'),
                                                TextInput::make('watermark_text_y')
                                                    ->label('Custom Text Y')
                                                    ->placeholder('Optional (e.g., 20 or h-text_h-20)'),
                                                Toggle::make('watermark_text_scroll_enabled')
                                                    ->label('Enable Scrolling Text')
                                                    ->reactive(),
                                                TextInput::make('watermark_text_scroll_speed')
                                                    ->label('Scroll Speed (px/frame)')
                                                    ->numeric()
                                                    ->minValue(1)
                                                    ->maxValue(50)
                                                    ->default(5)
                                                    ->helperText('Pixels the text moves per frame. Higher = faster scroll. Reference: 5 is a good default at 30fps.')
                                                    ->visible(fn ($get) => $get('watermark_text_scroll_enabled')),
                                                TextInput::make('watermark_text_scroll_interval')
                                                    ->label('Scroll Interval (sec)')
                                                    ->numeric()
                                                    ->minValue(0)
                                                    ->default(0)
                                                    ->helperText('Show the text every N seconds. 0 = always visible (continuous scroll).')
                                                    ->visible(fn ($get) => $get('watermark_text_scroll_enabled')),
                                                TextInput::make('watermark_text_scroll_duration')
                                                    ->label('Visible Duration (sec)')
                                                    ->numeric()
                                                    ->minValue(1)
                                                    ->default(10)
                                                    ->helperText('How many seconds the text stays visible each interval.')
                                                    ->visible(fn ($get) => $get('watermark_text_scroll_enabled') && $get('watermark_text_scroll_interval') > 0),
                                            ])
                                            ->columns(2)
                                            ->visible(fn ($get) => $get('watermark_text_enabled'))
                                            ->columnSpanFull(),
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

<?php

namespace App\Filament\Pages;

use App\Filament\Clusters\Settings as SettingsCluster;
use App\Filament\Concerns\RequiresSuperAdmin;
use App\Models\EncodeProfile;
use App\Models\Setting;
use App\Services\AdminLogger;
use App\Services\Encoding\FfmpegCommands;
use App\Services\FfmpegService;
use App\Services\WatermarkService;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;

/**
 * Every FFmpeg setting in one place, including the resolution ladder
 * (EncodeProfile). FfmpegCommands reads these keys to build the commands for
 * uploads, so this page is the only place they are edited.
 */
class EncodingSettings extends Page implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;
    use RequiresSuperAdmin;

    protected static string|\BackedEnum|null $navigationIcon = 'phosphor-film-strip';

    protected static ?string $navigationLabel = 'Video Encoding';

    protected static ?string $title = 'Video Encoding';

    protected static ?string $cluster = SettingsCluster::class;

    protected static ?int $navigationSort = 6;

    protected string $view = 'filament.pages.site-settings';

    public ?array $data = [];

    public ?string $watermarkPreviewUrl = null;

    public ?string $testVideoSourceUrl = null;

    public bool $isGeneratingPreview = false;

    /** Setting key => default. The defaults match FfmpegCommands'. */
    protected const DEFAULTS = [
        'ffmpeg_enabled' => true,
        'ffmpeg_path' => '',
        'ffprobe_path' => '',
        'ffmpeg_threads' => 4,
        'video_quality_preset' => 'veryfast',
        'ffmpeg_rate_control' => 'crf',
        'ffmpeg_crf' => 22,
        'audio_bitrate' => '128k',
        'ffmpeg_pix_fmt' => 'yuv420p',
        'multi_resolution_enabled' => true,
        'chunked_encoding_enabled' => true,
        'chunked_encoding_min_duration' => 300,
        'chunked_encoding_chunk_seconds' => 240,
        'generate_hls' => true,
        'hls_segment_duration' => 6,
        'ffmpeg_hls_playlist_type' => 'vod',
        'ffmpeg_hls_flags' => 'independent_segments',
        'ffmpeg_mp4_extra_args' => '',
        'ffmpeg_hls_extra_args' => '',
        'thumbnail_count' => 4,
        'animated_previews_enabled' => true,
        'watermark_enabled' => false,
        'watermark_image' => '',
        'watermark_position' => 'bottom-right',
        'watermark_opacity' => 70,
        'watermark_scale' => 15,
        'watermark_padding' => 10,
        'watermark_text_enabled' => false,
        'watermark_text' => '',
        'watermark_text_font' => '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',
        'watermark_text_color' => 'white',
        'watermark_text_size' => 24,
        'watermark_text_opacity' => 70,
        'watermark_text_padding' => 10,
        'watermark_text_position' => 'top',
        'watermark_text_scroll_enabled' => false,
        'watermark_text_scroll_speed' => 'medium',
        'watermark_text_scroll_interval' => 0,
        'watermark_text_scroll_start_delay' => 0,
        'watermark_test_video' => '',
    ];

    public function mount(): void
    {
        $state = [];
        foreach (self::DEFAULTS as $key => $default) {
            $state[$key] = Setting::get($key, $default);
        }

        $this->form->fill($state);

        $this->watermarkPreviewUrl = $this->resolveWatermarkPreviewUrl();
        $this->testVideoSourceUrl = $this->resolveTestVideoSourceUrl();
    }

    protected function resolveTestVideoSourceUrl(): ?string
    {
        $testPath = Setting::get('watermark_test_video', '');
        if (! $testPath || ! Storage::disk('public')->exists($testPath)) {
            return null;
        }

        return route('admin.video-stream', ['path' => $testPath]);
    }

    protected function resolveWatermarkPreviewUrl(): ?string
    {
        $previewPath = Setting::get('watermark_preview_path', '');
        if (! $previewPath || ! Storage::disk('public')->exists($previewPath)) {
            return null;
        }

        return route('admin.video-stream', ['path' => $previewPath]).'?t='.filemtime(Storage::disk('public')->path($previewPath));
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('Encoding')
                    ->tabs([
                        Tab::make('Encoding')
                            ->schema([
                                Section::make('FFmpeg')
                                    ->schema([
                                        Toggle::make('ffmpeg_enabled')
                                            ->label('Enable FFmpeg Processing')
                                            ->helperText('Off: uploads are published as-is, with no transcoding or thumbnails.')
                                            ->columnSpanFull(),
                                        TextInput::make('ffmpeg_path')
                                            ->label('FFmpeg Binary Path')
                                            ->placeholder('/usr/local/bin/ffmpeg')
                                            ->helperText('Leave empty to use the system default.'),
                                        TextInput::make('ffprobe_path')
                                            ->label('FFprobe Binary Path')
                                            ->placeholder('/usr/local/bin/ffprobe')
                                            ->helperText('Leave empty to use the system default.'),
                                        TextInput::make('ffmpeg_threads')
                                            ->label('CPU Threads')
                                            ->numeric()
                                            ->minValue(1)
                                            ->maxValue(64)
                                            ->helperText('Per encode. Leave some cores for the web server.'),
                                    ])->columns(2),
                                Section::make('Quality')
                                    ->schema([
                                        Select::make('video_quality_preset')
                                            ->label('Speed Preset')
                                            ->options([
                                                'ultrafast' => 'Ultra Fast (Lower Quality)',
                                                'veryfast' => 'Very Fast (Recommended)',
                                                'fast' => 'Fast',
                                                'medium' => 'Medium (Balanced)',
                                                'slow' => 'Slow (Higher Quality)',
                                            ]),
                                        Select::make('ffmpeg_rate_control')
                                            ->label('Rate Control')
                                            ->options([
                                                'crf' => 'CRF (Recommended)',
                                                'bitrate' => 'Bitrate',
                                            ])
                                            ->helperText('Bitrate mode uses each profile\'s video bitrate.')
                                            ->reactive(),
                                        TextInput::make('ffmpeg_crf')
                                            ->label('CRF')
                                            ->numeric()
                                            ->minValue(16)
                                            ->maxValue(30)
                                            ->helperText('Lower is better quality and larger files.')
                                            ->visible(fn ($get) => $get('ffmpeg_rate_control') === 'crf'),
                                        Select::make('audio_bitrate')
                                            ->label('Audio Bitrate')
                                            ->options([
                                                '64k' => '64 kbps (Low)',
                                                '96k' => '96 kbps',
                                                '128k' => '128 kbps (Default)',
                                                '192k' => '192 kbps',
                                                '256k' => '256 kbps (High)',
                                            ]),
                                        TextInput::make('ffmpeg_pix_fmt')
                                            ->label('Pixel Format')
                                            ->placeholder('yuv420p'),
                                    ])->columns(2),
                                Section::make('Renditions & HLS')
                                    ->schema([
                                        Toggle::make('multi_resolution_enabled')
                                            ->label('Multi-Resolution Transcoding')
                                            ->helperText('Resolutions are set in the Profiles tab. Videos are never upscaled.')
                                            ->reactive(),
                                        Toggle::make('chunked_encoding_enabled')
                                            ->label('Chunked Parallel Encoding')
                                            ->helperText('Encodes long videos in parallel chunks across queue workers.')
                                            ->reactive()
                                            ->visible(fn ($get) => $get('multi_resolution_enabled')),
                                        TextInput::make('chunked_encoding_min_duration')
                                            ->label('Chunk Videos Longer Than')
                                            ->numeric()
                                            ->minValue(60)
                                            ->suffix('seconds')
                                            ->visible(fn ($get) => $get('multi_resolution_enabled') && $get('chunked_encoding_enabled')),
                                        TextInput::make('chunked_encoding_chunk_seconds')
                                            ->label('Chunk Length')
                                            ->numeric()
                                            ->minValue(30)
                                            ->maxValue(1800)
                                            ->suffix('seconds')
                                            ->visible(fn ($get) => $get('multi_resolution_enabled') && $get('chunked_encoding_enabled')),
                                        Toggle::make('generate_hls')
                                            ->label('Generate HLS Streams')
                                            ->reactive()
                                            ->visible(fn ($get) => $get('multi_resolution_enabled')),
                                        TextInput::make('hls_segment_duration')
                                            ->label('HLS Segment Length')
                                            ->numeric()
                                            ->minValue(1)
                                            ->suffix('seconds')
                                            ->helperText('Shorter seeks faster; longer means fewer requests.')
                                            ->visible(fn ($get) => $get('multi_resolution_enabled') && $get('generate_hls')),
                                    ])->columns(2),
                                Section::make('Thumbnails & Previews')
                                    ->schema([
                                        TextInput::make('thumbnail_count')
                                            ->label('Thumbnails Per Video')
                                            ->numeric()
                                            ->minValue(1),
                                        Toggle::make('animated_previews_enabled')
                                            ->label('Animated Hover Previews'),
                                    ])->columns(2),
                                Section::make('Advanced')
                                    ->collapsible()
                                    ->collapsed()
                                    ->schema([
                                        Textarea::make('ffmpeg_mp4_extra_args')
                                            ->label('Extra MP4 Arguments')
                                            ->rows(2)
                                            ->placeholder('-profile:v high -level 4.1'),
                                        Textarea::make('ffmpeg_hls_extra_args')
                                            ->label('Extra HLS Arguments')
                                            ->rows(2)
                                            ->placeholder('-max_muxing_queue_size 1024'),
                                        TextInput::make('ffmpeg_hls_playlist_type')
                                            ->label('HLS Playlist Type')
                                            ->placeholder('vod'),
                                        TextInput::make('ffmpeg_hls_flags')
                                            ->label('HLS Flags')
                                            ->placeholder('independent_segments'),
                                    ])->columns(2),
                            ]),
                        Tab::make('Profiles')
                            ->schema([
                                EmbeddedTable::make(),
                            ]),
                        Tab::make('Watermark')
                            ->schema([
                                Section::make('Watermark')
                                    ->description('Burned into videos as they are encoded.')
                                    ->schema([
                                        Toggle::make('watermark_enabled')
                                            ->label('Image Watermark')
                                            ->reactive(),
                                        Toggle::make('watermark_text_enabled')
                                            ->label('Text Watermark')
                                            ->reactive(),
                                    ])->columns(2),
                                Section::make('Image Watermark')
                                    ->schema([
                                        FileUpload::make('watermark_image')
                                            ->label('Image')
                                            ->image()
                                            ->directory('watermarks')
                                            ->visibility('public')
                                            ->helperText('A transparent PNG works best.'),
                                        Select::make('watermark_position')
                                            ->label('Position')
                                            ->options(self::gridPositions()),
                                        TextInput::make('watermark_opacity')
                                            ->label('Opacity')
                                            ->numeric()
                                            ->minValue(0)
                                            ->maxValue(100)
                                            ->suffix('%'),
                                        TextInput::make('watermark_scale')
                                            ->label('Size')
                                            ->numeric()
                                            ->minValue(5)
                                            ->maxValue(50)
                                            ->suffix('% of video width'),
                                        TextInput::make('watermark_padding')
                                            ->label('Edge Padding')
                                            ->numeric()
                                            ->minValue(0)
                                            ->maxValue(100)
                                            ->suffix('px'),
                                    ])
                                    ->columns(2)
                                    ->visible(fn ($get) => $get('watermark_enabled')),
                                Section::make('Text Watermark')
                                    ->schema([
                                        Textarea::make('watermark_text')
                                            ->label('Text')
                                            ->rows(2)
                                            ->columnSpanFull(),
                                        Select::make('watermark_text_font')
                                            ->label('Font')
                                            ->options(fn () => WatermarkService::getSystemFonts())
                                            ->searchable()
                                            ->allowHtml()
                                            ->getOptionLabelUsing(fn ($value) => WatermarkService::getSystemFonts()[$value] ?? basename($value)),
                                        Select::make('watermark_text_color')
                                            ->label('Color')
                                            ->options(function () {
                                                $result = [];
                                                foreach (WatermarkService::getColorOptions() as $value => $label) {
                                                    $swatch = '<span style="display:inline-block;width:12px;height:12px;border-radius:2px;background:'.$value.';border:1px solid #555;vertical-align:middle;margin-right:6px"></span>';
                                                    $result[$value] = $swatch.$label;
                                                }

                                                return $result;
                                            })
                                            ->allowHtml(),
                                        TextInput::make('watermark_text_size')
                                            ->label('Size')
                                            ->numeric()
                                            ->minValue(8)
                                            ->maxValue(128)
                                            ->helperText('At 720p; scales with resolution.'),
                                        TextInput::make('watermark_text_opacity')
                                            ->label('Opacity')
                                            ->numeric()
                                            ->minValue(0)
                                            ->maxValue(100)
                                            ->suffix('%'),
                                        TextInput::make('watermark_text_padding')
                                            ->label('Edge Padding')
                                            ->numeric()
                                            ->minValue(0)
                                            ->maxValue(200)
                                            ->suffix('px'),
                                        Select::make('watermark_text_position')
                                            ->label('Position')
                                            ->options(fn ($get) => $get('watermark_text_scroll_enabled')
                                                ? ['top' => 'Top', 'middle' => 'Middle', 'bottom' => 'Bottom']
                                                : self::gridPositions())
                                            ->reactive(),
                                        Toggle::make('watermark_text_scroll_enabled')
                                            ->label('Scroll Across Video')
                                            ->reactive()
                                            ->afterStateUpdated(function ($state, $set, $get) {
                                                $pos = $get('watermark_text_position');
                                                if ($state) {
                                                    $map = [
                                                        'top-left' => 'top', 'top-center' => 'top', 'top-right' => 'top',
                                                        'center-left' => 'middle', 'center' => 'middle', 'center-right' => 'middle',
                                                        'bottom-left' => 'bottom', 'bottom-center' => 'bottom', 'bottom-right' => 'bottom',
                                                    ];
                                                    $set('watermark_text_position', $map[$pos] ?? 'top');
                                                } else {
                                                    $map = ['top' => 'top-center', 'middle' => 'center', 'bottom' => 'bottom-center'];
                                                    $set('watermark_text_position', $map[$pos] ?? $pos);
                                                }
                                            }),
                                        Select::make('watermark_text_scroll_speed')
                                            ->label('Scroll Speed')
                                            ->options(WatermarkService::getSpeedOptions())
                                            ->visible(fn ($get) => $get('watermark_text_scroll_enabled')),
                                        TextInput::make('watermark_text_scroll_interval')
                                            ->label('Repeat Every')
                                            ->numeric()
                                            ->minValue(0)
                                            ->suffix('seconds')
                                            ->helperText('0 = scroll continuously.')
                                            ->visible(fn ($get) => $get('watermark_text_scroll_enabled')),
                                        TextInput::make('watermark_text_scroll_start_delay')
                                            ->label('Start Delay')
                                            ->numeric()
                                            ->minValue(0)
                                            ->suffix('seconds')
                                            ->visible(fn ($get) => $get('watermark_text_scroll_enabled')),
                                    ])
                                    ->columns(2)
                                    ->visible(fn ($get) => $get('watermark_text_enabled')),
                                Section::make('Preview')
                                    ->description('Encodes a test clip with the current settings.')
                                    ->collapsible()
                                    ->schema([
                                        FileUpload::make('watermark_test_video')
                                            ->label('Test Video')
                                            ->acceptedFileTypes(['video/mp4', 'video/webm', 'video/quicktime', 'video/x-msvideo', 'video/x-matroska'])
                                            ->directory('watermarks')
                                            ->visibility('public')
                                            ->maxSize(102400)
                                            ->helperText('Max 100 MB.')
                                            ->afterStateUpdated(function ($state) {
                                                if ($state) {
                                                    Setting::set('watermark_test_video', $state, 'encoding', 'string');
                                                    $this->testVideoSourceUrl = '/storage/'.$state;
                                                }
                                            })
                                            ->reactive(),
                                        Actions::make([
                                            Action::make('generateWatermarkPreview')
                                                ->label('Generate Preview')
                                                ->icon('phosphor-play')
                                                ->color('primary')
                                                ->action(fn () => $this->generateWatermarkPreview()),
                                            Action::make('deleteWatermarkTestFiles')
                                                ->label('Delete Test Files')
                                                ->icon('phosphor-trash')
                                                ->color('danger')
                                                ->requiresConfirmation()
                                                ->modalHeading('Delete watermark test files?')
                                                ->modalDescription('Removes the test video and the preview.')
                                                ->action(fn () => $this->deleteWatermarkTestFiles()),
                                        ])->columnSpanFull(),
                                        Placeholder::make('watermark_preview')
                                            ->label('Preview')
                                            ->content(function () {
                                                if (! $this->watermarkPreviewUrl) {
                                                    return new HtmlString('<span class="text-sm text-gray-500 dark:text-gray-400">No preview yet.</span>');
                                                }

                                                return new HtmlString(
                                                    '<div wire:ignore>'.
                                                    '<video controls playsinline preload="auto" class="w-full max-w-lg rounded-lg" style="background:#111">'.
                                                    '<source src="'.e($this->watermarkPreviewUrl).'" type="video/mp4">'.
                                                    '</video>'.
                                                    '</div>'
                                                );
                                            })
                                            ->columnSpanFull(),
                                    ])
                                    ->visible(fn ($get) => $get('watermark_enabled') || $get('watermark_text_enabled')),
                            ]),
                    ])->columnSpanFull(),
            ])
            ->statePath('data');
    }

    /** The resolution ladder, shown in the Profiles tab. */
    public function table(Table $table): Table
    {
        return $table
            ->query(EncodeProfile::query()->withCount('encodings'))
            ->defaultSort('height')
            ->heading('Encoding Profiles')
            ->description('Videos are encoded to every active profile below their height. Changes apply to new uploads; use "Encode missing renditions" for existing videos.')
            ->columns([
                TextColumn::make('name')
                    ->label('Quality')
                    ->weight('bold'),
                TextColumn::make('resolution')
                    ->state(fn (EncodeProfile $record): string => "{$record->width}×{$record->height}"),
                TextColumn::make('video_bitrate')
                    ->label('Bitrate'),
                TextColumn::make('codec')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => EncodeProfile::CODECS[$state] ?? $state)
                    ->color('gray'),
                TextColumn::make('encodings_count')
                    ->label('Renditions')
                    ->sortable(),
                ToggleColumn::make('is_active')
                    ->label('Active'),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Add')
                    ->icon('phosphor-plus')
                    ->model(EncodeProfile::class)
                    ->schema(fn () => $this->profileFields()),
            ])
            ->recordActions([
                EditAction::make()->schema(fn () => $this->profileFields()),
                DeleteAction::make(),
            ])
            ->paginated(false)
            ->emptyStateIcon('phosphor-film-strip')
            ->emptyStateHeading('No encoding profiles')
            ->emptyStateDescription('Without an active profile, videos are published at their original resolution only.');
    }

    protected function profileFields(): array
    {
        return [
            TextInput::make('name')
                ->label('Quality Label')
                ->helperText('Shown in the player\'s quality menu, e.g. 720p.')
                ->required()
                ->maxLength(32)
                ->regex('/^[A-Za-z0-9_-]+$/')
                ->unique(EncodeProfile::class, 'name', ignoreRecord: true)
                ->notIn(['original']),
            Select::make('codec')
                ->options(EncodeProfile::CODECS)
                ->default('h264')
                ->required()
                ->selectablePlaceholder(false)
                ->helperText('H.264 plays in every browser, both as MP4 and over HLS.'),
            TextInput::make('width')
                ->numeric()
                ->required()
                ->minValue(64)
                ->maxValue(7680)
                ->helperText('Nominal 16:9 width, used in the HLS playlist. Output keeps the source aspect ratio.'),
            TextInput::make('height')
                ->numeric()
                ->required()
                ->minValue(64)
                ->maxValue(4320)
                ->helperText('Output height in pixels.'),
            TextInput::make('video_bitrate')
                ->label('Video Bitrate')
                ->required()
                ->maxLength(16)
                ->regex('/^\d+(\.\d+)?[kKmM]?$/')
                ->placeholder('2800k')
                ->helperText('Used when rate control is "Bitrate", and as the HLS bandwidth hint.'),
            Toggle::make('is_active')
                ->label('Active')
                ->default(true)
                ->inline(false),
        ];
    }

    protected static function gridPositions(): array
    {
        return [
            'top-left' => 'Top Left',
            'top-center' => 'Top Center',
            'top-right' => 'Top Right',
            'center-left' => 'Center Left',
            'center' => 'Center',
            'center-right' => 'Center Right',
            'bottom-left' => 'Bottom Left',
            'bottom-center' => 'Bottom Center',
            'bottom-right' => 'Bottom Right',
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('save')
                ->color('success')
                ->label('Save Settings')
                ->icon('phosphor-check')
                ->action('save'),
        ];
    }

    public function save(): void
    {
        $data = $this->persist($this->form->getState());

        AdminLogger::settingsSaved('Encoding', array_keys($data));

        Notification::make()
            ->title('Settings saved successfully')
            ->success()
            ->send();
    }

    /** @return array<string, mixed> what was written */
    protected function persist(array $data): array
    {
        foreach ($data as $key => $value) {
            $type = match (true) {
                is_bool($value) => 'boolean',
                is_int($value) => 'integer',
                is_array($value) => 'array',
                default => 'string',
            };

            Setting::set($key, $value, 'encoding', $type);
        }

        return $data;
    }

    /**
     * Encodes the test clip exactly as an upload would be, using the settings
     * currently on the form.
     */
    public function generateWatermarkPreview(): void
    {
        if (! FfmpegService::isAvailable()) {
            Notification::make()->title('FFmpeg is not available')->danger()->send();

            return;
        }

        $data = $this->persist($this->form->getState());
        $sourcePath = $data['watermark_test_video'] ?? '';

        if (! $sourcePath || ! Storage::disk('public')->exists($sourcePath)) {
            Notification::make()->title('Upload a test video first')->warning()->send();

            return;
        }

        $commands = new FfmpegCommands(Setting::getAll());

        if (! $commands->hasWatermark()) {
            Notification::make()->title('Enable an image or text watermark first')->warning()->send();

            return;
        }

        $this->isGeneratingPreview = true;

        $inputPath = Storage::disk('public')->path($sourcePath);

        $probe = Process::timeout(15)->run(sprintf(
            '%s -v error -select_streams v:0 -show_entries stream=width,height -of csv=p=0:s=x %s 2>&1',
            $commands->ffprobe(),
            escapeshellarg($inputPath)
        ));
        [$width, $height] = preg_match('/^(\d+)x(\d+)/', trim($probe->output()), $m)
            ? [(int) $m[1], (int) $m[2]]
            : [1280, 720];

        Storage::disk('public')->makeDirectory('watermarks');
        $relativePath = 'watermarks/watermark_preview.mp4';
        $outputPath = Storage::disk('public')->path($relativePath);

        $cmd = implode(' ', array_filter([
            $commands->ffmpeg().' -y -i '.escapeshellarg($inputPath),
            $commands->watermarkInput(),
            '-filter_complex '.escapeshellarg($commands->videoFilterGraph(true, $width, $height, null)),
            '-map '.escapeshellarg('[outv]').' -map '.escapeshellarg('0:a:0?'),
            $commands->videoArgs(),
            $commands->audioArgs(),
            '-movflags +faststart '.escapeshellarg($outputPath).' 2>&1',
        ]));

        $result = Process::timeout(600)->run($cmd);

        $this->isGeneratingPreview = false;

        if (! $result->successful() || ! file_exists($outputPath) || filesize($outputPath) === 0) {
            Log::error('Watermark preview generation failed', [
                'exit_code' => $result->exitCode(),
                'output' => substr($result->output()."\n".$result->errorOutput(), -2000),
            ]);

            Notification::make()
                ->title('Failed to generate preview')
                ->body('Check storage/logs/laravel.log for details.')
                ->danger()
                ->send();

            return;
        }

        Setting::set('watermark_preview_path', $relativePath, 'encoding', 'string');
        $this->watermarkPreviewUrl = route('admin.video-stream', ['path' => $relativePath]).'?t='.time();

        Notification::make()->title('Preview ready')->success()->send();
    }

    public function deleteWatermarkTestFiles(): void
    {
        $deleted = [];

        $testVideo = Setting::get('watermark_test_video', '');
        if ($testVideo && Storage::disk('public')->exists($testVideo)) {
            Storage::disk('public')->delete($testVideo);
            Setting::set('watermark_test_video', '', 'encoding', 'string');
            $this->testVideoSourceUrl = null;
            $this->data['watermark_test_video'] = null;
            $deleted[] = 'test video';
        }

        $previewPath = Setting::get('watermark_preview_path', '');
        if ($previewPath && Storage::disk('public')->exists($previewPath)) {
            Storage::disk('public')->delete($previewPath);
            Setting::set('watermark_preview_path', '', 'encoding', 'string');
            $this->watermarkPreviewUrl = null;
            $deleted[] = 'preview';
        }

        if (empty($deleted)) {
            Notification::make()->title('No test files to delete')->warning()->send();

            return;
        }

        Notification::make()->title('Deleted: '.implode(' & ', $deleted))->success()->send();
    }
}

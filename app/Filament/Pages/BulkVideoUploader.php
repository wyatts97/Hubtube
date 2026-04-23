<?php

namespace App\Filament\Pages;

use App\Jobs\CreateBulkVideosJob;
use App\Models\Category;
use App\Models\User;
use App\Models\Video;
use App\Services\AdminLogger;
use App\Services\BulkVideoCreator;
use Filament\Forms\Components\Actions\Action as FormAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Section as FormSection;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class BulkVideoUploader extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-arrow-up-tray';
    protected static ?string $navigationLabel = 'Bulk Upload';
    protected static ?string $navigationGroup = 'Content';
    protected static ?int $navigationSort = 7;
    protected static string $view = 'filament.pages.bulk-video-uploader';

    /** @var array File upload form state */
    public ?array $uploadData = [];

    /** @var array Video metadata entries [{title, description, category_id, tags, user_id, age_restricted, file_path, file_size, file_name}] */
    public array $entries = [];

    /** @var array "Apply to All" bulk-settings form state */
    public array $bulkSettings = [
        'category_id' => null,
        'user_id' => null,
        'tags' => [],
        'age_restricted' => true,
        'add_to_queue' => true,
    ];

    /** @var array Created video IDs for status polling */
    public array $createdVideoIds = [];

    /** @var bool Whether we're in the creating/processing phase */
    public bool $isCreating = false;

    /** @var string|null Token used to poll CreateBulkVideosJob results from cache */
    public ?string $bulkToken = null;

    /**
     * Batches with more entries than this are dispatched to a queue job
     * instead of being created synchronously inline.
     */
    protected const ASYNC_THRESHOLD = 3;

    protected function getForms(): array
    {
        return [
            'uploadForm',
            'bulkSettingsForm',
            'entriesForm',
        ];
    }

    public function mount(): void
    {
        $this->bulkSettings['user_id'] = auth()->id();
        $this->uploadForm->fill([]);
        $this->bulkSettingsForm->fill($this->bulkSettings);
    }

    public function uploadForm(Form $form): Form
    {
        return $form
            ->schema([
            FileUpload::make('video_files')
            ->label('Drop video files here or click to browse')
            ->disk('public')
            ->directory('videos/admin-uploads')
            ->acceptedFileTypes(['video/mp4', 'video/quicktime', 'video/x-msvideo', 'video/x-matroska', 'video/webm'])
            ->maxSize(5242880) // 5GB
            ->multiple()
            ->maxFiles(50)
            ->visibility('public')
            ->storeFileNamesIn('video_file_names')
            ->previewable(false)
            ->columnSpanFull(),
        ])
            ->statePath('uploadData');
    }

    public function bulkSettingsForm(Form $form): Form
    {
        return $form
            ->schema([
                FormSection::make('Apply to All')
                    ->description('Defaults applied to each newly added file. Click "Apply to All" to overwrite existing entries.')
                    ->icon('heroicon-o-adjustments-horizontal')
                    ->collapsible()
                    ->schema([
                        Select::make('category_id')
                            ->label('Category')
                            ->options(fn () => Category::active()->orderBy('name')->pluck('name', 'id')->all())
                            ->searchable()
                            ->preload()
                            ->placeholder('— None —'),
                        Select::make('user_id')
                            ->label('Assign to User')
                            ->options(fn () => User::orderBy('username')->pluck('username', 'id')->all())
                            ->searchable()
                            ->preload()
                            ->required(),
                        TagsInput::make('tags')
                            ->label('Tags')
                            ->placeholder('Add tags…')
                            ->columnSpanFull(),
                        Toggle::make('age_restricted')
                            ->label('Age Restricted')
                            ->inline(false),
                        Toggle::make('add_to_queue')
                            ->label('Auto-publish on schedule')
                            ->helperText('Adds each video to the scheduled-publish queue (skips moderation).')
                            ->inline(false),
                    ])
                    ->columns(2),
            ])
            ->statePath('bulkSettings');
    }

    public function entriesForm(Form $form): Form
    {
        return $form
            ->schema([
                Repeater::make('entries')
                    ->hiddenLabel()
                    ->reorderable(true)
                    ->reorderableWithDragAndDrop(true)
                    ->addable(false)
                    ->cloneable(false)
                    ->collapsible()
                    ->collapsed(false)
                    ->itemLabel(fn (array $state): string =>
                        trim((string) ($state['title'] ?? '')) !== ''
                            ? (string) $state['title']
                            : ((string) ($state['file_name'] ?? 'Video'))
                    )
                    ->deleteAction(
                        fn (FormAction $action) => $action->action(function (array $arguments, Repeater $component) {
                            $items = $component->getState();
                            $key = $arguments['item'] ?? null;
                            if ($key !== null && isset($items[$key])) {
                                $path = $items[$key]['file_path'] ?? null;
                                if ($path && Storage::disk('public')->exists($path)) {
                                    Storage::disk('public')->delete($path);
                                }
                                unset($items[$key]);
                                $component->state(array_values($items));
                            }
                        })
                    )
                    ->schema([
                        Placeholder::make('preview')
                            ->hiddenLabel()
                            ->content(fn (Get $get): View => view(
                                'filament.pages.partials.bulk-entry-preview',
                                [
                                    'filePath' => $get('file_path'),
                                    'fileSize' => $get('file_size'),
                                    'fileName' => $get('file_name'),
                                ]
                            ))
                            ->columnSpan(1),
                        FormSection::make()
                            ->hiddenLabel()
                            ->schema([
                                TextInput::make('title')
                                    ->required()
                                    ->maxLength(255)
                                    ->placeholder('Enter video title…')
                                    ->columnSpanFull()
                                    ->suffixAction(
                                        FormAction::make('useFilename')
                                            ->icon('heroicon-o-sparkles')
                                            ->tooltip('Regenerate title from filename')
                                            ->action(function (Set $set, Get $get) {
                                                $name = (string) ($get('file_name') ?? '');
                                                if ($name !== '') {
                                                    $set('title', $this->titleFromFilename($name));
                                                }
                                            })
                                    ),
                                Textarea::make('description')
                                    ->rows(2)
                                    ->placeholder('Optional description…')
                                    ->columnSpanFull(),
                                Select::make('category_id')
                                    ->label('Category')
                                    ->options(fn () => Category::active()->orderBy('name')->pluck('name', 'id')->all())
                                    ->searchable()
                                    ->preload()
                                    ->placeholder('— None —'),
                                Select::make('user_id')
                                    ->label('Assign to User')
                                    ->options(fn () => User::orderBy('username')->pluck('username', 'id')->all())
                                    ->searchable()
                                    ->preload()
                                    ->required(),
                                TagsInput::make('tags')
                                    ->label('Tags')
                                    ->placeholder('Add tags…')
                                    ->columnSpanFull(),
                                Toggle::make('age_restricted')
                                    ->label('Age Restricted')
                                    ->inline(false),
                                Hidden::make('file_path'),
                                Hidden::make('file_size'),
                                Hidden::make('file_name'),
                            ])
                            ->columnSpan(1),
                    ])
                    ->columns(2),
            ]);
    }

    public function addUploadedFiles(): void
    {
        $data = $this->uploadForm->getState();
        $paths = $data['video_files'] ?? [];
        $names = $data['video_file_names'] ?? [];

        if (empty($paths)) {
            Notification::make()->title('No files selected')->warning()->send();
            return;
        }

        foreach ($paths as $index => $tempPath) {
            $originalName = is_array($names) ? ($names[$tempPath] ?? basename($tempPath)) : basename($tempPath);

            $this->entries[] = [
                'title' => $this->titleFromFilename($originalName),
                'description' => '',
                'category_id' => $this->bulkSettings['category_id'] ?? null,
                'tags' => $this->bulkSettings['tags'] ?? [],
                'user_id' => $this->bulkSettings['user_id'] ?? auth()->id(),
                'age_restricted' => (bool) ($this->bulkSettings['age_restricted'] ?? true),
                'file_path' => $tempPath,
                'file_size' => Storage::disk('public')->exists($tempPath) ? Storage::disk('public')->size($tempPath) : 0,
                'file_name' => $originalName,
            ];
        }

        // Reset the upload form
        $this->uploadForm->fill([]);

        Notification::make()->title(count($paths) . ' file(s) added')->success()->send();
    }

    public function applyBulkSettings(): void
    {
        $settings = $this->bulkSettingsForm->getState();

        foreach ($this->entries as &$entry) {
            if (!empty($settings['category_id'])) {
                $entry['category_id'] = $settings['category_id'];
            }
            if (!empty($settings['user_id'])) {
                $entry['user_id'] = $settings['user_id'];
            }
            if (!empty($settings['tags'])) {
                $entry['tags'] = $settings['tags'];
            }
            $entry['age_restricted'] = (bool) ($settings['age_restricted'] ?? true);
        }
        unset($entry);

        Notification::make()->title('Bulk settings applied to all entries')->success()->send();
    }

    public function createAllVideos(): void
    {
        if (empty($this->entries)) {
            Notification::make()->title('No videos to create')->warning()->send();
            return;
        }

        // Validate all entries have titles
        foreach ($this->entries as $index => $entry) {
            if (empty(trim($entry['title'] ?? ''))) {
                Notification::make()
                    ->title("Video #" . ($index + 1) . " needs a title")
                    ->danger()
                    ->send();
                return;
            }
        }

        $this->isCreating = true;
        $this->createdVideoIds = [];
        $this->bulkToken = null;

        $addToQueue = (bool) ($this->bulkSettings['add_to_queue'] ?? true);
        $actorId = (int) (auth()->id() ?? 0);
        $count = count($this->entries);

        if ($count <= self::ASYNC_THRESHOLD) {
            // Small batch — create synchronously for immediate feedback.
            $this->createdVideoIds = app(BulkVideoCreator::class)
                ->createMany($this->entries, $addToQueue, $actorId);

            AdminLogger::settingsSaved('Bulk Video Upload', [
                'created_' . count($this->createdVideoIds) . '_videos',
                'mode_sync',
            ]);

            Notification::make()
                ->title('Created ' . count($this->createdVideoIds) . ' video(s) — processing will begin shortly')
                ->success()
                ->send();
        } else {
            // Larger batch — dispatch and poll the cache for the result.
            $this->bulkToken = (string) Str::uuid();
            CreateBulkVideosJob::dispatch(
                $this->entries,
                $addToQueue,
                $actorId,
                $this->bulkToken,
            );

            AdminLogger::settingsSaved('Bulk Video Upload', [
                "queued_{$count}_videos",
                'mode_async',
            ]);

            Notification::make()
                ->title("Queued {$count} video(s) — creating them in the background…")
                ->success()
                ->send();
        }

        $this->entries = [];
    }

    /**
     * Called by wire:poll while an async CreateBulkVideosJob is running.
     * Hydrates $createdVideoIds from the cache entry written by the job.
     */
    public function pollBulkResults(): void
    {
        if (!$this->bulkToken) {
            return;
        }

        $actorId = (int) (auth()->id() ?? 0);
        $key = CreateBulkVideosJob::cacheKey($actorId, $this->bulkToken);
        $payload = \Illuminate\Support\Facades\Cache::get($key);

        if (!is_array($payload)) {
            return;
        }

        $ids = $payload['created_ids'] ?? [];
        if (is_array($ids) && !empty($ids)) {
            $this->createdVideoIds = array_values(array_unique(array_merge($this->createdVideoIds, $ids)));
        }

        if (($payload['status'] ?? null) === 'done' || ($payload['status'] ?? null) === 'failed') {
            $this->bulkToken = null;
            \Illuminate\Support\Facades\Cache::forget($key);
        }
    }

    /**
     * Turn a raw uploaded filename into a presentable default title.
     * "My_Cool-Video.final v2.mp4" -> "My Cool Video Final V2"
     */
    protected function titleFromFilename(string $name): string
    {
        $base = pathinfo($name, PATHINFO_FILENAME);
        $clean = preg_replace('/[_\-.]+/', ' ', $base) ?? $base;
        $clean = preg_replace('/\s+/', ' ', trim($clean)) ?? $clean;
        return $clean === '' ? '' : Str::title($clean);
    }

    public function getCreatedVideosProperty(): \Illuminate\Database\Eloquent\Collection
    {
        if (empty($this->createdVideoIds)) {
            return new \Illuminate\Database\Eloquent\Collection();
        }

        return Video::with('user', 'category')
            ->whereIn('id', $this->createdVideoIds)
            ->orderBy('id')
            ->get();
    }

    public function selectThumbnail(int $videoId, int $thumbIndex): void
    {
        $video = Video::find($videoId);
        if (!$video)
            return;

        $slug = $video->slug;
        $slugTitle = Str::slug($video->title, '_');
        $thumbPath = "videos/{$slug}/{$slugTitle}_thumb_{$thumbIndex}.jpg";

        if (Storage::disk($video->storage_disk ?? 'public')->exists($thumbPath)) {
            $video->update(['thumbnail' => $thumbPath]);
            Notification::make()->title('Thumbnail updated')->success()->send();
        }
    }
}

<?php

namespace App\Filament\Pages;

use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Actions\Action;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Illuminate\Support\Facades\Cache;
use Illuminate\Database\Eloquent\Collection;
use App\Models\Category;
use App\Models\User;
use App\Models\Image;
use App\Services\AdminLogger;
use App\Services\ImageService;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\Uid\Ulid;

class BulkImageUploader extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string | \BackedEnum | null $navigationIcon = 'phosphor-images';
    protected static ?string $navigationLabel = 'Bulk Image Upload';
    protected static string | \UnitEnum | null $navigationGroup = 'Content';
    protected static ?int $navigationSort = 8;
    protected string $view = 'filament.pages.bulk-image-uploader';

    /** @var array File upload form state */
    public ?array $uploadData = [];

    /** @var array Image metadata entries [{title, description, category_id, tags, user_id, privacy, file_path, file_size, file_name}] */
    public array $entries = [];

    /** @var array "Apply to All" bulk-settings form state */
    public array $bulkSettings = [
        'category_id' => null,
        'user_id' => null,
        'tags' => [],
        'privacy' => 'public',
    ];

    /** @var array Created image IDs for status polling */
    public array $createdImageIds = [];

    /** @var bool Whether we're in the creating/processing phase */
    public bool $isCreating = false;

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

    public function uploadForm(Schema $schema): Schema
    {
        return $schema
            ->components([
            FileUpload::make('image_files')
            ->label('Drop image files here or click to browse')
            ->disk('public')
            ->directory('images/admin-uploads')
            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/gif', 'image/webp'])
            ->maxSize(524288) // 500MB
            ->multiple()
            ->maxFiles(50)
            ->visibility('public')
            ->storeFileNamesIn('image_file_names')
            ->previewable(false)
            ->columnSpanFull(),
        ])
            ->statePath('uploadData');
    }

    public function bulkSettingsForm(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Apply to All')
                    ->description('Defaults applied to each newly added file. Click "Apply to All" to overwrite existing entries.')
                    ->icon('phosphor-sliders-horizontal')
                    ->collapsible()
                    ->schema([
                        Select::make('category_id')
                            ->label('Category')
                            ->options(fn () => Category::active()->orderBy('name')->pluck('name', 'id')->all())
                            ->required()
                            ->searchable()
                            ->preload()
                            ->placeholder('Select a category'),
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
                        Select::make('privacy')
                            ->label('Privacy')
                            ->options([
                                'public' => 'Public',
                                'private' => 'Private',
                                'unlisted' => 'Unlisted',
                            ])
                            ->default('public')
                            ->required(),
                    ])
                    ->columns(2),
            ])
            ->statePath('bulkSettings');
    }

    public function entriesForm(Schema $schema): Schema
    {
        return $schema
            ->components([
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
                            : ((string) ($state['file_name'] ?? 'Image'))
                    )
                    ->deleteAction(
                        fn (Action $action) => $action->action(function (array $arguments, Repeater $component) {
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
                                'filament.pages.partials.bulk-image-preview',
                                [
                                    'filePath' => $get('file_path'),
                                    'fileSize' => $get('file_size'),
                                    'fileName' => $get('file_name'),
                                ]
                            ))
                            ->columnSpan(1),
                        Section::make()
                            ->hiddenLabel()
                            ->schema([
                                TextInput::make('title')
                                    ->required()
                                    ->maxLength(200)
                                    ->placeholder('Enter image title…')
                                    ->columnSpanFull()
                                    ->suffixAction(
                                        Action::make('useFilename')
                                            ->icon('phosphor-sparkle')
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
                                    ->required()
                                    ->searchable()
                                    ->preload()
                                    ->placeholder('Select a category'),
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
                                Select::make('privacy')
                                    ->label('Privacy')
                                    ->options([
                                        'public' => 'Public',
                                        'private' => 'Private',
                                        'unlisted' => 'Unlisted',
                                    ])
                                    ->default('public')
                                    ->required(),
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
        $paths = $data['image_files'] ?? [];
        $names = $data['image_file_names'] ?? [];

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
                'privacy' => $this->bulkSettings['privacy'] ?? 'public',
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
            if (!empty($settings['privacy'])) {
                $entry['privacy'] = $settings['privacy'];
            }
        }
        unset($entry);

        Notification::make()->title('Bulk settings applied to all entries')->success()->send();
    }

    public function createAllImages(): void
    {
        if (empty($this->entries)) {
            Notification::make()->title('No images to create')->warning()->send();
            return;
        }

        // Validate all entries have titles, users, and categories
        foreach ($this->entries as $index => $entry) {
            if (empty(trim($entry['title'] ?? ''))) {
                Notification::make()
                    ->title("Image #" . ($index + 1) . " needs a title")
                    ->danger()
                    ->send();
                return;
            }
            if (empty($entry['user_id'])) {
                Notification::make()
                    ->title("Image #" . ($index + 1) . " needs a user assigned")
                    ->danger()
                    ->send();
                return;
            }
            if (empty($entry['category_id'])) {
                Notification::make()
                    ->title("Image #" . ($index + 1) . " needs a category")
                    ->danger()
                    ->send();
                return;
            }
        }

        $this->isCreating = true;
        $this->createdImageIds = [];

        $actorId = (int) (auth()->id() ?? 0);
        $imageService = app(ImageService::class);

        foreach ($this->entries as $entry) {
            $tempPath = $entry['file_path'];
            $fullPath = Storage::disk('public')->path($tempPath);

            // Create UploadedFile from the temp path
            $uploadedFile = new \Illuminate\Http\UploadedFile(
                $fullPath,
                $entry['file_name'],
                mime_content_type($fullPath),
                $entry['file_size'] ?? null,
                true
            );

            // Use ImageService to process (creates record + optimizations)
            $image = $imageService->process($uploadedFile, $entry['user_id'], [
                'title' => $entry['title'],
                'description' => $entry['description'] ?? '',
                'privacy' => $entry['privacy'] ?? 'public',
                'category_id' => $entry['category_id'] ?? null,
                'tags' => $entry['tags'] ?? [],
            ]);

            // Clean up temp file
            if (Storage::disk('public')->exists($tempPath)) {
                Storage::disk('public')->delete($tempPath);
            }

            // Clean up empty admin-uploads directory
            $tempDir = dirname($tempPath);
            if (Storage::disk('public')->exists($tempDir) && empty(Storage::disk('public')->files($tempDir))) {
                Storage::disk('public')->deleteDirectory($tempDir);
            }

            $this->createdImageIds[] = $image->id;
        }

        AdminLogger::settingsSaved('Bulk Image Upload', [
            'created_' . count($this->createdImageIds) . '_images',
        ]);

        Notification::make()
            ->title('Created ' . count($this->createdImageIds) . ' image(s)')
            ->success()
            ->send();

        $this->entries = [];
    }

    /**
     * Turn a raw uploaded filename into a presentable default title.
     * "My_Cool-Image.final v2.jpg" -> "My Cool Image Final V2"
     */
    protected function titleFromFilename(string $name): string
    {
        $base = pathinfo($name, PATHINFO_FILENAME);
        $clean = preg_replace('/[_\-.]+/', ' ', $base) ?? $base;
        $clean = preg_replace('/\s+/', ' ', trim($clean)) ?? $clean;
        return $clean === '' ? '' : Str::title($clean);
    }

    protected function generateUniqueSlug(string $title): string
    {
        $baseSlug = Str::slug($title) ?: 'image';
        $slug = $baseSlug;
        $suffix = 2;
        while (Image::withTrashed()->where('slug', $slug)->exists()) {
            $slug = $baseSlug . '-' . $suffix;
            $suffix++;
        }
        return $slug;
    }

    public function getCreatedImagesProperty(): Collection
    {
        if (empty($this->createdImageIds)) {
            return new Collection();
        }

        return Image::with('user', 'category')
            ->whereIn('id', $this->createdImageIds)
            ->orderBy('id')
            ->get();
    }
}

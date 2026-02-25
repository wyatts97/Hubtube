<?php

namespace App\Filament\Pages;

use App\Events\VideoUploaded;
use App\Filament\Concerns\HasCustomizableNavigation;
use App\Models\Category;
use App\Models\ScheduleTemplate;
use App\Models\User;
use App\Models\Video;
use App\Services\AdminLogger;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class BulkVideoUploader extends Page implements HasForms
{
    use HasCustomizableNavigation;
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

    /** @var array Created video IDs for status polling */
    public array $createdVideoIds = [];

    /** @var bool Whether we're in the creating/processing phase */
    public bool $isCreating = false;

    // "Apply to All" fields
    public ?int $bulkCategoryId = null;
    public ?int $bulkUserId = null;
    public ?int $bulkTemplateId = null;
    public array $bulkTags = [];
    public bool $bulkAgeRestricted = true;

    protected function getForms(): array
    {
        return [
            'uploadForm',
        ];
    }

    public function mount(): void
    {
        $this->bulkUserId = auth()->id();
        $this->uploadForm->fill([]);
    }

    public function uploadForm(Form $form): Form
    {
        return $form
            ->schema([
            \Filament\Forms\Components\FileUpload::make('video_files')
            ->label('Drop video files here or click to browse')
            ->disk('public')
            ->directory('videos/admin-uploads')
            ->acceptedFileTypes(['video/mp4', 'video/quicktime', 'video/x-msvideo', 'video/x-matroska', 'video/webm'])
            ->maxSize(5242880) // 5GB
            ->multiple()
            ->maxFiles(50)
            ->visibility('public')
            ->storeFileNamesIn('video_file_names')
            ->columnSpanFull(),
        ])
            ->statePath('uploadData');
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
                'title' => '',
                'description' => '',
                'category_id' => $this->bulkCategoryId,
                'tags' => $this->bulkTags,
                'user_id' => $this->bulkUserId ?? auth()->id(),
                'age_restricted' => $this->bulkAgeRestricted,
                'file_path' => $tempPath,
                'file_size' => Storage::disk('public')->exists($tempPath) ?Storage::disk('public')->size($tempPath) : 0,
                'file_name' => $originalName,
            ];
        }

        // Reset the upload form
        $this->uploadForm->fill([]);

        Notification::make()->title(count($paths) . ' file(s) added')->success()->send();
    }

    public function getCategoriesProperty(): \Illuminate\Support\Collection
    {
        return Category::active()->orderBy('name')->pluck('name', 'id');
    }

    public function getUsersProperty(): \Illuminate\Support\Collection
    {
        return User::orderBy('username')->pluck('username', 'id');
    }

    public function getTemplatesProperty(): \Illuminate\Database\Eloquent\Collection
    {
        return ScheduleTemplate::where('is_active', true)->orderBy('name')->get();
    }

    public function removeEntry(int $index): void
    {
        if (isset($this->entries[$index])) {
            // Delete the temp file
            $path = $this->entries[$index]['file_path'] ?? null;
            if ($path && Storage::disk('public')->exists($path)) {
                Storage::disk('public')->delete($path);
            }
            unset($this->entries[$index]);
            $this->entries = array_values($this->entries);
        }
    }

    public function applyBulkSettings(): void
    {
        foreach ($this->entries as &$entry) {
            if ($this->bulkCategoryId) {
                $entry['category_id'] = $this->bulkCategoryId;
            }
            if ($this->bulkUserId) {
                $entry['user_id'] = $this->bulkUserId;
            }
            if (!empty($this->bulkTags)) {
                $entry['tags'] = $this->bulkTags;
            }
            $entry['age_restricted'] = $this->bulkAgeRestricted;
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

        // Fetch schedule slots if a master template is selected
        $slots = [];
        if ($this->bulkTemplateId) {
            $template = ScheduleTemplate::find($this->bulkTemplateId);
            if ($template) {
                // Get enough slots for all entries
                $slots = $template->getNextSlots(count($this->entries), now());
            }
        }

        foreach ($this->entries as $index => $entry) {
            // Assign schedule slot if available
            $entry['scheduled_at'] = $slots[$index] ?? null;

            $video = $this->createSingleVideo($entry);
            if ($video) {
                $this->createdVideoIds[] = $video->id;
            }
        }

        $count = count($this->createdVideoIds);
        $this->entries = [];

        AdminLogger::settingsSaved('Bulk Video Upload', ["created_{$count}_videos"]);

        Notification::make()
            ->title("Created {$count} video(s) — processing will begin shortly")
            ->success()
            ->send();
    }

    protected function createSingleVideo(array $entry): ?Video
    {
        $title = trim($entry['title']);
        $slug = $this->generateUniqueSlug($title);
        $tempPath = $entry['file_path'];
        $extension = pathinfo($tempPath, PATHINFO_EXTENSION) ?: 'mp4';

        // Move from temp to final location
        $directory = "videos/{$slug}";
        $filename = Str::slug($title, '_') . '.' . $extension;
        $newPath = "{$directory}/{$filename}";

        if (!Storage::disk('public')->exists($tempPath)) {
            return null;
        }

        Storage::disk('public')->makeDirectory($directory);
        Storage::disk('public')->move($tempPath, $newPath);

        $video = Video::create([
            'user_id' => $entry['user_id'] ?? auth()->id(),
            'uuid' => (string)Str::uuid(),
            'title' => $title,
            'slug' => $slug,
            'description' => $entry['description'] ?? null,
            'category_id' => $entry['category_id'] ?: null,
            'privacy' => 'public',
            'age_restricted' => $entry['age_restricted'] ?? true,
            'tags' => $entry['tags'] ?? [],
            'status' => 'pending',
            'video_path' => $newPath,
            'storage_disk' => 'public',
            'size' => Storage::disk('public')->size($newPath),
            'is_approved' => false,
            'scheduled_at' => $entry['scheduled_at'] ?? null,
            'requires_schedule' => isset($entry['scheduled_at']) ? true : false,
            'published_at' => null,
        ]);

        event(new VideoUploaded($video));

        return $video;
    }

    protected function generateUniqueSlug(string $title): string
    {
        $baseSlug = Str::slug($title) ?: 'video';
        $slug = $baseSlug;
        $suffix = 2;
        while (Video::withTrashed()->where('slug', $slug)->exists()) {
            $slug = $baseSlug . '-' . $suffix;
            $suffix++;
        }
        return $slug;
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

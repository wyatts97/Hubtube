<?php

namespace App\Services;

use App\Events\VideoUploaded;
use App\Models\Video;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Shared bulk-video creation logic used by both BulkVideoUploader (sync path for
 * small batches) and CreateBulkVideosJob (async queue path for larger batches).
 *
 * Entries are the plain arrays produced by BulkVideoUploader::addUploadedFiles:
 *   [title, description, category_id, tags, user_id, age_restricted,
 *    file_path, file_size, file_name]
 *
 * All created videos are flagged `suppress_notifications=true` so that bulk
 * uploads never fire the admin-new-video or video-published emails.
 */
class BulkVideoCreator
{
    public function __construct(
        protected VideoService $videoService,
    ) {}

    /**
     * Create every entry. Returns the resulting video IDs in creation order.
     *
     * @param  array<int, array<string, mixed>>  $entries
     * @param  int  $actorId  fallback user_id when an entry has none
     * @return array<int, int>
     */
    public function createMany(array $entries, bool $addToQueue, int $actorId): array
    {
        $createdIds = [];
        $maxOrder = (int) (Video::max('queue_order') ?? 0);

        foreach ($entries as $entry) {
            if ($addToQueue) {
                $maxOrder++;
                $entry['queue_order'] = $maxOrder;
            }

            $video = $this->createOne($entry, $actorId);
            if ($video) {
                $createdIds[] = $video->id;
            }
        }

        if ($addToQueue) {
            $this->videoService->recalculateScheduleQueue();
        }

        return $createdIds;
    }

    public function createOne(array $entry, int $actorId): ?Video
    {
        $title = trim((string) ($entry['title'] ?? ''));
        if ($title === '') {
            return null;
        }

        $tempPath = (string) ($entry['file_path'] ?? '');
        if ($tempPath === '' || !Storage::disk('public')->exists($tempPath)) {
            return null;
        }

        $slug = $this->generateUniqueSlug($title);
        $extension = pathinfo($tempPath, PATHINFO_EXTENSION) ?: 'mp4';
        $directory = "videos/{$slug}";
        $filename = Str::slug($title, '_') . '.' . $extension;
        $newPath = "{$directory}/{$filename}";

        Storage::disk('public')->makeDirectory($directory);
        Storage::disk('public')->move($tempPath, $newPath);

        $tags = array_values(array_unique(array_filter(
            array_map('trim', (array) ($entry['tags'] ?? [])),
            fn ($t) => $t !== '' && $t !== null
        )));

        $video = Video::create([
            'user_id' => $entry['user_id'] ?? $actorId,
            'uuid' => (string) Str::uuid(),
            'title' => $title,
            'slug' => $slug,
            'description' => $entry['description'] ?? null,
            'category_id' => $entry['category_id'] ?: null,
            'privacy' => 'public',
            'age_restricted' => (bool) ($entry['age_restricted'] ?? true),
            'tags' => $tags,
            'status' => 'pending',
            'video_path' => $newPath,
            'storage_disk' => 'public',
            'size' => Storage::disk('public')->size($newPath),
            'is_approved' => isset($entry['queue_order']),
            'queue_order' => $entry['queue_order'] ?? null,
            'requires_schedule' => isset($entry['queue_order']),
            'suppress_notifications' => true,
            'published_at' => null,
        ]);

        event(new VideoUploaded($video, suppressNotifications: true));

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
}

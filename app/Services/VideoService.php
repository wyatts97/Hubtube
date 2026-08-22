<?php

namespace App\Services;

use App\Events\VideoUploaded;
use App\Models\PointsTransaction;
use App\Models\Setting;
use App\Models\User;
use App\Models\Video;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Support\Carbon;

class VideoService
{
    public function __construct(
        protected TagSyncService $tagSyncService,
    ) {}

    public function create(array $data, User $user): Video
    {
        $video = Video::create([
            'user_id' => $user->id,
            'uuid' => Str::uuid(),
            'title' => $data['title'],
            'slug' => $this->generateUniqueSlug($data['title']),
            'description' => $data['description'] ?? null,
            'category_id' => $data['category_id'] ?? null,
            'privacy' => $data['privacy'] ?? 'public',
            'age_restricted' => $data['age_restricted'] ?? true,
            'tags' => $data['tags'] ?? [],
            'status' => 'pending',
            'published_at' => now(),
        ]);

        if (isset($data['video_file'])) {
            $this->handleVideoUpload($video, $data['video_file']);
        }

        $this->tagSyncService->syncVideo($video);

        event(new VideoUploaded($video));

        return $video;
    }

    public function update(Video $video, array $data): Video
    {
        $video->update([
            'title' => $data['title'] ?? $video->title,
            'description' => $data['description'] ?? $video->description,
            'category_id' => $data['category_id'] ?? $video->category_id,
            'privacy' => $data['privacy'] ?? $video->privacy,
            'age_restricted' => $data['age_restricted'] ?? $video->age_restricted,
            'tags' => $data['tags'] ?? $video->tags,
            'geo_blocked_countries' => $data['geo_blocked_countries'] ?? $video->geo_blocked_countries,
            'monetization_enabled' => $data['monetization_enabled'] ?? $video->monetization_enabled,
            'price' => $data['price'] ?? $video->price,
            'rent_price' => $data['rent_price'] ?? $video->rent_price,
        ]);

        if (isset($data['thumbnail'])) {
            $this->handleThumbnailUpload($video, $data['thumbnail']);
        }

        $this->tagSyncService->syncVideo($video);

        return $video->fresh();
    }

    public function delete(Video $video): void
    {
        // Storage cleanup is handled by the Video model's deleting event
        $video->delete();
    }

    protected function generateUniqueSlug(string $title): string
    {
        $baseSlug = Str::slug($title) ?: 'video';

        // Short slugs (< 4 chars) can collide with real routes at the root level
        // (e.g. "otk" → sitename.com/otk could conflict with /api, /admin, etc.)
        // Append a short random suffix to make them distinct and URL-safe.
        if (Str::length($baseSlug) < 4) {
            $baseSlug .= '-' . Str::lower(Str::random(6));
        }

        // Truncate very long slugs to 200 chars to keep URLs manageable
        $baseSlug = Str::limit($baseSlug, 200, '');

        $slug = $baseSlug;
        $suffix = 2;
        while (Video::withTrashed()->where('slug', $slug)->exists()) {
            $slug = $baseSlug . '-' . $suffix;
            $suffix++;
        }
        return $slug;
    }

    protected function handleVideoUpload(Video $video, UploadedFile $file): void
    {
        // All assets live in videos/{slug}/ with title-based filenames
        $slug = $video->slug;
        $directory = "videos/{$slug}";
        $extension = $file->getClientOriginalExtension() ?: 'mp4';
        $filename = Str::slug($video->title, '_') . '.' . $extension;

        // Always upload to local first — FFmpeg needs local filesystem access for processing.
        // ProcessVideoJob will offload to cloud and update storage_disk after successful upload.
        $path = $file->storeAs($directory, $filename, 'public');

        $video->update([
            'video_path' => $path,
            'storage_disk' => 'public',
            'size' => $file->getSize(),
        ]);
    }

    protected function handleThumbnailUpload(Video $video, UploadedFile $file): void
    {
        $disk = $video->storage_disk ?? 'public';

        // Delete old thumbnail from whichever disk it's on
        if ($video->thumbnail) {
            StorageManager::delete($video->thumbnail, $disk);
        }

        // Store custom thumbnail in the video's directory
        $directory = "videos/{$video->slug}";
        $extension = $file->getClientOriginalExtension() ?: 'jpg';
        $filename = Str::slug($video->title, '_') . '_custom_thumb.' . $extension;

        if (StorageManager::isCloudDisk($disk)) {
            $path = "{$directory}/{$filename}";
            StorageManager::put($path, file_get_contents($file->getRealPath()), $disk);
        }
        else {
            $path = $file->storeAs($directory, $filename, 'public');
        }

        $video->update(['thumbnail' => $path]);
    }

    public function publish(Video $video): void
    {
        $video->update([
            'published_at' => now(),
            'is_approved' => true,
        ]);
    }

    /**
     * @param string|null $degradedReason When set, the video is being marked processed with a
     *   degraded/fallback output (e.g. raw original after a transcoding failure) rather than a
     *   clean multi-quality result. Recorded in `processing_fallback_reason` — a column separate
     *   from `failure_reason`, which is already reused for admin rejection reasons — surfaced in
     *   the admin panel without changing `status`, which many publish/approval/listing checks
     *   compare directly against 'processed'.
     */
    public function markAsProcessed(Video $video, array $qualities, ?string $degradedReason = null): void
    {
        $updateData = [
            'status' => 'processed',
            'qualities_available' => $qualities,
            'processing_completed_at' => now(),
            'processing_fallback_reason' => $degradedReason,
        ];

        // Auto-approve if global toggle is on (or trusted uploader) AND a publish time was provided.
        // Bulk uploads now set published_at to null so they stay in the scheduling queue until an admin sets a time.
        if ($video->published_at && $this->shouldAutoApprove($video)) {
            $updateData['is_approved'] = true;
            $updateData['published_at'] = $video->published_at;
        }

        $video->update($updateData);

        // Award points if the video was auto-approved
        if (!empty($updateData['is_approved']) && $video->user) {
            $this->awardAutoApprovePoints($video);
        }
    }

    protected function shouldAutoApprove(Video $video): bool
    {
        // Single Setting::getAll() batch fetch instead of two individual
        // Setting::get() calls — matters under bulk-approve loops.
        $settings = Setting::getAll();

        // Global auto-approve: all videos
        if ($settings['video_auto_approve'] ?? false) {
            return true;
        }

        // Per-user auto-approve: check if uploader's username is in the trusted list
        $trustedUsernames = $settings['video_auto_approve_usernames'] ?? [];
        if (is_string($trustedUsernames)) {
            $trustedUsernames = json_decode($trustedUsernames, true) ?? [];
        }

        if (!empty($trustedUsernames) && $video->user) {
            return in_array($video->user->username, $trustedUsernames, true);
        }

        return false;
    }

    protected function awardAutoApprovePoints(Video $video): void
    {
        $settings = Setting::getAll();

        if (!($settings['points_enabled'] ?? true) || !($settings['points_video_upload_enabled'] ?? true)) {
            return;
        }

        $points = (int) ($settings['points_per_video_upload'] ?? 100);
        if ($points <= 0) {
            return;
        }

        app(PointsService::class)->award(
            $video->user,
            PointsTransaction::TYPE_VIDEO_UPLOAD,
            $points,
            $video,
            'Video upload approved'
        );
    }

    public function markAsFailed(Video $video, ?string $reason = null): void
    {
        $video->update([
            'status' => 'failed',
            'failure_reason' => $reason,
            'processing_completed_at' => now(),
        ]);
    }

    /**
     * Walks the entire ordered queue and updates `scheduled_at` based on config.
     */
    public function recalculateScheduleQueue(): void
    {
        $videos = Video::whereNotNull('queue_order')
            ->whereNull('published_at')
            ->orderBy('queue_order')
            ->get();

        if ($videos->isEmpty())
            return;

        $postsPerDay = (int)Setting::get('schedule_posts_per_day', 1);
        $startHourInput = Setting::get('schedule_start_hour', '08:00:00');

        $intervalHours = 24 / max(1, $postsPerDay);

        // Find the next available start time
        $cursor = now()->copy();
        $startHourCarbon = Carbon::parse($startHourInput);

        $cursor->setTimeFromTimeString($startHourInput);
        if ($cursor->isPast()) {
            // Find the closest interval chunk to now without going into the past
            while ($cursor->isPast()) {
                $cursor->addHours($intervalHours);
            }
        }

        foreach ($videos as $index => $video) {
            $scheduledTime = $cursor->copy();
            $video->update([
                'queue_order' => $index + 1, // Fix any gaps
                'scheduled_at' => $scheduledTime,
            ]);
            $cursor->addHours($intervalHours);
        }
    }
}

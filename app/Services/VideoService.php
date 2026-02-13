<?php

namespace App\Services;

use App\Events\VideoUploaded;
use App\Models\Setting;
use App\Models\User;
use App\Models\Video;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class VideoService
{
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
            'is_short' => $data['is_short'] ?? false,
            'status' => 'pending',
            'published_at' => now(),
        ]);

        if (isset($data['video_file'])) {
            $this->handleVideoUpload($video, $data['video_file']);
        }

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
        } else {
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

    public function markAsProcessed(Video $video, array $qualities): void
    {
        $updateData = [
            'status' => 'processed',
            'qualities_available' => $qualities,
            'processing_completed_at' => now(),
        ];

        // Auto-approve if global toggle is on, or if uploader is in the trusted list
        if ($this->shouldAutoApprove($video)) {
            $updateData['is_approved'] = true;
            $updateData['published_at'] = $video->published_at ?? now();
        }

        $video->update($updateData);
    }

    protected function shouldAutoApprove(Video $video): bool
    {
        // Global auto-approve: all videos
        if (Setting::get('video_auto_approve', false)) {
            return true;
        }

        // Per-user auto-approve: check if uploader's username is in the trusted list
        $trustedUsernames = Setting::get('video_auto_approve_usernames', []);
        if (is_string($trustedUsernames)) {
            $trustedUsernames = json_decode($trustedUsernames, true) ?? [];
        }

        if (!empty($trustedUsernames) && $video->user) {
            return in_array($video->user->username, $trustedUsernames, true);
        }

        return false;
    }

    public function markAsFailed(Video $video, ?string $reason = null): void
    {
        $video->update([
            'status' => 'failed',
            'failure_reason' => $reason,
            'processing_completed_at' => now(),
        ]);
    }
}

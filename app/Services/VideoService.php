<?php

namespace App\Services;

use App\Events\VideoUploaded;
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
        $disk = $video->storage_disk ?? 'public';

        // Delete original video file
        if ($video->video_path) {
            StorageManager::delete($video->video_path, $disk);
        }

        // Delete thumbnail
        if ($video->thumbnail) {
            StorageManager::delete($video->thumbnail, $disk);
        }

        // Delete processed files directory (HLS segments, quality variants, etc.)
        $processedDir = "videos/{$video->user_id}/{$video->uuid}/processed";
        if (StorageManager::exists($processedDir, $disk)) {
            StorageManager::deleteDirectory($processedDir, $disk);
        }

        // Delete the entire video directory if empty
        $videoDir = "videos/{$video->user_id}/{$video->uuid}";
        if (StorageManager::exists($videoDir, $disk)) {
            $files = StorageManager::allFiles($videoDir, $disk);
            if (empty($files)) {
                StorageManager::deleteDirectory($videoDir, $disk);
            }
        }

        // Delete preview file if exists
        if ($video->preview_path) {
            StorageManager::delete($video->preview_path, $disk);
        }

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
        $activeDisk = StorageManager::getActiveDiskName();

        // Always upload to local first — FFmpeg needs local filesystem access for processing.
        // ProcessVideoJob will push processed files to cloud storage after transcoding.
        $path = $file->store(
            "videos/{$video->user_id}/{$video->uuid}",
            'public'
        );

        $video->update([
            'video_path' => $path,
            'storage_disk' => StorageManager::isCloudDisk() ? $activeDisk : 'public',
            'size' => $file->getSize(),
        ]);
    }

    protected function handleThumbnailUpload(Video $video, UploadedFile $file): void
    {
        $disk = $video->storage_disk ?? StorageManager::getActiveDiskName();

        // Delete old thumbnail from whichever disk it's on
        if ($video->thumbnail) {
            StorageManager::delete($video->thumbnail, $disk);
        }

        if (StorageManager::isCloudDisk($disk)) {
            // Upload directly to cloud storage
            $directory = "thumbnails/{$video->user_id}";
            $filename = $directory . '/' . Str::random(40) . '.' . $file->getClientOriginalExtension();
            StorageManager::put($filename, file_get_contents($file->getRealPath()), $disk);
            $path = $filename;
        } else {
            $path = $file->store(
                "thumbnails/{$video->user_id}",
                'public'
            );
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
        $video->update([
            'status' => 'processed',
            'qualities_available' => $qualities,
            'processing_completed_at' => now(),
        ]);
    }

    public function markAsFailed(Video $video, string $reason = null): void
    {
        $video->update([
            'status' => 'failed',
            'processing_completed_at' => now(),
        ]);
    }
}

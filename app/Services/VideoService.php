<?php

namespace App\Services;

use App\Events\VideoUploaded;
use App\Models\User;
use App\Models\Video;
use Illuminate\Http\UploadedFile;
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
            'slug' => Str::slug($data['title']) . '-' . Str::random(8),
            'description' => $data['description'] ?? null,
            'category_id' => $data['category_id'] ?? null,
            'privacy' => $data['privacy'] ?? 'public',
            'age_restricted' => $data['age_restricted'] ?? true,
            'tags' => $data['tags'] ?? [],
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
        if ($video->video_path) {
            Storage::disk('public')->delete($video->video_path);
        }

        if ($video->thumbnail) {
            Storage::disk('public')->delete($video->thumbnail);
        }

        $video->delete();
    }

    protected function handleVideoUpload(Video $video, UploadedFile $file): void
    {
        // Store in public storage for direct access when FFmpeg is not available
        $path = $file->store(
            "videos/{$video->user_id}/{$video->uuid}",
            'public'
        );

        $video->update([
            'video_path' => $path,
            'size' => $file->getSize(),
        ]);
    }

    protected function handleThumbnailUpload(Video $video, UploadedFile $file): void
    {
        if ($video->thumbnail) {
            Storage::disk('public')->delete($video->thumbnail);
        }

        $path = $file->store(
            "thumbnails/{$video->user_id}",
            'public'
        );

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

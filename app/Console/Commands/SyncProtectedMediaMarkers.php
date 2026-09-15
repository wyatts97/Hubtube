<?php

namespace App\Console\Commands;

use App\Models\Video;
use App\Services\ProtectedMediaService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Rebuild the privacy markers that make nginx hand private videos to Laravel.
 *
 * Markers are kept in step on every save, so this is for first deploy, a
 * restored backup, or anything that changed privacy without model events
 * (raw SQL, a quiet update).
 */
class SyncProtectedMediaMarkers extends Command
{
    protected $signature = 'videos:sync-media-protection';

    protected $description = 'Create privacy markers for private videos and remove stale ones';

    public function handle(ProtectedMediaService $media): int
    {
        $synced = 0;

        Video::withTrashed()
            ->where('is_embedded', false)
            ->select(['id', 'slug', 'privacy', 'is_embedded'])
            ->chunkById(500, function ($videos) use ($media, &$synced) {
                foreach ($videos as $video) {
                    $media->sync($video);
                    $synced++;
                }
            });

        // Directories left behind by videos that no longer exist.
        $removed = 0;
        $disk = Storage::disk('public');

        foreach ($disk->directories('videos') as $directory) {
            $marker = $directory.'/'.ProtectedMediaService::MARKER;

            if ($disk->exists($marker) && ! Video::withTrashed()->where('slug', basename($directory))->where('privacy', 'private')->exists()) {
                $disk->delete($marker);
                $removed++;
            }
        }

        $private = Video::withTrashed()->where('privacy', 'private')->count();

        $this->info("Checked {$synced} videos ({$private} private). Removed {$removed} stale markers.");

        // The marker protects videos/{slug}/ only. Anything still in the old
        // videos/{user_id}/{uuid}/ layout needs `storage:migrate` first.
        $unprotected = Video::withTrashed()
            ->where('privacy', 'private')
            ->where('is_embedded', false)
            ->whereNotNull('video_path')
            ->get(['id', 'slug', 'video_path'])
            ->reject(fn (Video $video) => str_starts_with($video->video_path, "videos/{$video->slug}/"));

        foreach ($unprotected as $video) {
            $this->warn("Video #{$video->id} is private but stored outside videos/{$video->slug}/ ({$video->video_path}); its files are not protected.");
        }

        return self::SUCCESS;
    }
}

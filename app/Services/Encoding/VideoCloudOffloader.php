<?php

namespace App\Services\Encoding;

use App\Models\Video;
use App\Services\StorageManager;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Pushes a processed video's files from the local disk to cloud storage.
 *
 * FFmpeg needs local files, so everything is produced on the public disk
 * first. Once every file has uploaded, the video's storage_disk is switched
 * and, if configured, the local copies are removed.
 */
class VideoCloudOffloader
{
    /** @param  array<string, mixed>  $settings */
    public function offload(Video $video, string $targetDisk, array $settings): void
    {
        $localDisk = Storage::disk('public');
        $videoDir = "videos/{$video->slug}";
        $uploaded = 0;
        $failed = 0;

        Log::info('Offloading processed video to cloud storage', [
            'video_id' => $video->id,
            'target_disk' => $targetDisk,
        ]);

        $allFiles = $localDisk->allFiles($videoDir);

        foreach ($allFiles as $file) {
            if (StorageManager::uploadLocalFile($localDisk->path($file), $file, $targetDisk)) {
                $uploaded++;
            } else {
                $failed++;
                Log::warning('Failed to upload file to cloud', ['file' => $file, 'disk' => $targetDisk]);
            }
        }

        // Stored paths that can live outside the video directory (legacy layouts).
        foreach ([$video->thumbnail, $video->preview_path, $video->scrubber_vtt_path] as $path) {
            if (! $path || in_array($path, $allFiles, true) || ! file_exists($localDisk->path($path))) {
                continue;
            }

            StorageManager::uploadLocalFile($localDisk->path($path), $path, $targetDisk) ? $uploaded++ : $failed++;
        }

        StorageManager::cleanupTemp();

        Log::info('Cloud offload finished', ['video_id' => $video->id, 'uploaded' => $uploaded, 'failed' => $failed]);

        if ($failed > 0) {
            Log::warning('Some files failed to upload; keeping the video on local storage', [
                'video_id' => $video->id,
                'failed' => $failed,
            ]);

            return;
        }

        $video->forceFill(['storage_disk' => $targetDisk])->save();

        if (! ($settings['cloud_offloading_delete_local'] ?? false)) {
            return;
        }

        foreach ($allFiles as $file) {
            $localDisk->delete($file);
        }

        if ($video->thumbnail && $localDisk->exists($video->thumbnail)) {
            $localDisk->delete($video->thumbnail);
        }

        if ($localDisk->exists($videoDir) && empty($localDisk->allFiles($videoDir))) {
            $localDisk->deleteDirectory($videoDir);
        }
    }
}

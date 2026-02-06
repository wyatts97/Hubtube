<?php

namespace App\Jobs;

use App\Models\Video;
use App\Services\BunnyStreamService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class DownloadBunnyVideoJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;
    public int $timeout = 900; // 15 minutes per video
    public int $backoff = 60;

    public function __construct(
        public Video $video,
        public string $targetDisk = 'public'
    ) {}

    public function handle(BunnyStreamService $bunny): void
    {
        $video = $this->video;

        if (!$video->is_embedded || !$video->source_video_id) {
            Log::info('DownloadBunnyVideo: skipping non-embedded video', ['id' => $video->id]);
            return;
        }

        $bunnyVideoId = $video->source_video_id;
        $storageBase = "videos/{$video->user_id}/{$video->uuid}";

        Log::info('DownloadBunnyVideo: starting', [
            'video_id' => $video->id,
            'bunny_id' => $bunnyVideoId,
            'title' => $video->title,
        ]);

        // 1. Get video info from Bunny API to determine best resolution
        $videoInfo = $bunny->getVideo($bunnyVideoId);
        $hasOriginal = $videoInfo['hasOriginal'] ?? false;
        $hasMp4Fallback = $videoInfo['hasMP4Fallback'] ?? false;

        // 2. Download the video file
        $videoPath = null;

        if ($hasOriginal) {
            // Prefer the original uploaded file
            $originalUrl = $bunny->getOriginalUrl($bunnyVideoId);
            $videoPath = $bunny->downloadFile(
                $originalUrl,
                "{$storageBase}/original.mp4",
                $this->targetDisk
            );
        }

        if (!$videoPath && $hasMp4Fallback && $videoInfo) {
            // Fall back to best available MP4 resolution
            $bestRes = $bunny->getBestMp4Resolution($videoInfo);
            $mp4Url = $bunny->getMp4Url($bunnyVideoId, $bestRes);
            $videoPath = $bunny->downloadFile(
                $mp4Url,
                "{$storageBase}/play_{$bestRes}.mp4",
                $this->targetDisk
            );
        }

        if (!$videoPath) {
            Log::error('DownloadBunnyVideo: failed to download video file', [
                'video_id' => $video->id,
                'bunny_id' => $bunnyVideoId,
                'hasOriginal' => $hasOriginal,
                'hasMp4Fallback' => $hasMp4Fallback,
            ]);
            $video->update(['status' => 'download_failed']);
            return;
        }

        // 3. Download thumbnail
        $thumbnailFileName = $videoInfo['thumbnailFileName'] ?? null;
        $thumbnailUrl = $bunny->getThumbnailUrl($bunnyVideoId, $thumbnailFileName);
        $thumbnailPath = $bunny->downloadFile(
            $thumbnailUrl,
            "thumbnails/{$video->user_id}/{$video->uuid}_thumb.jpg",
            $this->targetDisk
        );

        // 4. Download animated preview WebP
        $previewWebpUrl = $bunny->getPreviewUrl($bunnyVideoId);
        $previewPath = $bunny->downloadFile(
            $previewWebpUrl,
            "{$storageBase}/preview.webp",
            $this->targetDisk
        );

        // 5. Update the video record to become a native video
        $updateData = [
            'video_path' => $videoPath,
            'is_embedded' => false,
            'embed_url' => null,
            'embed_code' => null,
            'external_thumbnail_url' => null,
            'external_preview_url' => null,
            'status' => 'processed',
            'qualities_available' => ['original'],
        ];

        if ($thumbnailPath) {
            $updateData['thumbnail'] = $thumbnailPath;
        }

        if ($previewPath) {
            $updateData['preview_path'] = $previewPath;
        }

        $video->update($updateData);

        Log::info('DownloadBunnyVideo: completed', [
            'video_id' => $video->id,
            'bunny_id' => $bunnyVideoId,
            'video_path' => $videoPath,
            'thumbnail' => $thumbnailPath,
            'preview' => $previewPath,
            'disk' => $this->targetDisk,
        ]);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('DownloadBunnyVideo: job failed permanently', [
            'video_id' => $this->video->id,
            'error' => $exception->getMessage(),
        ]);

        $this->video->update(['status' => 'download_failed']);
    }
}

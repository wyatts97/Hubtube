<?php

namespace App\Jobs;

use App\Models\Video;
use App\Services\Encoding\RenditionCoordinator;
use App\Services\VideoService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Final step once every rendition of a video has finished or failed:
 * watermarked original swap, cloud offload, and recording any problems.
 * See RenditionCoordinator::complete().
 */
class CompleteVideoProcessingJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    // Cloud offload of a large video with every rendition can take a while.
    public int $timeout = 3600;

    public int $backoff = 60;

    public function __construct(
        public int $videoId,
    ) {}

    public function handle(RenditionCoordinator $coordinator): void
    {
        $video = Video::find($this->videoId);

        if ($video) {
            $coordinator->complete($video);
        }
    }

    public function failed(Throwable $exception): void
    {
        Log::error('Completing video processing failed', [
            'video_id' => $this->videoId,
            'error' => $exception->getMessage(),
        ]);

        $video = Video::find($this->videoId);

        if (! $video) {
            return;
        }

        app(RenditionCoordinator::class)->setStage($video, null);

        // Never leave a video stuck in "processing": fall back to the upload.
        if ($video->status !== 'processed') {
            app(VideoService::class)->markAsProcessed(
                $video,
                ['original'],
                'Completing processing failed: '.Str::limit($exception->getMessage(), 500)
            );
        }
    }
}

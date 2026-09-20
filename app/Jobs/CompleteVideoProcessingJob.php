<?php

namespace App\Jobs;

use App\Models\Video;
use App\Services\Encoding\RenditionCoordinator;
use App\Services\Media\MediaCompressService;
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

        if (! $video) {
            return;
        }

        $coordinator->complete($video);

        $this->compressMaster($video->refresh());

        // Index the finished video's folder in one pass. Every rendition,
        // poster, sprite sheet and VTT this pipeline wrote lands under
        // videos/{slug}, and none of it went through the Media Library — so
        // without this it stays invisible there until the next scheduled scan.
        // Once at completion rather than per rendition: they share the folder.
        IndexMediaDirectoryJob::dispatch('videos/'.$video->slug);
    }

    /**
     * Queue a re-compression of the upload this video is served from.
     *
     * The upload is the single biggest file a video owns and nothing streams
     * from it — the player is handed HLS, and the renditions are encoded from
     * it once. On the measured library these were 66 GB, 60% of all video
     * storage, so leaving them untouched is what made storage grow three
     * times faster than the catalogue.
     *
     * The result only replaces the upload after MediaCompressService has
     * verified it is smaller, the same length and a real video stream; if it
     * is not, the upload stays exactly as it is.
     */
    protected function compressMaster(Video $video): void
    {
        $compress = app(MediaCompressService::class);
        $path = (string) $video->video_path;

        $skip = $video->is_embedded
            || ($video->storage_disk ?? 'public') !== 'public'
            || $path === ''
            || ! $compress->isVideoPath($path)
            // Already one of ours: compressing again would cost quality for
            // very little, on every re-run.
            || $compress->isCompressedOutput($path)
            || $compress->readableSize($path) === null;

        if ($skip) {
            return;
        }

        $codec = $compress->defaultCodec();

        if (! ($compress->available()[$codec] ?? false)) {
            return;
        }

        CompressMediaFileJob::dispatch($path, $codec, 'balanced', requestedBy: null, replaceOriginal: true);
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

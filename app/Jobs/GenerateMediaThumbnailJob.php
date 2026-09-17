<?php

namespace App\Jobs;

use App\Models\MediaFile;
use App\Services\FileManagerThumbnailService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Produce one Media Library thumbnail, off the request.
 *
 * Generation used to happen while the page was rendering — a GD decode, resize
 * and WebP encode per image, and an ffmpeg frame grab per video, for every
 * tile on screen. The state machine on media_files replaces that: the page only
 * ever reads a row and, at most, dispatches this.
 *
 * Unique on the file's path hash so re-renders of the same page (and the
 * per-page dispatch on each of them) collapse into one job.
 */
class GenerateMediaThumbnailJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    /** An ffmpeg frame grab on a large file can take a while. */
    public int $timeout = 300;

    /** Long enough to cover the job's own runtime, so a crash cannot wedge it. */
    public int $uniqueFor = 600;

    public function __construct(public int $mediaFileId)
    {
        $this->onQueue('media-thumbnails');
    }

    public function uniqueId(): string
    {
        return (string) $this->mediaFileId;
    }

    public function handle(FileManagerThumbnailService $thumbnails): void
    {
        $file = MediaFile::find($this->mediaFileId);

        if (! $file) {
            // The file was deleted or re-indexed away while this waited.
            return;
        }

        $result = $thumbnails->generate($file);

        $attributes = [
            'thumbnail_state' => $result->state,
            'thumbnail_path' => $result->path,
            'thumbnail_error' => $result->error,
            'thumbnail_generated_at' => now(),
        ];

        // Attempts only count against a real failure: an unsupported format
        // never retries anyway, and a missing ffmpeg is not this file's fault.
        if ($result->state === MediaFile::THUMB_FAILED) {
            $attributes['thumbnail_attempts'] = $file->thumbnail_attempts + 1;

            // Logged once, on the transition — not once per render, which is
            // what the old inline generation did for every SVG on the page.
            Log::warning('Media Library: thumbnail generation failed', [
                'path' => $file->path,
                'error' => $result->error,
            ]);
        }

        // Dimensions and duration are read while the file is already open, so
        // the listing never has to probe for them.
        if ($result->width !== null) {
            $attributes['width'] = $result->width;
        }

        if ($result->height !== null) {
            $attributes['height'] = $result->height;
        }

        if ($result->durationSeconds !== null) {
            $attributes['duration_seconds'] = $result->durationSeconds;
        }

        $file->update($attributes);
    }

    /**
     * A job that died outright (timeout, worker kill) must not sit in `queued`
     * for ever, or nothing will ever pick it up again.
     */
    public function failed(?Throwable $exception): void
    {
        MediaFile::query()
            ->whereKey($this->mediaFileId)
            ->update([
                'thumbnail_state' => MediaFile::THUMB_FAILED,
                'thumbnail_error' => $exception?->getMessage() ?? 'The job did not finish.',
            ]);
    }
}

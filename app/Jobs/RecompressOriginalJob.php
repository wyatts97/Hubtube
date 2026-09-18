<?php

namespace App\Jobs;

use App\Models\Setting;
use App\Models\StorageReclaim;
use App\Services\Encoding\FfmpegCommands;
use App\Services\Encoding\FfmpegRunner;
use App\Services\Storage\StorageReclaimService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Re-compress a video's original upload.
 *
 * The original is usually the single biggest file a video owns — whatever the
 * uploader gave us, often camera footage at tens of megabits — and it is
 * served three ways: as the player's fallback source, as "original" in the
 * quality menu, and through the Pro download route. So this is the reclaim
 * with the most to gain and the most to be careful about.
 *
 * Two decisions do most of that carefulness:
 *
 *  - **The output goes to a new filename.** nginx sets `expires 30d` on .mp4
 *    under /storage and Cloudflare sits in front with no purge integration, so
 *    overwriting in place would serve stale bytes for a month. A new name
 *    sidesteps the caches entirely, *is* the "keep the old file" requirement
 *    rather than a second mechanism for it, and makes accepting a single quiet
 *    column update. Explicitly **not** `processed/original_watermarked.mp4`:
 *    RenditionCoordinator::complete() swaps any completed `original` encoding
 *    row over video_path, so a file there would be picked up later by an
 *    unrelated re-encode.
 *  - **Nothing about the video changes until an admin accepts.** video_path
 *    still names the old file for the whole of this job, so playback, the
 *    quality menu and the download route are untouched even if it fails
 *    half-way.
 */
class RecompressOriginalJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    /** Never auto-retried: a half-written encode is looked at, not repeated. */
    public int $tries = 1;

    public int $timeout = 7200;

    public function __construct(public int $reclaimId)
    {
        $this->onQueue('storage-reclaim');
    }

    public function uniqueId(): string
    {
        return 'reclaim-original-'.$this->reclaimId;
    }

    public function handle(StorageReclaimService $reclaims, FfmpegRunner $runner): void
    {
        $reclaim = StorageReclaim::find($this->reclaimId);

        if (! $reclaim || $reclaim->status !== StorageReclaim::PENDING) {
            return;
        }

        $video = $reclaim->video;

        if (! $video) {
            $this->settleAs($reclaim, StorageReclaim::FAILED, 'The video no longer exists.');

            return;
        }

        if ($reason = $reclaims->eligibility($video, $reclaim->id)) {
            $this->settleAs($reclaim, StorageReclaim::SKIPPED, $reason);

            return;
        }

        if (! $runner->isAvailable() || ! Setting::get('ffmpeg_enabled', true)) {
            $this->settleAs($reclaim, StorageReclaim::SKIPPED, 'FFmpeg is not available on this host.');

            return;
        }

        $disk = Storage::disk('public');
        $source = (string) $video->video_path;
        $target = $reclaims->recompressedPathFor($source, $reclaim->id);
        $sourceBytes = $reclaims->readableSize($source);

        if ($sourceBytes === null) {
            $this->settleAs($reclaim, StorageReclaim::SKIPPED, 'The uploaded file could not be read.');

            return;
        }

        $reclaim->forceFill([
            'status' => StorageReclaim::RUNNING,
            'started_at' => now(),
            'source_path' => $source,
            // The old file stays exactly where it is and keeps serving; only
            // an accept repoints the column away from it.
            'kept_path' => $source,
            'before_bytes' => $sourceBytes,
            'before_duration_ms' => (int) round(((float) $video->duration) * 1000),
        ])->saveQuietly();

        $settings = $reclaim->settings_snapshot ?? $reclaims->settings();

        $commands = (new FfmpegCommands(Setting::getAll()))->withOverrides([
            'preset' => $settings['preset'] ?? 'slow',
            'crf' => $settings['crf'] ?? 24,
            // The original is neither chunked nor HLS-packaged, so a forced
            // keyframe every two seconds costs bytes for nothing here.
            'keyframes' => false,
            // Re-encoding already-lossy AAC loses quality for negligible
            // bytes.
            'copy_audio' => true,
        ]);

        // No watermark filter: eligibility() refuses a video whose watermark
        // has not been drawn yet, so the source already carries it and drawing
        // again would double it.
        $command = sprintf(
            '%s -hide_banner -nostdin -y -i %s -map 0:v:0 -map 0:a:0? %s %s -movflags +faststart %s 2>&1',
            $commands->ffmpeg(),
            escapeshellarg($disk->path($source)),
            $commands->videoArgs(),
            $commands->audioArgs(),
            escapeshellarg($disk->path($target)),
        );

        try {
            [$exitCode, $output] = $runner->run($command, $commands->timeout());
        } catch (Throwable $e) {
            $this->cleanUp($target);
            $this->settleAs($reclaim, StorageReclaim::FAILED, $e->getMessage());

            return;
        }

        if ($exitCode !== 0) {
            Log::warning('Original re-compression failed', [
                'reclaim' => $reclaim->id,
                'exit_code' => $exitCode,
                'output' => substr($output, 0, 500),
            ]);

            $this->cleanUp($target);
            $this->settleAs($reclaim, StorageReclaim::FAILED, 'FFmpeg exited with code '.$exitCode.'.');

            return;
        }

        // Verified before it is offered: same length, a real video stream, and
        // a saving worth the quality trade. A rejection deletes the candidate
        // and leaves video_path pointing where it always was.
        $reclaims->settle($reclaim, $target, $video);
    }

    protected function cleanUp(string $path): void
    {
        $disk = Storage::disk('public');

        if ($disk->exists($path)) {
            $disk->delete($path);
        }
    }

    protected function settleAs(StorageReclaim $reclaim, string $status, string $message): void
    {
        $reclaim->forceFill([
            'status' => $status,
            'error' => $message,
            'finished_at' => now(),
        ])->saveQuietly();
    }

    public function failed(Throwable $exception): void
    {
        StorageReclaim::where('id', $this->reclaimId)
            ->whereIn('status', [StorageReclaim::PENDING, StorageReclaim::RUNNING])
            ->update([
                'status' => StorageReclaim::FAILED,
                'error' => $exception->getMessage(),
                'finished_at' => now(),
            ]);
    }
}

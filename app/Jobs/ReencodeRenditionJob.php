<?php

namespace App\Jobs;

use App\Models\StorageReclaim;
use App\Models\VideoEncoding;
use App\Services\Encoding\RenditionCoordinator;
use App\Services\Storage\StorageReclaimService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Re-encode one rendition more tightly, keeping the old file until reviewed.
 *
 * This job does not encode anything itself: it sets the existing pipeline up
 * to redo one rendition with slower settings, then hands over. Everything
 * after that is the normal path — prepare() skips artefacts that exist,
 * plan() re-plans only the flipped row, HLS is repackaged for free, and
 * complete() routes through VideoService::updateRenditions(), so there is no
 * re-approval, no points and no notification.
 *
 * The two things it must get right:
 *
 *  - **The live file keeps serving.** The old rendition is *hardlinked* aside
 *    rather than moved: a second directory entry for the same inode, so it
 *    costs no bytes, the canonical path keeps serving the old file for the
 *    whole encode, and FinalizeRenditionJob's closing rename() replaces only
 *    the directory entry — the kept link still holds the original bytes.
 *  - **The status flip, not a delete, is what forces the redo.** plan() skips
 *    a completed rendition whose file exists, so deleting the file would be
 *    the obvious way to force it — and would leave a serving gap.
 */
class ReencodeRenditionJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    /** A half-set-up reclaim is looked at, never repeated. */
    public int $tries = 1;

    public int $timeout = 600;

    public function __construct(public int $reclaimId)
    {
        $this->onQueue('storage-reclaim');
    }

    public function uniqueId(): string
    {
        return 'reclaim-rendition-'.$this->reclaimId;
    }

    public function handle(StorageReclaimService $reclaims, RenditionCoordinator $coordinator): void
    {
        $reclaim = StorageReclaim::find($this->reclaimId);

        if (! $reclaim || $reclaim->status !== StorageReclaim::PENDING) {
            return;
        }

        $video = $reclaim->video;

        if (! $video) {
            $this->fail($reclaim, 'The video no longer exists.');

            return;
        }

        if ($reason = $reclaims->eligibility($video, StorageReclaim::TARGET_RENDITION, $reclaim->quality, $reclaim->id)) {
            $reclaim->forceFill([
                'status' => StorageReclaim::SKIPPED,
                'error' => $reason,
                'finished_at' => now(),
            ])->saveQuietly();

            return;
        }

        $disk = Storage::disk('public');
        $live = $reclaims->renditionPath($video, $reclaim->quality);
        $kept = $reclaims->keptPathFor($live, $reclaim->id);

        try {
            [$keptPath, $isHardlink] = $reclaims->holdAside($live, $kept);
        } catch (Throwable $e) {
            Log::error('Rendition reclaim could not hold the old file', [
                'reclaim' => $reclaim->id,
                'error' => $e->getMessage(),
            ]);
            $this->fail($reclaim, 'The existing file could not be set aside: '.$e->getMessage());

            return;
        }

        $reclaim->forceFill([
            'status' => StorageReclaim::RUNNING,
            'started_at' => now(),
            'source_path' => $live,
            'kept_path' => $keptPath,
            'kept_is_hardlink' => $isHardlink,
            'before_bytes' => $disk->size($live),
            'before_duration_ms' => (int) round(((float) $video->duration) * 1000),
        ])->saveQuietly();

        // The same lock the coordinator takes, so this cannot interleave with
        // a rendition finishing and re-planning underneath it.
        Cache::lock("video-renditions:{$video->id}", 60)->block(30, function () use ($reclaim, $video) {
            VideoEncoding::where('video_id', $video->id)
                ->where('quality', $reclaim->quality)
                ->update([
                    // settings_overrides is deliberately not written here:
                    // plan() rebuilds this row and reads the settings from the
                    // ledger, so writing them now would only be overwritten.
                    //
                    // Cleared so a reclaim of the lowest rendition cannot
                    // inherit its place at the front of the encoding queue.
                    'is_priority' => false,
                    'status' => VideoEncoding::PENDING,
                    'progress' => 0,
                    'chunks_completed' => 0,
                    'error' => null,
                    'size' => null,
                    'started_at' => null,
                    'completed_at' => null,
                    // Fences off any job still in flight from the last run.
                    'run_id' => (string) Str::uuid(),
                ]);
        });

        // From here it is the ordinary pipeline. renditionFinished() calls
        // back into the service to measure the result.
        ProcessVideoJob::dispatch($video)->onQueue('storage-reclaim');
    }

    protected function fail(StorageReclaim $reclaim, string $message): void
    {
        $reclaim->forceFill([
            'status' => StorageReclaim::FAILED,
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

<?php

namespace App\Jobs;

use App\Models\StorageReclaim;
use App\Models\VideoEncoding;
use App\Services\Encoding\HlsPackager;
use App\Services\Storage\StorageReclaimService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Give up a video's duplicate HLS copy.
 *
 * `generate_hls` defaults to on, so every video carries a second complete copy
 * of its renditions as .ts segments under `processed/hls` — for a typical
 * ladder that is as many bytes again as the MP4s themselves, which makes this
 * the largest reclaim on the site and the only one that re-encodes nothing.
 *
 * The whole operation is one `rename()`: the tree is moved aside, not deleted,
 * so reverting is another rename and costs no copy either way. With no HLS
 * playlists left, `writeMasterPlaylist()` unlinks the master by itself,
 * `Video::hls_playlist_url` then returns null, and the player falls back to
 * progressive MP4. `qualities_available` needs no change — the renditions are
 * all still there.
 */
class ReclaimHlsJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    /** Never retried: a half-moved tree must be looked at, not repeated. */
    public int $tries = 1;

    public int $timeout = 600;

    public function __construct(public int $reclaimId)
    {
        $this->onQueue('storage-reclaim');
    }

    public function uniqueId(): string
    {
        return 'reclaim-hls-'.$this->reclaimId;
    }

    public function handle(StorageReclaimService $reclaims, HlsPackager $packager): void
    {
        $reclaim = StorageReclaim::find($this->reclaimId);

        if (! $reclaim || $reclaim->status !== StorageReclaim::PENDING) {
            return;
        }

        $video = $reclaim->video;

        if (! $video) {
            $reclaim->forceFill([
                'status' => StorageReclaim::FAILED,
                'error' => 'The video no longer exists.',
                'finished_at' => now(),
            ])->saveQuietly();

            return;
        }

        // Re-checked here, not just at request time: the request may have sat
        // in the queue while a rendition was deleted, and this is the one
        // reclaim that could leave a quality with no copy at all.
        // Its own row is excluded, or "a reclaim is already in progress" —
        // this one — would answer first and mask the check below it.
        if ($reason = $reclaims->eligibility($video, StorageReclaim::TARGET_HLS, null, $reclaim->id)) {
            $reclaim->forceFill([
                'status' => StorageReclaim::SKIPPED,
                'error' => $reason,
                'finished_at' => now(),
            ])->saveQuietly();

            return;
        }

        $disk = Storage::disk('public');
        $source = $reclaims->hlsDir($video);
        $kept = $source.'.reclaim-'.$reclaim->id;

        $reclaim->forceFill([
            'status' => StorageReclaim::RUNNING,
            'started_at' => now(),
            'source_path' => $source,
            'before_bytes' => $this->treeBytes($source),
        ])->saveQuietly();

        try {
            if ($disk->directoryExists($kept)) {
                // A previous attempt died between the rename and the update.
                // Its tree is the one to keep holding, so leave it alone.
                $disk->deleteDirectory($source);
            } else {
                $disk->move($source, $kept);
            }

            // With no playlists under hls/, this unlinks master.m3u8 — which
            // is what makes hls_playlist_url go null and the player fall back.
            $packager->writeMasterPlaylist(
                $disk->path($reclaims->processedDir($video)),
                $video->encodings()->where('status', VideoEncoding::COMPLETED)->with('profile')->get(),
            );
        } catch (Throwable $e) {
            Log::error('HLS reclaim failed', ['reclaim' => $reclaim->id, 'error' => $e->getMessage()]);

            $reclaim->forceFill([
                'status' => StorageReclaim::FAILED,
                'error' => $e->getMessage(),
                'finished_at' => now(),
            ])->saveQuietly();

            return;
        }

        $reclaim->forceFill([
            'status' => StorageReclaim::AWAITING_REVIEW,
            'kept_path' => $kept,
            // Nothing replaces it: the whole tree is the saving.
            'after_bytes' => 0,
            'keep_until' => $reclaims->keepUntil(),
            'finished_at' => now(),
        ])->saveQuietly();

        // The master playlist changed under its own name, and nginx caches it.
        $reclaims->markMediaChanged($video);
    }

    /** Recursive size of a directory, from the disk rather than the index. */
    protected function treeBytes(string $directory): int
    {
        $disk = Storage::disk('public');
        $bytes = 0;

        foreach ($disk->allFiles($directory) as $file) {
            $bytes += $disk->size($file);
        }

        return $bytes;
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

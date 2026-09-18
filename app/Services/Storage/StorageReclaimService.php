<?php

namespace App\Services\Storage;

use App\Jobs\IndexMediaDirectoryJob;
use App\Jobs\RecompressOriginalJob;
use App\Models\Setting;
use App\Models\StorageReclaim;
use App\Models\User;
use App\Models\Video;
use App\Services\AdminLogger;
use App\Services\Encoding\MediaProbe;
use App\Services\Media\MediaReferenceResolver;
use App\Services\Media\MediaStorageReport;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Requesting and reviewing re-compressions of original uploads.
 *
 * Reclaiming space means replacing a file a running site is serving, on a box
 * with no media backups (config/backup.php excludes storage/app/public).
 * Everything here is built around one invariant:
 *
 *   **The file at video_path is always the current good one.**
 *
 * The re-encode is written to a *new* filename and the column is not repointed
 * until an admin accepts, so there is no window in which video_path names a
 * partial encode — which is also why a failed accept or a discarded attempt
 * can never lose the file the site is serving.
 *
 * Scope is deliberately narrow: **the original upload only.** The encoded
 * renditions and the HLS segment tree are what the player actually streams
 * (VideoPlayer.vue hands the player the HLS manifest whenever it exists, and
 * falls back to the rendition MP4s), so shrinking or discarding those trades
 * real playback capability for disk. This feature reclaims the one file
 * nothing streams from.
 *
 * The Filament page, the Media Library action, the console command and the
 * tests all come through here, so those four cannot disagree about what is
 * allowed.
 */
class StorageReclaimService
{
    public function __construct(
        protected MediaReferenceResolver $references,
    ) {}

    // ── Settings ────────────────────────────────────────────────────────────

    /**
     * The encoder settings a reclaim runs with, snapshotted onto every row.
     *
     * Normal encoding already uses CRF — the default is CRF 22 at preset
     * `veryfast` — so "compress this more precisely" means a slower preset and
     * a higher CRF, not a different rate control. Both are admin-visible: a
     * reclaim is irreversible once accepted and the quality trade is a
     * judgement call.
     */
    public function settings(): array
    {
        return [
            'preset' => (string) Setting::get('reclaim_preset', 'slow'),
            'crf' => (int) Setting::get('ffmpeg_crf', 22) + (int) Setting::get('reclaim_crf_delta', 2),
            'min_saving_percent' => (int) Setting::get('reclaim_min_saving_percent', 15),
        ];
    }

    // ── Eligibility ─────────────────────────────────────────────────────────

    /**
     * Why this video's original cannot be re-compressed, or null if it can.
     *
     * Returns a human reason rather than a bool so the same call can drive a
     * disabled button's tooltip, a console command's output and a test's
     * assertion — the pattern MediaGuard already uses. Every caller must go
     * through this; nothing else is allowed to decide.
     *
     * $ignoreReclaimId is how a running job re-checks its own work: its own
     * row is active by definition, and without excluding it that answer would
     * mask every check below it.
     */
    public function eligibility(Video $video, ?int $ignoreReclaimId = null): ?string
    {
        // The source is read from the local public disk, and the encoder only
        // offloads to cloud storage after it finishes, so an offloaded video
        // has no local file to work from.
        if (($video->storage_disk ?? 'public') !== 'public') {
            return 'This video is stored on '.$video->storage_disk.', and reclaim only works on local storage.';
        }

        if ($video->is_embedded) {
            return 'Embedded videos have no files of their own.';
        }

        if (! $video->canReencode()) {
            return $video->isEncoding()
                ? 'This video is still encoding.'
                : 'This video has no finished local files to reclaim.';
        }

        if ((int) $video->duration <= 0) {
            return 'This video has no known duration, so a re-encode could not be verified.';
        }

        // A watermark is drawn once, into processed/original_watermarked.mp4,
        // and .watermark_done records that it happened. Re-encoding the current
        // original without that marker risks drawing a second watermark over
        // the first — that video belongs in the normal pipeline, not here.
        if ($this->watermarkPending($video)) {
            return 'A watermark is configured but has not been applied yet.';
        }

        if ($this->hasActive($video, $ignoreReclaimId)) {
            return 'A reclaim for this video is already in progress or awaiting review.';
        }

        return null;
    }

    public function isEligible(Video $video, ?int $ignoreReclaimId = null): bool
    {
        return $this->eligibility($video, $ignoreReclaimId) === null;
    }

    protected function watermarkPending(Video $video): bool
    {
        if (! Setting::get('watermark_enabled', false)) {
            return false;
        }

        return ! Storage::disk('public')->exists($this->videoDir($video).'/.watermark_done');
    }

    public function hasActive(Video $video, ?int $ignoreReclaimId = null): bool
    {
        return StorageReclaim::query()
            ->where('video_id', $video->id)
            ->when($ignoreReclaimId, fn ($query) => $query->whereKeyNot($ignoreReclaimId))
            ->active()
            ->exists();
    }

    // ── Paths ───────────────────────────────────────────────────────────────

    public function videoDir(Video $video): string
    {
        return dirname((string) $video->video_path);
    }

    /**
     * Where a re-compressed original is written.
     *
     * A new filename rather than an overwrite, because nginx caches .mp4 for
     * 30 days with no Cloudflare purge behind it — and because a new name *is*
     * the "keep the old file until confirmed" requirement rather than a second
     * mechanism for it. Deliberately not under `processed/`, whose
     * `original_watermarked.mp4` RenditionCoordinator::complete() would later
     * swap over video_path on an unrelated re-encode.
     */
    public function recompressedPathFor(string $videoPath, int $reclaimId): string
    {
        $directory = dirname($videoPath);
        $stem = pathinfo($videoPath, PATHINFO_FILENAME);

        return $directory.'/'.$stem.'_r'.$reclaimId.'.mp4';
    }

    /**
     * The video whose original this Media Library path is, or null.
     *
     * media_files.references only ever resolves the original — a rendition
     * MP4, an HLS segment, a sprite sheet and the scrubber VTT match no column
     * in MediaReferenceResolver — so a selected file has to be matched against
     * video_path directly. Anything else returns null, including every
     * rendition and segment: those are what the player streams.
     */
    public function videoForPath(string $path): ?Video
    {
        $path = ltrim(str_replace('\\', '/', $path), '/');

        if (! str_starts_with($path, 'videos/')) {
            return null;
        }

        $video = Video::withTrashed()->where('video_path', $path)->first();

        // A soft-deleted video can still be restored within the 30-day window,
        // so its files are not ours to touch.
        return $video && ! $video->trashed() ? $video : null;
    }

    /**
     * Collapse a selection of library paths into the videos they imply.
     *
     * @param  array<int, string>  $paths
     * @return array<int, Video>
     */
    public function videosForPaths(array $paths): array
    {
        $videos = [];

        foreach ($paths as $path) {
            $video = $this->videoForPath($path);

            if ($video) {
                $videos[$video->id] ??= $video;
            }
        }

        return array_values($videos);
    }

    // ── Requesting ──────────────────────────────────────────────────────────

    /**
     * Open a reclaim, or return null with a reason if it is not allowed.
     *
     * The lock closes the window between the eligibility check and the insert:
     * two admins pressing the same button, or a bulk action overlapping a
     * console run, would otherwise both pass and queue two encodes of the same
     * file — the second of which would hold the *first's* output as its "old"
     * file, and the real original would be lost.
     *
     * @return array{reclaim: ?StorageReclaim, reason: ?string}
     */
    public function request(Video $video, ?User $by = null): array
    {
        $lock = Cache::lock("storage-reclaim:{$video->id}", 10);

        if (! $lock->get()) {
            return ['reclaim' => null, 'reason' => 'Another reclaim request for this video is being processed.'];
        }

        try {
            if ($reason = $this->eligibility($video)) {
                return ['reclaim' => null, 'reason' => $reason];
            }

            $reclaim = StorageReclaim::create([
                'video_id' => $video->id,
                'requested_by' => $by?->id ?? Auth::id(),
                'target' => StorageReclaim::TARGET_ORIGINAL,
                'status' => StorageReclaim::PENDING,
                'run_id' => (string) Str::uuid(),
                'settings_snapshot' => $this->settings(),
            ]);

            AdminLogger::log(
                "Requested a re-compression of the original upload for video #{$video->id}",
                'admin',
                ['reclaim_id' => $reclaim->id],
                $video,
            );

            return ['reclaim' => $reclaim, 'reason' => null];
        } finally {
            $lock->release();
        }
    }

    /**
     * Queue the encode for a pending reclaim.
     *
     * The `storage-reclaim` queue's supervisor runs a single niced process —
     * that, rather than any per-request limit, is what stops a bulk reclaim
     * from starving live uploads.
     */
    public function start(StorageReclaim $reclaim): bool
    {
        if ($reclaim->status !== StorageReclaim::PENDING) {
            return false;
        }

        RecompressOriginalJob::dispatch($reclaim->id);

        return true;
    }

    /**
     * Request a reclaim and queue it in one step.
     *
     * @return array{reclaim: ?StorageReclaim, reason: ?string}
     */
    public function requestAndStart(Video $video, ?User $by = null): array
    {
        $result = $this->request($video, $by);

        if ($result['reclaim']) {
            $this->start($result['reclaim']);
        }

        return $result;
    }

    // ── Verifying a result ──────────────────────────────────────────────────

    /**
     * Verify a freshly encoded file and either offer it or discard it.
     *
     * Nothing reaches review until it is proven sound, because accepting is
     * what deletes the only other copy.
     */
    public function settle(StorageReclaim $reclaim, string $newPath, Video $video): bool
    {
        $disk = Storage::disk('public');

        if ($reason = $this->rejectionReason($reclaim, $newPath)) {
            // video_path was never repointed, so the live file is untouched and
            // discarding the candidate is the whole undo.
            if ($disk->exists($newPath)) {
                $disk->delete($newPath);
            }

            $this->finish($reclaim, StorageReclaim::SKIPPED, $reason);

            AdminLogger::log(
                "Discarded a re-compression for video #{$video->id}: {$reason}",
                'admin',
                ['reclaim_id' => $reclaim->id],
                $video,
            );

            return false;
        }

        $probe = app(MediaProbe::class)->inspect($disk->path($newPath));

        $reclaim->forceFill([
            'status' => StorageReclaim::AWAITING_REVIEW,
            'new_path' => $newPath,
            'after_bytes' => $disk->size($newPath),
            'after_duration_ms' => $probe['duration_ms'] ?? null,
            'finished_at' => now(),
        ])->saveQuietly();

        return true;
    }

    /**
     * Why a re-encoded file must not be offered, or null if it is sound.
     *
     * Each check exists for a concrete failure:
     *
     *  - **Duration within 250 ms.** The scrubber VTT's cue times and #xywh=
     *    offsets derive from videos.duration, and prepare() rewrites that
     *    column unconditionally on the next encode — so drift here silently
     *    desynchronises the scrubber later, long after anyone would connect it
     *    back to this.
     *  - **A real video stream, and at least 10 KB.** A killed ffmpeg leaves a
     *    header-only file that exists() is perfectly happy with.
     *  - **A saving worth the trade.** Below the configured threshold the
     *    quality cost buys nothing.
     */
    protected function rejectionReason(StorageReclaim $reclaim, string $newPath): ?string
    {
        $disk = Storage::disk('public');
        $settings = $reclaim->settings_snapshot ?? $this->settings();

        if (! $disk->exists($newPath)) {
            return 'The re-encoded file is missing.';
        }

        $after = $disk->size($newPath);

        if ($after < 10240) {
            return 'The re-encoded file is too small to be real.';
        }

        $probe = app(MediaProbe::class)->inspect($disk->path($newPath));

        if (! $probe) {
            return 'The re-encoded file could not be probed.';
        }

        if (! $probe['has_video']) {
            return 'The re-encoded file has no video stream.';
        }

        $before = (int) $reclaim->before_bytes;

        if ($before > 0) {
            $saved = ($before - $after) / $before * 100;
            $required = (int) ($settings['min_saving_percent'] ?? 15);

            if ($saved < $required) {
                return sprintf('It only saved %.1f%%, under the %d%% threshold.', max(0, $saved), $required);
            }
        }

        $expected = (int) $reclaim->before_duration_ms;

        if ($expected > 0 && abs($probe['duration_ms'] - $expected) > 250) {
            return sprintf(
                'Its length drifted by %.2fs, which would desynchronise the scrubber.',
                abs($probe['duration_ms'] - $expected) / 1000,
            );
        }

        return null;
    }

    // ── Review ──────────────────────────────────────────────────────────────

    /**
     * Point the video at the smaller file and delete the original upload.
     *
     * The only irreversible step in the feature, and it only ever runs because
     * a person pressed the button: there is no sweep, no expiry and no
     * scheduled acceptance. A reclaim waits indefinitely rather than free
     * space nobody agreed to free.
     */
    public function accept(StorageReclaim $reclaim, ?User $by = null): bool
    {
        if ($reclaim->status !== StorageReclaim::AWAITING_REVIEW) {
            return false;
        }

        // Resolved fresh rather than through the relation: callers hand us
        // whatever instance they have, and a partially selected one (the review
        // page lists `video:id,title,slug`) is missing every column the work
        // below reads.
        $video = $this->videoFor($reclaim);

        if (! $video) {
            $this->finish($reclaim, StorageReclaim::FAILED, 'The video no longer exists.');

            return false;
        }

        $disk = Storage::disk('public');

        try {
            if (! $reclaim->new_path || ! $disk->exists($reclaim->new_path)) {
                throw new RuntimeException('The re-compressed file is missing.');
            }

            $old = $reclaim->kept_path;

            // Quiet, because Video::booted() flushes ~130 cache keys on update.
            $video->forceFill(['video_path' => $reclaim->new_path])->saveQuietly();

            if ($old && $old !== $reclaim->new_path && $disk->exists($old)) {
                $disk->delete($old);
            }
        } catch (Throwable $e) {
            Log::error('Storage reclaim accept failed', ['reclaim' => $reclaim->id, 'error' => $e->getMessage()]);
            $this->finish($reclaim, StorageReclaim::FAILED, $e->getMessage());

            return false;
        }

        $this->refreshSize($video);
        $this->resync($reclaim, $video);

        $reclaim->forceFill([
            'reviewed_by' => $by?->id ?? Auth::id(),
            'kept_path' => null,
        ])->saveQuietly();

        $this->finish($reclaim, StorageReclaim::ACCEPTED);

        AdminLogger::log(
            sprintf('Accepted a re-compression for video #%d — saved %s', $video->id, $reclaim->savingLabel()),
            'admin',
            [
                'reclaim_id' => $reclaim->id,
                'before_bytes' => $reclaim->before_bytes,
                'after_bytes' => $reclaim->after_bytes,
            ],
            $video,
        );

        return true;
    }

    /**
     * Throw the candidate away and keep the original upload.
     *
     * Safe by construction: video_path was never repointed, so there is
     * nothing to restore and the worst this can do is leave a file on disk.
     */
    public function revert(StorageReclaim $reclaim, ?User $by = null): bool
    {
        if ($reclaim->status !== StorageReclaim::AWAITING_REVIEW) {
            return false;
        }

        $video = $this->videoFor($reclaim);
        $disk = Storage::disk('public');

        try {
            if ($reclaim->new_path && $disk->exists($reclaim->new_path)) {
                $disk->delete($reclaim->new_path);
            }
        } catch (Throwable $e) {
            Log::error('Storage reclaim revert failed', ['reclaim' => $reclaim->id, 'error' => $e->getMessage()]);
            $this->finish($reclaim, StorageReclaim::REVERT_FAILED, $e->getMessage());

            return false;
        }

        $reclaim->forceFill(['reviewed_by' => $by?->id ?? Auth::id()])->saveQuietly();

        if ($video) {
            $this->resync($reclaim, $video);
        }

        $this->finish($reclaim, StorageReclaim::REVERTED);

        AdminLogger::log(
            'Discarded a re-compression for video #'.$reclaim->video_id,
            'admin',
            ['reclaim_id' => $reclaim->id],
            $video,
        );

        return true;
    }

    // ── Bookkeeping ─────────────────────────────────────────────────────────

    /** The full video row a reclaim acts on, or null if it has gone. */
    protected function videoFor(StorageReclaim $reclaim): ?Video
    {
        return Video::find($reclaim->video_id);
    }

    /**
     * Point videos.size at whatever is now on disk.
     *
     * It is shown in the admin and feeds storage totals, so leaving it at the
     * pre-reclaim figure would make the saving invisible everywhere except
     * this ledger.
     */
    protected function refreshSize(Video $video): void
    {
        $disk = Storage::disk('public');

        if ($video->video_path && $disk->exists($video->video_path)) {
            $video->forceFill(['size' => $disk->size($video->video_path)])->saveQuietly();
        }
    }

    /**
     * Bring the Media Library back in line with the disk.
     *
     * syncForPaths is explicit because every write above is saveQuietly(),
     * which skips VideoObserver::updated — the hook that keeps
     * media_files.is_referenced honest. Without this the file the site is now
     * serving reads as "Unused" in the library, and is one click from deletion.
     */
    protected function resync(StorageReclaim $reclaim, Video $video): void
    {
        $this->references->syncForPaths(array_filter([
            $reclaim->source_path,
            $reclaim->new_path,
            $reclaim->kept_path,
            $video->video_path,
        ]));

        IndexMediaDirectoryJob::dispatch($this->videoDir($video));
        MediaStorageReport::forget();
    }

    protected function finish(StorageReclaim $reclaim, string $status, ?string $error = null): void
    {
        $reclaim->forceFill([
            'status' => $status,
            'error' => $error,
            'finished_at' => now(),
        ])->saveQuietly();
    }
}

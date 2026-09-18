<?php

namespace App\Services\Storage;

use App\Jobs\IndexMediaDirectoryJob;
use App\Models\Setting;
use App\Models\StorageReclaim;
use App\Models\User;
use App\Models\Video;
use App\Models\VideoEncoding;
use App\Services\AdminLogger;
use App\Services\Media\MediaReferenceResolver;
use App\Services\Media\MediaStorageReport;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Requesting, reviewing and unwinding storage reclaims.
 *
 * Reclaiming space means deleting or replacing files a running site is
 * serving, on a box with no media backups (config/backup.php excludes
 * storage/app/public). Everything here is built around one invariant:
 *
 *   **The file at the canonical path is always the current good one.**
 *
 * Every operation either leaves that path alone — as original re-compression
 * does, by encoding to a new filename and only repointing the column on
 * accept — or replaces it atomically with an already-verified file. There is
 * no window in which it holds a partial encode, which is also why a failed
 * revert can never lose the live file.
 *
 * The Filament page, the bulk actions, the console commands and the tests all
 * come through here, so those four can never disagree about what is allowed.
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
     * a higher CRF, not a different rate control. Those are the only two knobs
     * that matter, and both are deliberately admin-visible: a reclaim is
     * irreversible once accepted and the quality trade is a judgement call.
     */
    public function settings(): array
    {
        return [
            'preset' => (string) Setting::get('reclaim_preset', 'slow'),
            'crf' => (int) Setting::get('ffmpeg_crf', 22) + (int) Setting::get('reclaim_crf_delta', 2),
            'min_saving_percent' => (int) Setting::get('reclaim_min_saving_percent', 15),
            'keep_days' => (int) Setting::get('reclaim_keep_days', 14),
        ];
    }

    public function keepUntil(): Carbon
    {
        return now()->addDays(max(1, $this->settings()['keep_days']));
    }

    // ── Eligibility ─────────────────────────────────────────────────────────

    /**
     * Why this video's target cannot be reclaimed, or null if it can.
     *
     * Returns a human reason rather than a bool so the same call can drive a
     * disabled button's tooltip, a console command's output and a test's
     * assertion — the pattern MediaGuard already uses. Every caller must go
     * through this; nothing else is allowed to decide.
     */
    public function eligibility(Video $video, string $target, ?string $quality = null): ?string
    {
        if (! in_array($target, StorageReclaim::TARGETS, true)) {
            return 'Unknown reclaim target.';
        }

        // sourcePath() is hardcoded to the local public disk, and the encoder
        // only offloads to cloud storage after it finishes, so an offloaded
        // video has no local file to work from.
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
        // and .watermark_done records that it happened. Re-encoding the
        // current original without that marker risks drawing a second
        // watermark over the first — that video belongs in the normal
        // pipeline, not here.
        if ($target === StorageReclaim::TARGET_ORIGINAL && $this->watermarkPending($video)) {
            return 'A watermark is configured but has not been applied yet.';
        }

        if ($this->hasActive($video, $target, $quality)) {
            return 'A reclaim for this file is already in progress or awaiting review.';
        }

        return match ($target) {
            StorageReclaim::TARGET_RENDITION => $this->renditionReason($video, $quality),
            StorageReclaim::TARGET_HLS => $this->hlsReason($video),
            default => null,
        };
    }

    public function isEligible(Video $video, string $target, ?string $quality = null): bool
    {
        return $this->eligibility($video, $target, $quality) === null;
    }

    protected function watermarkPending(Video $video): bool
    {
        if (! Setting::get('watermark_enabled', false)) {
            return false;
        }

        return ! Storage::disk('public')->exists($this->videoDir($video).'/.watermark_done');
    }

    protected function renditionReason(Video $video, ?string $quality): ?string
    {
        if (! $quality || $quality === VideoEncoding::ORIGINAL) {
            return 'Pick a rendition to re-encode.';
        }

        $encoding = $video->encodings()
            ->where('quality', $quality)
            ->where('status', VideoEncoding::COMPLETED)
            ->first();

        if (! $encoding) {
            return "There is no completed {$quality} encode to replace.";
        }

        if (! Storage::disk('public')->exists($this->renditionPath($video, $quality))) {
            return "The {$quality} file is missing from disk.";
        }

        return null;
    }

    /**
     * Whether the HLS tree can be given up.
     *
     * This is the one path in the feature that could make a video unplayable,
     * so it is the one with a real pre-flight: HLS is the *only* copy of any
     * quality whose progressive MP4 has been lost, and dropping the tree then
     * takes that quality with it. Every advertised quality must have a real
     * MP4 on disk first.
     */
    protected function hlsReason(Video $video): ?string
    {
        if (! Storage::disk('public')->exists($this->hlsDir($video))) {
            return 'This video has no HLS copy.';
        }

        foreach ((array) $video->qualities_available as $quality) {
            if ($quality === 'original') {
                continue;
            }

            $path = $this->renditionPath($video, $quality);

            // 10 KB: an ffmpeg run killed part-way leaves a header-only file
            // that exists() is perfectly happy with.
            if (! Storage::disk('public')->exists($path) || Storage::disk('public')->size($path) < 10240) {
                return "The {$quality} MP4 is missing, so HLS is currently its only copy.";
            }
        }

        return null;
    }

    public function hasActive(Video $video, string $target, ?string $quality = null): bool
    {
        return StorageReclaim::query()
            ->where('video_id', $video->id)
            ->where('target', $target)
            ->when($target === StorageReclaim::TARGET_RENDITION, fn ($q) => $q->where('quality', $quality))
            ->active()
            ->exists();
    }

    // ── Paths ───────────────────────────────────────────────────────────────

    public function videoDir(Video $video): string
    {
        return dirname((string) $video->video_path);
    }

    public function processedDir(Video $video): string
    {
        return $this->videoDir($video).'/processed';
    }

    public function renditionPath(Video $video, string $quality): string
    {
        return $this->processedDir($video).'/'.$quality.'.mp4';
    }

    public function hlsDir(Video $video): string
    {
        return $this->processedDir($video).'/hls';
    }

    /**
     * Which reclaim a path in the Media Library corresponds to.
     *
     * media_files.references only ever resolves the original — a rendition
     * MP4, an HLS segment, a sprite sheet and the scrubber VTT match no column
     * in MediaReferenceResolver — so a file selected in the library has to be
     * mapped back to its video by convention, from the `videos/{slug}/…`
     * layout the encoder writes.
     *
     * @return array{video: Video, target: string, quality: ?string}|null
     */
    public function targetForPath(string $path): ?array
    {
        $path = ltrim(str_replace('\\', '/', $path), '/');

        if (! str_starts_with($path, 'videos/')) {
            return null;
        }

        $segments = explode('/', $path);

        if (count($segments) < 3) {
            return null;
        }

        $dir = 'videos/'.$segments[1];

        $video = Video::withTrashed()
            ->where('video_path', 'like', $dir.'/%')
            ->first();

        if (! $video || $video->trashed()) {
            // A soft-deleted video can still be restored within the 30-day
            // window, so its files are not ours to touch.
            return null;
        }

        if ($path === $video->video_path) {
            return ['video' => $video, 'target' => StorageReclaim::TARGET_ORIGINAL, 'quality' => null];
        }

        $relative = Str::after($path, $dir.'/');

        if (str_starts_with($relative, 'processed/hls/')) {
            return ['video' => $video, 'target' => StorageReclaim::TARGET_HLS, 'quality' => null];
        }

        if (preg_match('#^processed/([0-9]{3,4}p)\.mp4$#', $relative, $matches)) {
            return [
                'video' => $video,
                'target' => StorageReclaim::TARGET_RENDITION,
                'quality' => $matches[1],
            ];
        }

        return null;
    }

    /**
     * Collapse a selection of files into the reclaims they imply.
     *
     * Selecting 200 HLS segments means one reclaim of one tree, not 200.
     *
     * @param  array<int, string>  $paths
     * @return array<int, array{video: Video, target: string, quality: ?string}>
     */
    public function targetsForPaths(array $paths): array
    {
        $targets = [];

        foreach ($paths as $path) {
            $resolved = $this->targetForPath($path);

            if (! $resolved) {
                continue;
            }

            $key = $resolved['video']->id.':'.$resolved['target'].':'.($resolved['quality'] ?? '');
            $targets[$key] ??= $resolved;
        }

        return array_values($targets);
    }

    // ── Requesting ──────────────────────────────────────────────────────────

    /**
     * Open a reclaim, or return null with a reason if it is not allowed.
     *
     * The lock closes the window between the eligibility check and the insert:
     * two admins pressing the same button, or a bulk action overlapping a
     * console run, would otherwise both pass and queue two encodes of the same
     * file — the second of which would hold the *first's* output as its "old"
     * file and the real original would be lost.
     *
     * @return array{reclaim: ?StorageReclaim, reason: ?string}
     */
    public function request(Video $video, string $target, ?string $quality = null, ?User $by = null): array
    {
        $lock = Cache::lock("storage-reclaim:{$video->id}:{$target}:".($quality ?? ''), 10);

        if (! $lock->get()) {
            return ['reclaim' => null, 'reason' => 'Another reclaim request for this file is being processed.'];
        }

        try {
            if ($reason = $this->eligibility($video, $target, $quality)) {
                return ['reclaim' => null, 'reason' => $reason];
            }

            $reclaim = StorageReclaim::create([
                'video_id' => $video->id,
                'requested_by' => $by?->id ?? Auth::id(),
                'target' => $target,
                'quality' => $target === StorageReclaim::TARGET_RENDITION ? $quality : null,
                'status' => StorageReclaim::PENDING,
                'run_id' => (string) Str::uuid(),
                'settings_snapshot' => $this->settings(),
                'keep_until' => $this->keepUntil(),
            ]);

            AdminLogger::log(
                "Requested storage reclaim ({$reclaim->targetLabel()}) for video #{$video->id}",
                'admin',
                ['reclaim_id' => $reclaim->id, 'target' => $target, 'quality' => $quality],
                $video,
            );

            return ['reclaim' => $reclaim, 'reason' => null];
        } finally {
            $lock->release();
        }
    }

    // ── Review ──────────────────────────────────────────────────────────────

    /**
     * Keep the new file and release the old bytes.
     *
     * Idempotent in the one way that matters: if the kept file has already
     * gone — a second sweep pass, someone tidying up by hand — that is treated
     * as already-freed and the row still completes. A sweep that could fail on
     * its own previous success would stall every row behind it.
     */
    public function accept(StorageReclaim $reclaim, ?User $by = null, string $status = StorageReclaim::ACCEPTED): bool
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

        try {
            match ($reclaim->target) {
                StorageReclaim::TARGET_ORIGINAL => $this->acceptOriginal($reclaim, $video),
                default => $this->discardKept($reclaim),
            };
        } catch (Throwable $e) {
            Log::error('Storage reclaim accept failed', ['reclaim' => $reclaim->id, 'error' => $e->getMessage()]);
            $this->finish($reclaim, StorageReclaim::FAILED, $e->getMessage());

            return false;
        }

        // A rendition or HLS tree was replaced under its own name, so anything
        // holding the old bytes — nginx's 30 days, Cloudflare, a browser —
        // needs to be told. A re-compressed original changed filename instead.
        if ($reclaim->target !== StorageReclaim::TARGET_ORIGINAL) {
            $this->bumpMediaVersion($video);
        }

        $this->refreshSizes($reclaim, $video);
        $this->resync($reclaim, $video);

        $reclaim->forceFill([
            'reviewed_by' => $by?->id ?? Auth::id(),
            'kept_path' => null,
        ])->saveQuietly();

        $this->finish($reclaim, $status);

        AdminLogger::log(
            sprintf(
                'Accepted storage reclaim (%s) for video #%d — saved %s',
                $reclaim->targetLabel(),
                $video->id,
                $reclaim->savingLabel(),
            ),
            'admin',
            [
                'reclaim_id' => $reclaim->id,
                'before_bytes' => $reclaim->before_bytes,
                'after_bytes' => $reclaim->after_bytes,
                'auto' => $status === StorageReclaim::EXPIRED,
            ],
            $video,
        );

        return true;
    }

    /**
     * Put the old file back and throw away the new one.
     *
     * If the kept file is gone there is nothing to restore, and the live file
     * stays exactly as it is — the row goes to revert_failed and says so. That
     * is the whole point of the invariant: the worst outcome of a failed revert
     * is a smaller file than the admin wanted, never a missing one.
     */
    public function revert(StorageReclaim $reclaim, ?User $by = null): bool
    {
        if ($reclaim->status !== StorageReclaim::AWAITING_REVIEW) {
            return false;
        }

        // Resolved fresh rather than through the relation: callers hand us
        // whatever instance they have, and a partially selected one (the review
        // page lists `video:id,title,slug`) is missing every column the work
        // below reads.
        $video = $this->videoFor($reclaim);
        $disk = Storage::disk('public');

        if (! $video || ! $reclaim->kept_path || ! $this->exists($reclaim->kept_path)) {
            $this->finish($reclaim, StorageReclaim::REVERT_FAILED, 'The kept file is no longer on disk.');

            AdminLogger::log(
                "Could not revert storage reclaim #{$reclaim->id}: the kept file is gone",
                'error',
                ['reclaim_id' => $reclaim->id],
                $video,
            );

            return false;
        }

        try {
            if ($reclaim->target === StorageReclaim::TARGET_ORIGINAL) {
                // Nothing was ever repointed, so reverting is just deleting
                // the candidate: video_path still names the kept file.
                if ($reclaim->new_path && $disk->exists($reclaim->new_path)) {
                    $disk->delete($reclaim->new_path);
                }
            } else {
                $this->restoreKept($reclaim);
                $this->bumpMediaVersion($video);
            }
        } catch (Throwable $e) {
            Log::error('Storage reclaim revert failed', ['reclaim' => $reclaim->id, 'error' => $e->getMessage()]);
            $this->finish($reclaim, StorageReclaim::REVERT_FAILED, $e->getMessage());

            return false;
        }

        $reclaim->forceFill(['reviewed_by' => $by?->id ?? Auth::id()])->saveQuietly();
        $this->refreshSizes($reclaim, $video, reverted: true);
        $this->resync($reclaim, $video);
        $this->finish($reclaim, StorageReclaim::REVERTED);

        AdminLogger::log(
            "Reverted storage reclaim ({$reclaim->targetLabel()}) for video #{$video->id}",
            'admin',
            ['reclaim_id' => $reclaim->id],
            $video,
        );

        return true;
    }

    /**
     * Auto-accept everything whose review window has passed.
     *
     * Treating a passed keep_until as acceptance is the only reading under
     * which this feature ever reclaims a byte unattended; rows left pending
     * forever would mean nothing is saved without a human. Every auto-accept
     * is logged with before and after, the review page shows the countdown,
     * and reclaim_keep_days can be set high enough to disable it in practice.
     */
    public function sweep(?int $limit = null): int
    {
        $expired = StorageReclaim::query()
            ->awaitingReview()
            ->whereNotNull('keep_until')
            ->where('keep_until', '<=', now())
            ->orderBy('keep_until')
            ->when($limit, fn ($q) => $q->limit($limit))
            ->get();

        $count = 0;

        foreach ($expired as $reclaim) {
            if ($this->accept($reclaim, null, StorageReclaim::EXPIRED)) {
                $count++;
            }
        }

        return $count;
    }

    // ── Bookkeeping ─────────────────────────────────────────────────────────

    /** Repoint the video at the smaller file and drop the old one. */
    protected function acceptOriginal(StorageReclaim $reclaim, Video $video): void
    {
        $disk = Storage::disk('public');

        if (! $reclaim->new_path || ! $disk->exists($reclaim->new_path)) {
            throw new \RuntimeException('The re-compressed file is missing.');
        }

        $old = $reclaim->kept_path;

        // Quiet, because Video::booted() flushes ~130 cache keys on update.
        $video->forceFill(['video_path' => $reclaim->new_path])->saveQuietly();

        if ($old && $old !== $reclaim->new_path && $disk->exists($old)) {
            $disk->delete($old);
        }
    }

    /** Release the bytes a kept file or directory is holding. */
    protected function discardKept(StorageReclaim $reclaim): void
    {
        if (! $reclaim->kept_path) {
            return;
        }

        $disk = Storage::disk('public');

        if ($disk->directoryExists($reclaim->kept_path)) {
            $disk->deleteDirectory($reclaim->kept_path);
        } elseif ($disk->exists($reclaim->kept_path)) {
            $disk->delete($reclaim->kept_path);
        }
    }

    /** Move the kept file or directory back over the canonical path. */
    protected function restoreKept(StorageReclaim $reclaim): void
    {
        $disk = Storage::disk('public');
        $target = $reclaim->source_path;

        if (! $target) {
            throw new \RuntimeException('This reclaim did not record which path to restore.');
        }

        if ($disk->directoryExists($reclaim->kept_path)) {
            if ($disk->directoryExists($target)) {
                $disk->deleteDirectory($target);
            }
        } elseif ($disk->exists($target)) {
            $disk->delete($target);
        }

        $disk->move($reclaim->kept_path, $target);
    }

    protected function exists(string $path): bool
    {
        $disk = Storage::disk('public');

        return $disk->exists($path) || $disk->directoryExists($path);
    }

    /**
     * Point the site's size columns at whatever is now on disk.
     *
     * videos.size and video_encodings.size are shown in the admin and used for
     * storage totals, so leaving them at the pre-reclaim figures would make
     * the saving invisible everywhere except this ledger.
     */
    protected function refreshSizes(StorageReclaim $reclaim, Video $video, bool $reverted = false): void
    {
        $disk = Storage::disk('public');

        if ($video->video_path && $disk->exists($video->video_path)) {
            $video->forceFill(['size' => $disk->size($video->video_path)])->saveQuietly();
        }

        if ($reclaim->target === StorageReclaim::TARGET_RENDITION && $reclaim->quality) {
            $path = $this->renditionPath($video, $reclaim->quality);

            if ($disk->exists($path)) {
                $video->encodings()
                    ->where('quality', $reclaim->quality)
                    ->update(['size' => $disk->size($path)]);
            }
        }
    }

    /**
     * Move the cache-busting version on by one.
     *
     * Incremented in SQL rather than read-modify-written: callers hand us
     * whatever Video instance they have, and a partially selected one (the
     * review page loads `video:id,title,slug`) has no media_version to read.
     * Under Model::shouldBeStrict() that throws; in production it would read
     * as null and reset the counter to 1 on every reclaim, at which point a
     * second replacement of the same file serves the stale bytes it was meant
     * to bust.
     */
    protected function bumpMediaVersion(Video $video): void
    {
        Video::withTrashed()
            ->whereKey($video->id)
            ->update(['media_version' => DB::raw('media_version + 1')]);

        if (array_key_exists('media_version', $video->getAttributes())) {
            $video->setAttribute('media_version', (int) $video->getAttributes()['media_version'] + 1);
            $video->syncOriginalAttribute('media_version');
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

    /** The full video row a reclaim acts on, or null if it has gone. */
    protected function videoFor(StorageReclaim $reclaim): ?Video
    {
        return Video::find($reclaim->video_id);
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

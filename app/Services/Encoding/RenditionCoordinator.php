<?php

namespace App\Services\Encoding;

use App\Jobs\CompleteVideoProcessingJob;
use App\Jobs\EncodeVideoChunkJob;
use App\Jobs\FinalizeRenditionJob;
use App\Models\EncodeProfile;
use App\Models\Setting;
use App\Models\Video;
use App\Models\VideoEncoding;
use App\Services\AdminLogger;
use App\Services\StorageManager;
use App\Services\VideoService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Drives a video's renditions from plan to publish.
 *
 * The flow, all tracked in video_encodings rows:
 *
 *  1. ProcessVideoJob prepares the video, then calls plan() and start().
 *  2. Each rendition is cut into chunks (one chunk for short videos), each
 *     encoded by its own EncodeVideoChunkJob, so several workers can work on
 *     one video at the same time.
 *  3. When a rendition's chunks are all done, FinalizeRenditionJob joins them,
 *     packages HLS and calls renditionFinished().
 *  4. The lowest rendition goes first, on its own priority queue. When it
 *     finishes the video is published at that quality, and the remaining
 *     renditions are dispatched together.
 *  5. Once every rendition has finished or failed, CompleteVideoProcessingJob
 *     swaps in the watermarked original, offloads to cloud storage and
 *     records any failures.
 *
 * Every row carries a run_id that changes whenever the video is re-planned,
 * so jobs left over from an abandoned run stand down instead of clobbering
 * the new one.
 */
class RenditionCoordinator
{
    public const PRIORITY_QUEUE = 'video-priority';

    public const QUEUE = 'video-processing';

    /** Seconds between progress writes to the database per chunk job. */
    protected const PROGRESS_WRITE_INTERVAL = 3.0;

    /** @var array<string, float> */
    protected array $lastProgressWrite = [];

    public function __construct(
        protected VideoService $videoService,
        protected HlsPackager $hls,
        protected VideoCloudOffloader $offloader,
    ) {}

    // ── Paths ───────────────────────────────────────────────────────────────

    public function sourcePath(Video $video): string
    {
        return Storage::disk('public')->path($video->video_path);
    }

    public function processedDir(Video $video): string
    {
        return Storage::disk('public')->path("videos/{$video->slug}/processed");
    }

    /** Per run, so files left by an abandoned run can never pass for this one's. */
    public function chunkDir(VideoEncoding $encoding): string
    {
        return $this->processedDir($encoding->video)."/chunks/{$encoding->quality}/{$encoding->run_id}";
    }

    public function audioTrackPath(Video $video): string
    {
        return $this->processedDir($video).'/chunks/audio.m4a';
    }

    /** Where a finished rendition's MP4 lives. */
    public function outputPath(VideoEncoding $encoding): string
    {
        return $encoding->isOriginal()
            ? $this->processedDir($encoding->video).'/original_watermarked.mp4'
            : $this->processedDir($encoding->video)."/{$encoding->quality}.mp4";
    }

    public function watermarkDoneMarker(Video $video): string
    {
        return Storage::disk('public')->path("videos/{$video->slug}/.watermark_done");
    }

    // ── Planning ────────────────────────────────────────────────────────────

    /**
     * Create or refresh the rendition rows for a video.
     *
     * Renditions that already finished and whose file is still on disk are
     * kept, which is what lets "encode missing renditions" add a newly enabled
     * profile without redoing the rest.
     *
     * @param  array{duration: float, width: int, height: int, has_audio: bool}  $source
     * @return Collection<int, VideoEncoding> Rows that still need encoding.
     */
    public function plan(Video $video, array $source, FfmpegCommands $commands): Collection
    {
        $targets = collect();

        if ($commands->s('multi_resolution_enabled', true)) {
            // Strictly below the source: a rendition must never upscale, and
            // the source height is already served as 'original'.
            $targets = EncodeProfile::active()
                ->where('height', '<', $source['height'])
                ->orderBy('height')
                ->get()
                ->map(fn (EncodeProfile $profile) => [
                    'quality' => $profile->name,
                    'height' => $profile->height,
                    'encode_profile_id' => $profile->id,
                    'apply_watermark' => false,
                ]);
        }

        $existing = $video->encodings()->get()->keyBy('quality');

        // Videos processed before rendition tracking existed have files but no
        // rows. Adopt what is on disk instead of re-encoding it, and leave the
        // watermark alone: the old pipeline already burned it into the upload,
        // so drawing it again would double it.
        $isLegacy = $existing->isEmpty()
            && $video->status === 'processed'
            && ! empty($video->qualities_available);

        if ($isLegacy) {
            $existing = $this->adoptLegacyRenditions($video);
        }

        $sourceWatermarked = $isLegacy || file_exists($this->watermarkDoneMarker($video));
        $applyWatermark = $commands->hasWatermark() && ! $sourceWatermarked;

        // Renditions carry the watermark themselves until the source has been
        // replaced by a watermarked copy; after that they would get it twice.
        $targets = $targets->map(fn (array $target) => ['apply_watermark' => $applyWatermark] + $target);

        if ($applyWatermark) {
            $targets->push([
                'quality' => VideoEncoding::ORIGINAL,
                'height' => $source['height'],
                'encode_profile_id' => null,
                'apply_watermark' => true,
            ]);
        }

        [$chunks, $chunkSeconds] = $this->chunkPlan($source, $commands);

        $keep = $targets->pluck('quality')->all();

        // Unfinished rows for renditions no longer wanted (profile switched off,
        // watermark disabled) would otherwise sit in the progress bars forever.
        $existing->each(function (VideoEncoding $row) use ($keep) {
            if (! in_array($row->quality, $keep, true) && $row->status !== VideoEncoding::COMPLETED) {
                $row->delete();
            }
        });

        $lowest = $targets->reject(fn ($t) => $t['quality'] === VideoEncoding::ORIGINAL)->first();
        $needsWork = collect();

        foreach ($targets as $target) {
            /** @var VideoEncoding|null $row */
            $row = $existing->get($target['quality']);

            if ($row && $row->status === VideoEncoding::COMPLETED && $this->renditionOnDisk($video, $target['quality'])) {
                continue;
            }

            $row ??= new VideoEncoding(['video_id' => $video->id, 'quality' => $target['quality']]);

            $row->fill([
                'encode_profile_id' => $target['encode_profile_id'],
                'height' => $target['height'],
                'source_width' => $source['width'],
                'source_height' => $source['height'],
                'apply_watermark' => $target['apply_watermark'],
                'status' => VideoEncoding::PENDING,
                'progress' => 0,
                'is_priority' => $lowest !== null && $target['quality'] === $lowest['quality'],
                'chunks_total' => $chunks,
                'chunks_completed' => 0,
                'chunk_seconds' => $chunks > 1 ? $chunkSeconds : null,
                'run_id' => (string) Str::uuid(),
                'error' => null,
                'size' => null,
                'started_at' => null,
                'completed_at' => null,
            ])->save();

            $row->setRelation('video', $video);
            $needsWork->push($row);
        }

        return $needsWork;
    }

    /**
     * How many chunks each rendition is cut into, and how long they are.
     *
     * @return array{0: int, 1: int}
     */
    public function chunkPlan(array $source, FfmpegCommands $commands): array
    {
        $duration = (float) $source['duration'];

        // A shared audio track is what keeps chunked video in sync (see
        // EncodeVideoChunkJob), so without audio support nothing is chunked.
        if (! $commands->s('chunked_encoding_enabled', true)
            || $duration <= (int) $commands->s('chunked_encoding_min_duration', 300)) {
            return [1, 0];
        }

        $step = FfmpegCommands::KEYFRAME_SECONDS;
        $seconds = max(30, (int) $commands->s('chunked_encoding_chunk_seconds', 240));
        // Chunk boundaries must fall on the keyframe grid, or HLS segments
        // would not line up across renditions.
        $seconds = (int) (ceil($seconds / $step) * $step);

        return [max(1, (int) ceil($duration / $seconds)), $seconds];
    }

    protected function outputPathFor(Video $video, string $quality): string
    {
        return $quality === VideoEncoding::ORIGINAL
            ? $this->processedDir($video).'/original_watermarked.mp4'
            : $this->processedDir($video)."/{$quality}.mp4";
    }

    /**
     * Whether a finished rendition still exists in some playable form.
     *
     * Either its MP4 or its packaged HLS stream will do. FinalizeRenditionJob
     * deletes the MP4 once the stream is verified, so "the MP4 is missing" no
     * longer means "this quality needs encoding again" — without this, every
     * re-run would re-encode the whole ladder.
     */
    protected function renditionOnDisk(Video $video, string $quality): bool
    {
        return file_exists($this->outputPathFor($video, $quality))
            || ($quality !== VideoEncoding::ORIGINAL && HlsPackager::isPackaged($this->processedDir($video), $quality));
    }

    /** Bytes a packaged rendition occupies, for an adopted row's size. */
    protected function packagedSizeOf(Video $video, string $quality): int
    {
        return app(HlsPackager::class)->packagedSize($this->processedDir($video), $quality);
    }

    /**
     * Create completed rows for renditions a legacy video already has on disk.
     *
     * @return Collection<string, VideoEncoding>
     */
    protected function adoptLegacyRenditions(Video $video): Collection
    {
        $profiles = EncodeProfile::all()->keyBy('name');

        foreach ((array) $video->qualities_available as $quality) {
            if ($quality === 'original' || ! $this->renditionOnDisk($video, $quality)) {
                continue;
            }

            $profile = $profiles->get($quality);
            $mp4 = $this->outputPathFor($video, $quality);

            VideoEncoding::create([
                'video_id' => $video->id,
                'encode_profile_id' => $profile?->id,
                'quality' => $quality,
                'height' => $profile?->height ?? (int) $quality,
                'status' => VideoEncoding::COMPLETED,
                'progress' => 100,
                'run_id' => (string) Str::uuid(),
                'size' => (file_exists($mp4) ? filesize($mp4) : $this->packagedSizeOf($video, $quality)) ?: null,
                'completed_at' => $video->processing_completed_at ?? now(),
            ]);
        }

        return $video->encodings()->get()->keyBy('quality');
    }

    // ── Dispatch ────────────────────────────────────────────────────────────

    /**
     * Start encoding: the priority rendition alone, or everything at once
     * when there is no priority rendition (e.g. only the watermarked original).
     */
    public function start(Video $video): void
    {
        $pending = $video->encodings()->where('status', VideoEncoding::PENDING)->get();

        if ($pending->isEmpty()) {
            $this->dispatchCompletionIfDone($video);

            return;
        }

        // Renditions kept from an earlier run may already be enough to go live.
        if ($video->status !== 'processed') {
            Cache::lock("video-renditions:{$video->id}", 60)->block(30, function () use ($video) {
                $this->publishAvailableRenditions($video, $video->encodings()->get());
            });
            $video->refresh();
        }

        $priority = $pending->firstWhere('is_priority', true);

        // With no priority rendition pending, or a video that is already live
        // (re-encoding missing renditions), there is nothing to wait for.
        if (! $priority || $video->status === 'processed') {
            foreach ($pending as $encoding) {
                $this->dispatchRendition($encoding);
            }

            return;
        }

        // The rest follow when this one finishes (see renditionFinished).
        $this->dispatchRendition($priority);
    }

    public function dispatchRendition(VideoEncoding $encoding): void
    {
        $updated = VideoEncoding::whereKey($encoding->id)
            ->where('run_id', $encoding->run_id)
            ->where('status', VideoEncoding::PENDING)
            ->update(['status' => VideoEncoding::QUEUED]);

        if ($updated === 0) {
            return;
        }

        $queue = $encoding->is_priority ? self::PRIORITY_QUEUE : self::QUEUE;

        for ($index = 0; $index < $encoding->chunks_total; $index++) {
            $this->dispatchSafely(fn () => EncodeVideoChunkJob::dispatch($encoding->id, $encoding->run_id, $index)->onQueue($queue));
        }
    }

    /**
     * Dispatch without letting a job's own failure escape into the caller.
     *
     * On a real queue dispatch never throws for that. On the sync queue (local
     * development, tests) the job runs inline, and Laravel re-throws its
     * exception after calling failed(), which has already recorded the
     * failure; letting it propagate would abort the rendition that dispatched it.
     */
    protected function dispatchSafely(callable $dispatch): void
    {
        try {
            $dispatch();
        } catch (\Throwable $e) {
            if (config('queue.default') !== 'sync') {
                throw $e;
            }

            Log::warning('Encoding job failed inline on the sync queue', ['error' => $e->getMessage()]);
        }
    }

    // ── Chunk progress ──────────────────────────────────────────────────────

    public function chunkDoneMarker(VideoEncoding $encoding, int $index): string
    {
        return $this->chunkDir($encoding).sprintf('/chunk_%04d.done', $index);
    }

    public function chunkPath(VideoEncoding $encoding, int $index): string
    {
        return $this->chunkDir($encoding).sprintf('/chunk_%04d.mp4', $index);
    }

    protected function progressKey(VideoEncoding $encoding, int $index): string
    {
        return "video-encoding:{$encoding->id}:{$encoding->run_id}:{$index}";
    }

    /** Record one chunk's progress and, at most every few seconds, the rendition's. */
    public function recordChunkProgress(VideoEncoding $encoding, int $index, float $percent): void
    {
        Cache::put($this->progressKey($encoding, $index), min(100.0, max(0.0, $percent)), now()->addDay());

        $throttleKey = "{$encoding->id}:{$index}";
        $now = microtime(true);

        if (($this->lastProgressWrite[$throttleKey] ?? 0) > $now - self::PROGRESS_WRITE_INTERVAL) {
            return;
        }

        $this->lastProgressWrite[$throttleKey] = $now;
        $this->writeRenditionProgress($encoding);
    }

    protected function writeRenditionProgress(VideoEncoding $encoding): void
    {
        $total = 0.0;

        for ($i = 0; $i < $encoding->chunks_total; $i++) {
            $total += (float) Cache::get($this->progressKey($encoding, $i), 0.0);
        }

        // Stop short of 100 until the rendition is actually finalized.
        $progress = (int) min(99, floor($total / max(1, $encoding->chunks_total)));

        VideoEncoding::whereKey($encoding->id)
            ->where('run_id', $encoding->run_id)
            ->whereNotIn('status', VideoEncoding::TERMINAL)
            ->where('progress', '<', $progress)
            ->update(['progress' => $progress]);
    }

    /**
     * A chunk finished. When it was the last one, hand the rendition to
     * FinalizeRenditionJob, exactly once even if chunks finish together.
     */
    public function chunkFinished(VideoEncoding $encoding, int $index): void
    {
        Cache::put($this->progressKey($encoding, $index), 100.0, now()->addDay());

        $done = 0;
        for ($i = 0; $i < $encoding->chunks_total; $i++) {
            if (file_exists($this->chunkDoneMarker($encoding, $i))) {
                $done++;
            }
        }

        VideoEncoding::whereKey($encoding->id)
            ->where('run_id', $encoding->run_id)
            ->update(['chunks_completed' => $done]);

        $this->writeRenditionProgress($encoding);

        if ($done < $encoding->chunks_total) {
            return;
        }

        $claimed = VideoEncoding::whereKey($encoding->id)
            ->where('run_id', $encoding->run_id)
            ->whereIn('status', [VideoEncoding::QUEUED, VideoEncoding::PROCESSING])
            ->update(['status' => VideoEncoding::FINALIZING]);

        if ($claimed === 1) {
            $this->dispatchSafely(fn () => FinalizeRenditionJob::dispatch($encoding->id, $encoding->run_id)
                ->onQueue($encoding->is_priority ? self::PRIORITY_QUEUE : self::QUEUE));
        }
    }

    // ── Rendition outcomes ──────────────────────────────────────────────────

    public function renditionFailed(int $encodingId, string $runId, string $message): void
    {
        $encoding = VideoEncoding::with('video')->find($encodingId);

        if (! $encoding || $encoding->run_id !== $runId || $encoding->isTerminal()) {
            return;
        }

        $encoding->update([
            'status' => VideoEncoding::FAILED,
            'error' => Str::limit($message, 2000),
            'completed_at' => now(),
        ]);

        Log::error('Rendition failed', [
            'video_id' => $encoding->video_id,
            'quality' => $encoding->quality,
            'error' => Str::limit($message, 500),
        ]);

        if ($encoding->video) {
            $this->renditionFinished($encoding);
        }
    }

    /**
     * A rendition reached completed or failed.
     *
     * Publishes newly available qualities, releases the remaining renditions
     * once the priority one is done, and hands off to completion when nothing
     * is left.
     */
    public function renditionFinished(VideoEncoding $encoding): void
    {
        $video = $encoding->video()->first();

        if (! $video) {
            return;
        }

        $toDispatch = collect();

        Cache::lock("video-renditions:{$video->id}", 60)->block(30, function () use ($video, $encoding, &$toDispatch) {
            $encodings = $video->encodings()->with('profile')->get();

            $this->hls->writeMasterPlaylist($this->processedDir($video), $encodings->where('status', VideoEncoding::COMPLETED));
            $this->publishAvailableRenditions($video, $encodings);

            if ($encoding->is_priority) {
                $toDispatch = $encodings->where('status', VideoEncoding::PENDING);
            }
        });

        // Dispatch outside the lock: with a sync queue these jobs run inline and
        // would otherwise deadlock trying to take it again.
        foreach ($toDispatch as $pending) {
            $this->dispatchRendition($pending);
        }

        $this->dispatchCompletionIfDone($video);
    }

    /**
     * Make finished renditions watchable.
     *
     * The first time any rendition is ready, the video is marked processed —
     * running the normal approval, points and notification flow — rather than
     * waiting for the rest. After that, new qualities are simply added.
     *
     * @param  Collection<int, VideoEncoding>  $encodings
     */
    protected function publishAvailableRenditions(Video $video, Collection $encodings): void
    {
        $qualities = $this->availableQualities($encodings);

        if (empty(array_diff($qualities, ['original']))) {
            return;
        }

        if ($video->status !== 'processed') {
            $this->videoService->markAsProcessed($video, $qualities);
            $this->videoService->notifyProcessedOnce($video);

            return;
        }

        if ($qualities !== ($video->qualities_available ?? [])) {
            $video->update(['qualities_available' => $qualities]);
        }
    }

    /**
     * Qualities that can be played right now, lowest first, then 'original'.
     *
     * While a watermarked original is still pending the upload itself is
     * unwatermarked, so 'original' is held back until the copy replaces it.
     *
     * @param  Collection<int, VideoEncoding>  $encodings
     * @return list<string>
     */
    public function availableQualities(Collection $encodings): array
    {
        $qualities = $encodings
            ->where('status', VideoEncoding::COMPLETED)
            ->reject->isOriginal()
            ->sortBy('height')
            ->pluck('quality')
            ->values()
            ->all();

        $original = $encodings->first(fn (VideoEncoding $e) => $e->isOriginal());

        if (! $original || $original->isTerminal()) {
            $qualities[] = 'original';
        }

        return $qualities;
    }

    public function dispatchCompletionIfDone(Video $video): void
    {
        $encodings = $video->encodings()->get(['id', 'status', 'run_id']);

        if ($encodings->contains(fn (VideoEncoding $e) => ! $e->isTerminal())) {
            return;
        }

        // Several renditions can finish at once; complete each plan only once.
        $planKey = md5($encodings->pluck('run_id')->sort()->implode('|'));

        if (! Cache::add("video-complete:{$video->id}:{$planKey}", true, now()->addHours(6))) {
            return;
        }

        $this->dispatchSafely(fn () => CompleteVideoProcessingJob::dispatch($video->id)->onQueue(self::QUEUE));
    }

    // ── Completion ──────────────────────────────────────────────────────────

    /**
     * Wrap up a video once every rendition has finished or failed.
     */
    public function complete(Video $video): void
    {
        $settings = Setting::getAll();
        $encodings = $video->encodings()->with('profile')->get();

        if ($encodings->contains(fn (VideoEncoding $e) => ! $e->isTerminal())) {
            return;
        }

        $this->setStage($video, 'finishing');

        $original = $encodings->first(fn (VideoEncoding $e) => $e->isOriginal());
        $originalFailed = false;

        if ($original?->status === VideoEncoding::COMPLETED) {
            $this->swapInWatermarkedOriginal($video, $original);
        } elseif ($original?->status === VideoEncoding::FAILED) {
            $originalFailed = true;
        }

        $renditions = $encodings->reject->isOriginal();
        $failed = $renditions->where('status', VideoEncoding::FAILED);
        $reason = null;

        if ($renditions->isNotEmpty() && $renditions->where('status', VideoEncoding::COMPLETED)->isEmpty()) {
            $reason = 'Transcoding failed; shipped as unprocessed original.';
        } elseif ($failed->isNotEmpty()) {
            $reason = 'Some renditions failed: '.$failed
                ->map(fn (VideoEncoding $e) => "{$e->quality} (".Str::limit((string) $e->error, 200).')')
                ->implode('; ');
        }

        if ($originalFailed) {
            $reason = trim(($reason ? $reason.' ' : '').'Watermarking the original failed; the upload is served without it.');
        }

        $reason = $reason ? Str::limit($reason, 1000) : null;

        $this->hls->writeMasterPlaylist($this->processedDir($video), $encodings->where('status', VideoEncoding::COMPLETED));
        $this->removeChunks($video);

        $qualities = $this->availableQualities($encodings);

        if (($settings['cloud_offloading_enabled'] ?? false) && ($video->storage_disk ?? 'public') === 'public') {
            $target = StorageManager::getActiveDiskName();
            if (StorageManager::isCloudDisk($target)) {
                $this->setStage($video, 'uploading');
                $this->offloader->offload($video, $target, $settings);
            }
        }

        $video->refresh();

        if ($video->status === 'processed') {
            $this->videoService->updateRenditions($video, $qualities, $reason);
        } else {
            $this->videoService->markAsProcessed($video, $qualities, $reason);
        }

        $this->setStage($video, null);

        if ($reason !== null) {
            AdminLogger::error("Video #{$video->id} ({$video->title}) finished processing with problems: {$reason}", [
                'video_id' => $video->id,
                'qualities' => $qualities,
            ]);
        }

        $this->videoService->notifyProcessedOnce($video);
    }

    /**
     * Replace the upload with its watermarked copy.
     *
     * Deferred to completion because every other rendition encodes from the
     * upload and draws the watermark itself; swapping earlier would hand the
     * ones still running an already-watermarked source.
     */
    protected function swapInWatermarkedOriginal(Video $video, VideoEncoding $original): void
    {
        $watermarked = $this->outputPath($original->setRelation('video', $video));
        $source = $this->sourcePath($video);

        if (! file_exists($watermarked) || filesize($watermarked) < 10240) {
            return;
        }

        if (file_exists($source)) {
            unlink($source);
        }

        rename($watermarked, $source);
        file_put_contents($this->watermarkDoneMarker($video), now()->toIso8601String());

        $video->forceFill(['size' => filesize($source)])->saveQuietly();
    }

    protected function removeChunks(Video $video): void
    {
        $dir = $this->processedDir($video).'/chunks';

        if (! is_dir($dir)) {
            return;
        }

        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($items as $item) {
            $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
        }

        @rmdir($dir);
    }

    /**
     * Record what processing is doing, for the admin progress display.
     * Quiet, so stage changes don't flush every listing cache.
     */
    public function setStage(Video $video, ?string $stage): void
    {
        $video->forceFill(['processing_stage' => $stage])->saveQuietly();
    }
}

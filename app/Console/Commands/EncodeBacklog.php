<?php

namespace App\Console\Commands;

use App\Jobs\ProcessVideoJob;
use App\Models\Setting;
use App\Models\Video;
use App\Services\AdminLogger;
use App\Services\Encoding\HlsPackager;
use App\Support\Bytes;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Encode videos that were imported rather than uploaded.
 *
 * A migration from another site brought in videos as files alone: an upload
 * and nothing else. Measured on a real library, 1,684 of 2,231 video
 * directories were in this state, holding 46.5 GB — the upload is the only
 * playable file, so those videos stream progressively with no quality ladder
 * and no adaptive switching.
 *
 * This hands them to the normal pipeline, which builds the ladder, packages
 * HLS, and then compresses the upload and swaps it in
 * (CompleteVideoProcessingJob). Storage goes down only modestly for these —
 * the compressed master saves more than the new ladder costs, but not by much
 * — the point is that they become properly streamable.
 *
 * Deliberately a batch at a time. A thousand-odd encodes is days of work on a
 * niced worker, and it must never get in the way of live uploads, so this
 * queues `--limit` videos and is run again for the next batch.
 */
class EncodeBacklog extends Command
{
    protected $signature = 'videos:encode-backlog
        {--apply : Actually queue the work; without this, only report}
        {--limit=10 : How many videos to queue this run}
        {--order=views : views, size or oldest — which to encode first}
        {--watermark : Proceed even though a watermark is configured}';

    protected $description = 'Queue imported videos that have no rendition ladder for encoding';

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');

        if (! $this->watermarkSafe()) {
            return self::FAILURE;
        }

        $candidates = $this->candidates();

        if ($candidates->isEmpty()) {
            $this->components->info('Every video has a rendition ladder. Nothing to do.');

            return self::SUCCESS;
        }

        $bytes = 0;

        foreach ($candidates as $video) {
            $size = (int) ($this->sizeOf($video) ?? 0);
            $bytes += $size;

            $this->line(sprintf('  %s %s (%s)', $apply ? 'queued' : 'would queue', $video->slug, Bytes::format($size)));

            if ($apply) {
                ProcessVideoJob::dispatch($video);
            }
        }

        $remaining = $this->backlogCount();

        $this->newLine();
        $this->components->info(sprintf(
            '%s %d video%s (%s). %d left in the backlog.',
            $apply ? 'Queued' : 'Would queue',
            $candidates->count(),
            $candidates->count() === 1 ? '' : 's',
            Bytes::format($bytes),
            max(0, $remaining - ($apply ? $candidates->count() : 0)),
        ));

        if (! $apply) {
            $this->components->warn('Dry run. Re-run with --apply to queue this batch.');

            return self::SUCCESS;
        }

        AdminLogger::log(
            sprintf('Queued %d imported videos (%s) for encoding', $candidates->count(), Bytes::format($bytes)),
            'system',
        );

        $this->components->info('Watch Horizon, then run this again for the next batch.');

        return self::SUCCESS;
    }

    /**
     * Refuse to run into a watermark by accident.
     *
     * Imported videos have no `.watermark_done` marker, so the pipeline would
     * draw the configured watermark onto every one of them — and an import
     * that already carries the old site's burnt-in watermark would end up with
     * two. The audit found files named `*_watermarked.mp4`, so this is real.
     */
    protected function watermarkSafe(): bool
    {
        if (! Setting::get('watermark_enabled', false) || $this->option('watermark')) {
            return true;
        }

        $this->components->error('A watermark is configured, and these videos have never been through this pipeline.');
        $this->line('  Encoding them would draw it on, including onto imports that already carry one.');
        $this->line('  Turn the watermark off first, or pass --watermark if that is what you want.');

        return false;
    }

    /** Videos with an upload but no ladder at all, neither MP4s nor HLS. */
    protected function candidates()
    {
        $query = Video::query()
            ->where('is_embedded', false)
            ->where(fn ($q) => $q->whereNull('storage_disk')->orWhere('storage_disk', 'public'))
            ->whereNotNull('video_path')
            ->whereDoesntHave('encodings', fn ($q) => $q->where('status', 'completed'));

        match ((string) $this->option('order')) {
            'size' => $query->orderByDesc('size'),
            'oldest' => $query->orderBy('created_at'),
            default => $query->orderByDesc('views_count'),
        };

        // Take a wider slice than asked for, then drop anything already
        // packaged or whose file has gone, before cutting to the limit.
        return $query->limit(max(1, (int) $this->option('limit')) * 4)
            ->get()
            ->filter(fn (Video $video) => $this->needsEncoding($video))
            ->take(max(1, (int) $this->option('limit')))
            ->values();
    }

    protected function backlogCount(): int
    {
        return Video::query()
            ->where('is_embedded', false)
            ->whereNotNull('video_path')
            ->whereDoesntHave('encodings', fn ($q) => $q->where('status', 'completed'))
            ->count();
    }

    protected function needsEncoding(Video $video): bool
    {
        if ($this->sizeOf($video) === null) {
            return false;
        }

        $processedDir = dirname((string) $video->video_path).'/processed';

        foreach (['2160p', '1440p', '1080p', '720p', '480p', '360p', '240p', '144p'] as $quality) {
            if (HlsPackager::isPackaged(Storage::disk('public')->path($processedDir), $quality)) {
                return false;
            }
        }

        return true;
    }

    /** The upload's size, or null when it is no longer on disk. */
    protected function sizeOf(Video $video): ?int
    {
        try {
            return Storage::disk('public')->size((string) $video->video_path);
        } catch (\Throwable) {
            return null;
        }
    }
}

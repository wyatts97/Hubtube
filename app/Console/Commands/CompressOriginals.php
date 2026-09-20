<?php

namespace App\Console\Commands;

use App\Jobs\CompressMediaFileJob;
use App\Models\Video;
use App\Services\AdminLogger;
use App\Services\Encoding\HlsPackager;
use App\Services\Media\MediaCompressService;
use App\Support\Bytes;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Re-compress the uploads an existing library is still storing at full size.
 *
 * The upload is the biggest file a video owns and nothing streams from it —
 * the player is handed HLS, and the renditions were encoded from it once. On
 * the measured library they were 66.2 GB across 2,218 files, 60% of all video
 * storage. New uploads are compressed automatically once encoding finishes
 * (CompleteVideoProcessingJob); this is for everything uploaded before that.
 *
 * Each video is queued as an ordinary CompressMediaFileJob on `media-compress`
 * — one niced worker, so this never competes with live uploads — and the
 * result only replaces the upload after MediaCompressService verifies it is
 * smaller, the same length and a real video stream. A video whose encode fails
 * that check keeps its upload untouched.
 *
 * Dry run by default. `--apply` is what queues the work.
 */
class CompressOriginals extends Command
{
    protected $signature = 'videos:compress-originals
        {--apply : Actually queue the work; without this, only report}
        {--limit=25 : How many videos to queue, biggest upload first}
        {--min-mb=20 : Skip uploads smaller than this}
        {--codec= : h265, vp9 or av1; defaults to the best this server can encode}';

    protected $description = 'Queue re-compression of video uploads still stored at full size';

    public function handle(MediaCompressService $compress): int
    {
        $apply = (bool) $this->option('apply');
        $codec = (string) ($this->option('codec') ?: $compress->defaultCodec());

        if (! ($compress->available()[$codec] ?? false)) {
            $this->components->error("This server's ffmpeg cannot encode {$codec}.");

            return self::FAILURE;
        }

        $minBytes = max(0, (int) $this->option('min-mb')) * 1024 * 1024;
        $limit = max(1, (int) $this->option('limit'));

        $candidates = Video::query()
            ->where('status', 'processed')
            ->where('is_embedded', false)
            ->where(fn ($query) => $query->whereNull('storage_disk')->orWhere('storage_disk', 'public'))
            ->whereNotNull('video_path')
            ->where('size', '>=', $minBytes)
            ->orderByDesc('size')
            ->limit($limit * 3)
            ->get(['id', 'slug', 'title', 'video_path', 'size'])
            // Already one of ours, or gone from disk: nothing to do.
            ->reject(fn (Video $video) => $compress->isCompressedOutput((string) $video->video_path)
                || $compress->readableSize((string) $video->video_path) === null)
            // And never a video whose upload is the only playable copy. An
            // imported video has no HLS ladder, so the player streams the
            // upload itself — re-encoding it to AV1 or H.265 would leave
            // anyone without that decoder unable to watch it at all. Give
            // those to videos:encode-backlog first.
            ->reject(fn (Video $video) => ! $this->hasStream($video))
            ->take($limit);

        if ($candidates->isEmpty()) {
            $this->components->info('Nothing left to compress.');

            return self::SUCCESS;
        }

        $total = 0;

        foreach ($candidates as $video) {
            $size = (int) $video->size;
            $total += $size;

            $this->line(sprintf('  %s %s (%s)', $apply ? 'queued' : 'would queue', $video->slug, Bytes::format($size)));

            if ($apply) {
                CompressMediaFileJob::dispatch((string) $video->video_path, $codec, 'balanced', requestedBy: null, replaceOriginal: true);
            }
        }

        $this->newLine();
        $this->components->info(sprintf(
            '%s %d upload%s totalling %s as %s. Typically half of that is recovered.',
            $apply ? 'Queued' : 'Would queue',
            $candidates->count(),
            $candidates->count() === 1 ? '' : 's',
            Bytes::format($total),
            strtoupper($codec),
        ));

        if (! $apply) {
            $this->components->warn('Dry run. Re-run with --apply to queue the work.');

            return self::SUCCESS;
        }

        AdminLogger::log(
            sprintf('Queued %d uploads (%s) for re-compression as %s', $candidates->count(), Bytes::format($total), $codec),
            'system',
        );

        $this->components->info('They run one at a time on the media-compress queue. Re-run this command for the next batch.');

        return self::SUCCESS;
    }

    /** Whether the video has an HLS stream, so the upload is not its only copy. */
    protected function hasStream(Video $video): bool
    {
        $processedDir = Storage::disk('public')->path(dirname((string) $video->video_path).'/processed');

        foreach (['2160p', '1440p', '1080p', '720p', '480p', '360p', '240p', '144p'] as $quality) {
            if (HlsPackager::isPackaged($processedDir, $quality)) {
                return true;
            }
        }

        return false;
    }
}

<?php

namespace App\Console\Commands;

use App\Jobs\IndexMediaDirectoryJob;
use App\Models\Setting;
use App\Models\Video;
use App\Models\VideoEncoding;
use App\Services\AdminLogger;
use App\Services\Encoding\FfmpegCommands;
use App\Services\Encoding\HlsPackager;
use App\Services\Encoding\RenditionCoordinator;
use App\Support\Bytes;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

/**
 * Reclaim the second copy of every rendition in an existing library.
 *
 * HLS segments were packaged as a `-c copy` of the rendition MP4 beside them,
 * so each quality sat on disk twice — measured on a real library, 21.0 GB of
 * MP4s against 21.7 GB of the same bytes as segments. New videos no longer do
 * this (FinalizeRenditionJob); this walks what is already there.
 *
 * Per rendition: repackage into a single-file HLS stream (which also collapses
 * ~19,000 segment files into one per rendition), prove the stream plays at the
 * right length, and only then delete the MP4. A rendition that cannot be
 * verified is left exactly as it is — both copies, the wasteful but safe
 * state. There are no media backups on this box.
 *
 * Dry run by default. `--apply` is what deletes.
 */
class RepackHls extends Command
{
    protected $signature = 'videos:repack-hls
        {--apply : Actually repackage and delete; without this, only report}
        {--limit=0 : Stop after this many videos}
        {--video= : Only this video id}';

    protected $description = 'Store each rendition once, as HLS, and reclaim its duplicate MP4';

    public function handle(RenditionCoordinator $coordinator, HlsPackager $hls): int
    {
        $apply = (bool) $this->option('apply');
        $commands = new FfmpegCommands(Setting::getAll());

        if (! $commands->s('generate_hls', true)) {
            $this->components->error('generate_hls is off, so the MP4 ladder is the only playable copy. Nothing to do.');

            return self::FAILURE;
        }

        $videos = Video::query()
            ->where('status', 'processed')
            ->where(fn ($query) => $query->whereNull('storage_disk')->orWhere('storage_disk', 'public'))
            ->when($this->option('video'), fn ($query, $id) => $query->whereKey($id))
            ->when((int) $this->option('limit') > 0, fn ($query) => $query->limit((int) $this->option('limit')))
            ->orderBy('id')
            ->get(['id', 'slug', 'title', 'video_path', 'duration']);

        $freed = 0;
        $reclaimed = 0;
        $skipped = 0;

        foreach ($videos as $video) {
            // A video whose upload has gone is served progressively from its
            // renditions — bestDownloadPath() falls back to them — so those
            // MP4s are its only downloadable copy, not a duplicate. Twelve of
            // these turned up in the audit of a real library.
            if (! $this->uploadPresent($video)) {
                $this->components->warn("{$video->slug}: upload is missing, so its renditions are the only progressive copy — left alone");
                $skipped++;

                continue;
            }

            $processedDir = $coordinator->processedDir($video);
            $changed = 0;

            foreach ($this->renditionsOf($video) as $encoding) {
                $mp4 = "{$processedDir}/{$encoding->quality}.mp4";

                if (! file_exists($mp4)) {
                    continue;
                }

                $size = (int) filesize($mp4);

                if (! $apply) {
                    $this->line(sprintf('  would reclaim %s %s (%s)', $video->slug, $encoding->quality, Bytes::format($size)));
                    $freed += $size;
                    $reclaimed++;

                    continue;
                }

                // Repackage even when a stream already exists: the old layout
                // is hundreds of segment files, and the MP4 is still here to
                // package from.
                if (! $hls->packageRendition($commands, $processedDir, $encoding->quality)) {
                    $this->components->warn("{$video->slug} {$encoding->quality}: packaging failed, keeping the MP4");
                    $skipped++;

                    continue;
                }

                $reason = $hls->verifyPackaged($commands, $processedDir, $encoding->quality, (float) $video->duration);

                if ($reason !== null) {
                    $this->components->warn("{$video->slug} {$encoding->quality}: {$reason}, keeping the MP4");
                    $skipped++;

                    continue;
                }

                @unlink($mp4);
                $encoding->update(['size' => $hls->packagedSize($processedDir, $encoding->quality)]);

                $freed += $size;
                $reclaimed++;
                $changed++;
                $this->line(sprintf('  %s %s reclaimed %s', $video->slug, $encoding->quality, Bytes::format($size)));
            }

            // Only for videos this pass actually touched.
            if ($changed > 0) {
                IndexMediaDirectoryJob::dispatch('videos/'.$video->slug);
            }
        }

        $this->newLine();
        $this->components->info(sprintf(
            '%s %d rendition%s across %d videos, %s%s',
            $apply ? 'Reclaimed' : 'Would reclaim',
            $reclaimed,
            $reclaimed === 1 ? '' : 's',
            $videos->count(),
            Bytes::format($freed),
            $skipped > 0 ? ", {$skipped} left alone" : '',
        ));

        if (! $apply) {
            $this->components->warn('Dry run. Re-run with --apply to repackage and delete.');
        } elseif ($freed > 0) {
            AdminLogger::log(
                sprintf('Reclaimed %s by storing %d renditions as HLS only', Bytes::format($freed), $reclaimed),
                'system',
            );
        }

        return self::SUCCESS;
    }

    /** Whether the video's own upload is still on disk. */
    protected function uploadPresent(Video $video): bool
    {
        try {
            return (bool) $video->video_path && Storage::disk('public')->exists($video->video_path);
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * The renditions worth looking at: finished, and not the original.
     *
     * @return Collection<int, VideoEncoding>
     */
    protected function renditionsOf(Video $video)
    {
        return $video->encodings()
            ->where('status', VideoEncoding::COMPLETED)
            ->where('quality', '!=', VideoEncoding::ORIGINAL)
            ->get();
    }
}

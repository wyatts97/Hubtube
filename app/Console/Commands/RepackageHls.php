<?php

namespace App\Console\Commands;

use App\Models\Setting;
use App\Models\Video;
use App\Models\VideoEncoding;
use App\Services\Encoding\FfmpegCommands;
use App\Services\Encoding\HlsPackager;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Rebuild a video's HLS copy from the MP4 renditions it already has.
 *
 * The recovery path for an HLS reclaim that was accepted and then regretted —
 * once the kept tree is deleted, this is what puts adaptive streaming back.
 * It is a remux, not a re-encode (`-c copy`, renditions already carry
 * keyframes on a fixed grid), so it costs minutes rather than hours and loses
 * no quality.
 *
 * Also useful in its own right for a video whose packaging failed while its
 * renditions succeeded.
 */
class RepackageHls extends Command
{
    protected $signature = 'storage:repackage-hls
                            {video* : Video ids to repackage}
                            {--force : Repackage even if an HLS tree already exists}';

    protected $description = 'Rebuild HLS segments and the master playlist from existing MP4 renditions';

    public function handle(HlsPackager $packager): int
    {
        $commands = new FfmpegCommands(Setting::getAll());
        $disk = Storage::disk('public');
        $failures = 0;

        foreach ((array) $this->argument('video') as $id) {
            $video = Video::find((int) $id);

            if (! $video || ! $video->video_path) {
                $this->error("Video #{$id} was not found, or has no files.");
                $failures++;

                continue;
            }

            $processed = dirname($video->video_path).'/processed';

            if ($disk->directoryExists($processed.'/hls') && ! $this->option('force')) {
                $this->warn("Video #{$id} already has an HLS tree. Pass --force to rebuild it.");

                continue;
            }

            $completed = $video->encodings()
                ->where('status', VideoEncoding::COMPLETED)
                ->with('profile')
                ->get();

            $packaged = 0;

            foreach ($completed->reject->isOriginal() as $encoding) {
                $this->line("Video #{$id}: packaging {$encoding->quality}…");

                if ($packager->packageRendition($commands, $disk->path($processed), $encoding->quality)) {
                    $packaged++;

                    continue;
                }

                $this->warn("Video #{$id}: {$encoding->quality} could not be packaged.");
            }

            if ($packaged === 0) {
                $this->error("Video #{$id}: nothing was packaged, so the master playlist was left alone.");
                $failures++;

                continue;
            }

            $packager->writeMasterPlaylist($disk->path($processed), $completed);

            $this->info("Video #{$id}: repackaged {$packaged} rendition(s) as HLS.");
        }

        return $failures > 0 ? self::FAILURE : self::SUCCESS;
    }
}

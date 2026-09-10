<?php

namespace App\Console\Commands;

use App\Jobs\ProcessAdCreativeJob;
use App\Models\VideoAd;
use App\Services\FfmpegService;
use Illuminate\Console\Command;

/**
 * Populates duration/width/height on mp4 ad creatives uploaded before the VAST
 * migration, when ProcessAdCreativeJob had no reason to probe them.
 *
 * VastBuilder serves nominal values (30s, 640x360) for unprobed rows, so this is
 * a correctness pass rather than a prerequisite — but skip-offset timing and ad
 * slot sizing stay wrong until it runs.
 */
class BackfillAdMediaMetadata extends Command
{
    protected $signature = 'ads:backfill-media-metadata
                            {--dry-run : List the creatives that would be probed without dispatching}
                            {--force : Re-probe creatives that already have metadata}';

    protected $description = 'Probe duration and dimensions for local MP4 ad creatives so they can be served as VAST';

    public function handle(): int
    {
        if (! FfmpegService::isAvailable()) {
            $this->error('FFmpeg/ffprobe is not available — nothing can be probed on this host.');

            return self::FAILURE;
        }

        $query = VideoAd::query()
            ->where('type', 'mp4')
            ->whereNotNull('file_path');

        if (! $this->option('force')) {
            $query->where(function ($q) {
                $q->whereNull('duration')
                    ->orWhereNull('width')
                    ->orWhereNull('height');
            });
        }

        $ads = $query->get();

        if ($ads->isEmpty()) {
            $this->info('No ad creatives need probing.');

            return self::SUCCESS;
        }

        $this->info("Found {$ads->count()} ad creative(s) to probe.");

        foreach ($ads as $ad) {
            if ($this->option('dry-run')) {
                $this->line("  [DRY RUN] Would probe #{$ad->id} \"{$ad->name}\"");

                continue;
            }

            // Reuse the job rather than duplicating the ffprobe call. It is
            // idempotent, and re-running it also repairs a missing HLS variant.
            ProcessAdCreativeJob::dispatch($ad)->onQueue('ad-processing');
            $this->line("  Queued probe for #{$ad->id} \"{$ad->name}\"");
        }

        if (! $this->option('dry-run')) {
            $this->newLine();
            $this->info('Queued on the "ad-processing" queue — make sure a worker is consuming it.');
        }

        return self::SUCCESS;
    }
}

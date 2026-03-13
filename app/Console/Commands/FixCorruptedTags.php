<?php

namespace App\Console\Commands;

use App\Models\Video;
use Illuminate\Console\Command;

class FixCorruptedTags extends Command
{
    protected $signature = 'videos:fix-corrupted-tags {--dry-run : Show what would be fixed without saving}';

    protected $description = 'Fix videos whose tags were malformed (single-character arrays or string tags) into clean tag arrays';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');

        $videos = Video::whereNotNull('tags')
            ->where('tags', '!=', '[]')
            ->where('tags', '!=', 'null')
            ->get();

        $fixed = 0;

        /** @var Video $video */
        foreach ($videos as $video) {
            $rawTags = $video->getRawOriginal('tags');
            $currentTags = $video->tags;
            $normalized = Video::normalizeTagsInput($rawTags);

            $needsFix = !is_array($currentTags) || $currentTags !== $normalized;
            if (!$needsFix) {
                continue;
            }

            $this->info("Video #{$video->id} \"{$video->title}\":");
            $this->line("  Before(raw): " . (string) $rawTags);
            $this->line("  Before(cast): " . json_encode($currentTags));
            $this->line("  After:        " . json_encode($normalized));

            if (!$dryRun) {
                $video->tags = empty($normalized) ? null : $normalized;
                $video->save();
            }

            $fixed++;
        }

        if ($dryRun) {
            $this->info("\nDry run complete. {$fixed} video(s) would be fixed.");
        } else {
            $this->info("\nDone. {$fixed} video(s) fixed.");
        }

        return self::SUCCESS;
    }
}

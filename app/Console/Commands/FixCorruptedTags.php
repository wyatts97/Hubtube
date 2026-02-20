<?php

namespace App\Console\Commands;

use App\Models\Video;
use Illuminate\Console\Command;

class FixCorruptedTags extends Command
{
    protected $signature = 'videos:fix-corrupted-tags {--dry-run : Show what would be fixed without saving}';

    protected $description = 'Fix videos whose tags were corrupted into single-character arrays (e.g. ["A","t","o","m","i","c"] instead of ["Atomic"])';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');

        $videos = Video::whereNotNull('tags')
            ->where('tags', '!=', '[]')
            ->where('tags', '!=', 'null')
            ->get();

        $fixed = 0;

        foreach ($videos as $video) {
            $tags = $video->tags;

            if (!is_array($tags) || count($tags) < 2) {
                continue;
            }

            // Detect corruption: if most tags are single characters, they were likely
            // split from a string. A normal tag list would have multi-character entries.
            $singleCharCount = collect($tags)->filter(fn ($t) => is_string($t) && mb_strlen(trim($t)) <= 1)->count();
            $ratio = $singleCharCount / count($tags);

            if ($ratio < 0.5) {
                continue;
            }

            // Reconstruct: join all single chars back into a string, then split on
            // comma/space boundaries to recover the original tags.
            $joined = implode('', $tags);

            // Split on commas (with optional surrounding spaces)
            $reconstructed = array_values(array_filter(
                array_map('trim', explode(',', $joined)),
                fn ($t) => $t !== ''
            ));

            if (empty($reconstructed)) {
                continue;
            }

            $this->info("Video #{$video->id} \"{$video->title}\":");
            $this->line("  Before: " . json_encode($tags));
            $this->line("  After:  " . json_encode($reconstructed));

            if (!$dryRun) {
                $video->tags = $reconstructed;
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

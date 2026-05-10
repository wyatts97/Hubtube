<?php

namespace App\Console\Commands;

use App\Services\VideoDescriptionTemplate;
use Illuminate\Console\Command;

/**
 * CLI counterpart to the SEO Diagnostics "Apply to videos missing a description"
 * action. Useful for cron and large catalog backfills.
 */
class FillVideoDescriptions extends Command
{
    protected $signature = 'videos:fill-descriptions
        {--template= : Override the saved template for this run only}
        {--all : Include private/unapproved/unprocessed videos}
        {--limit= : Cap the number of videos updated}
        {--dry-run : Print the count without writing}';

    protected $description = 'Fill in missing video descriptions using the configured template';

    public function handle(): int
    {
        $template = (string) ($this->option('template') ?: VideoDescriptionTemplate::template());
        if (trim($template) === '') {
            $this->error('Empty template — nothing to apply.');
            return self::FAILURE;
        }

        $all = (bool) $this->option('all');
        $limit = $this->option('limit');
        $dry = (bool) $this->option('dry-run');

        $opts = [
            'only_public'    => !$all,
            'only_approved'  => !$all,
            'only_processed' => !$all,
            'limit'          => $limit !== null ? max(1, (int) $limit) : null,
            'dry_run'        => $dry,
        ];

        $missing = VideoDescriptionTemplate::missingCount($opts);
        $this->info("Eligible videos missing a description: {$missing}");

        if ($missing === 0) {
            return self::SUCCESS;
        }

        if ($dry) {
            $this->warn("Dry run — would update up to {$missing} video(s) using template:");
            $this->line('  ' . $template);
            return self::SUCCESS;
        }

        if (!$this->confirm("Apply template to all eligible videos?", true)) {
            $this->info('Cancelled.');
            return self::SUCCESS;
        }

        $count = VideoDescriptionTemplate::applyToMissing($template, $opts);
        $this->info("Updated {$count} video(s).");

        return self::SUCCESS;
    }
}

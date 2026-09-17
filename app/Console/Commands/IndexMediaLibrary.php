<?php

namespace App\Console\Commands;

use App\Services\Media\MediaIndexService;
use App\Services\Media\MediaPathGuard;
use Illuminate\Console\Command;

/**
 * Build or refresh the Media Library index.
 *
 * One command for both jobs. `media:index` on its own refreshes what has
 * changed and is what the scheduler runs; `--full` re-reads every file, which
 * is how a first deploy, a restored backup, or anything that changed a file's
 * contents without changing its directory gets picked up.
 *
 * Idempotent and safe to repeat. It writes nothing to the filesystem.
 */
class IndexMediaLibrary extends Command
{
    protected $signature = 'media:index
        {--path=* : Only index these directories, instead of every allowed root}
        {--full : Re-read every file rather than only changed directories}
        {--prune : Also delete index rows for files this pass never saw}';

    protected $description = 'Index the files the admin Media Library browses';

    public function handle(MediaIndexService $index, MediaPathGuard $guard): int
    {
        $paths = (array) $this->option('path');
        $started = microtime(true);

        if ($paths !== []) {
            $totals = ['added' => 0, 'updated' => 0, 'removed' => 0, 'directories' => 0];

            foreach ($paths as $path) {
                if (! $guard->isAllowed($guard->sanitize($path))) {
                    $this->components->error("Not an indexable path: {$path}");

                    return self::FAILURE;
                }

                $result = $index->indexDirectory($guard->sanitize($path), true);

                foreach ($result as $key => $value) {
                    $totals[$key] += $value;
                }
            }
        } else {
            $totals = $index->indexAll(
                prune: (bool) $this->option('prune'),
                full: (bool) $this->option('full'),
            );
        }

        $this->components->info(sprintf(
            'Indexed %d director%s: %d added, %d updated, %d removed (%.1fs)',
            $totals['directories'],
            $totals['directories'] === 1 ? 'y' : 'ies',
            $totals['added'],
            $totals['updated'],
            $totals['removed'],
            microtime(true) - $started,
        ));

        return self::SUCCESS;
    }
}

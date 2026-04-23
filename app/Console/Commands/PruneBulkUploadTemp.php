<?php

namespace App\Console\Commands;

use App\Models\Video;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Prune orphaned temporary files from the bulk upload directory.
 *
 * Deletes files older than a configurable threshold (default 24 hours) that
 * are not referenced by any Video row's video_path. This cleans up files
 * left behind when an admin uploads but never creates videos, or when the
 * job fails before moving the file to its final location.
 */
class PruneBulkUploadTemp extends Command
{
    protected $signature = 'videos:prune-bulk-temp {--hours=24}';
    protected $description = 'Prune orphaned bulk upload temp files older than N hours (default: 24)';

    public function handle(): int
    {
        $hours = (int) $this->option('hours');
        if ($hours < 1) {
            $this->error('Hours must be at least 1.');
            return self::FAILURE;
        }

        $cutoff = now()->subHours($hours);
        $directory = 'videos/admin-uploads';

        if (!Storage::disk('public')->exists($directory)) {
            $this->info("Directory '{$directory}' does not exist — nothing to prune.");
            return self::SUCCESS;
        }

        $files = Storage::disk('public')->files($directory);

        if (empty($files)) {
            $this->info("No files in '{$directory}' — nothing to prune.");
            return self::SUCCESS;
        }

        $pruned = 0;
        $skipped = 0;

        // Build a set of all video_paths that exist in the database to avoid
        // querying per-file. This is safe because bulk upload moves files
        // out of the temp directory before creating the Video row, so a
        // video_path should never point into admin-uploads.
        $allVideoPaths = Video::whereNotNull('video_path')
            ->pluck('video_path')
            ->map(fn ($p) => ltrim($p, '/\\'))
            ->flip()
            ->all();

        foreach ($files as $file) {
            $relative = ltrim($file, '/\\');

            // Skip if the file is referenced by any Video row (defensive guard)
            if (isset($allVideoPaths[$relative])) {
                $skipped++;
                continue;
            }

            $lastModified = Storage::disk('public')->lastModified($file);

            if ($lastModified < $cutoff->timestamp) {
                Storage::disk('public')->delete($file);
                $pruned++;
                $this->line("Pruned: {$relative}");
            } else {
                $skipped++;
            }
        }

        $this->info("Pruned {$pruned} orphaned file(s) older than {$hours}h. Skipped {$skipped}.");

        if ($pruned > 0) {
            Log::info('PruneBulkUploadTemp: pruned orphaned files', [
                'count' => $pruned,
                'hours' => $hours,
                'skipped' => $skipped,
            ]);
        }

        return self::SUCCESS;
    }
}

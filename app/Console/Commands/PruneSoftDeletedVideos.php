<?php

namespace App\Console\Commands;

use App\Models\Video;
use App\Services\StorageManager;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class PruneSoftDeletedVideos extends Command
{
    protected $signature = 'videos:prune-deleted {--days=30 : Days after soft-delete before permanent removal} {--dry-run : Preview without deleting}';

    protected $description = 'Permanently delete soft-deleted videos and their storage files';

    public function handle(): int
    {
        $days = (int) $this->option('days');
        $dryRun = $this->option('dry-run');

        $videos = Video::onlyTrashed()
            ->where('deleted_at', '<', now()->subDays($days))
            ->get();

        if ($videos->isEmpty()) {
            $this->info('No soft-deleted videos older than ' . $days . ' days.');
            return self::SUCCESS;
        }

        $this->info(($dryRun ? '[DRY RUN] ' : '') . "Found {$videos->count()} videos to permanently delete.");

        $deleted = 0;
        foreach ($videos as $video) {
            $this->line("  - [{$video->id}] {$video->title} (deleted {$video->deleted_at->diffForHumans()})");

            if (!$dryRun) {
                $disk = $video->storage_disk ?? 'public';

                // Delete video directory from storage
                $videoDir = "videos/{$video->slug}";
                if (StorageManager::exists($videoDir, $disk)) {
                    StorageManager::deleteDirectory($videoDir, $disk);
                }

                // Legacy path cleanup
                $legacyDir = "videos/{$video->user_id}/{$video->uuid}";
                if (StorageManager::exists($legacyDir, $disk)) {
                    StorageManager::deleteDirectory($legacyDir, $disk);
                }

                // Permanently delete the record
                $video->forceDelete();
                $deleted++;
            }
        }

        $this->info(($dryRun ? '[DRY RUN] Would delete' : 'Permanently deleted') . " {$deleted} videos.");

        if (!$dryRun) {
            Log::info('PruneSoftDeletedVideos: permanently deleted videos', ['count' => $deleted, 'days_threshold' => $days]);
        }

        return self::SUCCESS;
    }
}

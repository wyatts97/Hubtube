<?php

namespace App\Console\Commands;

use App\Models\MediaFile;
use App\Services\FileManagerThumbnailService;
use App\Services\Media\MediaThumbnailDispatcher;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Backfill and tidy Media Library thumbnails.
 *
 * The page queues thumbnails for the folder you are looking at, which covers
 * everyday use. This is for the rest: the first pass over an existing library,
 * a retry after ffmpeg was installed, and clearing thumbnails whose source is
 * long gone.
 */
class GenerateMediaThumbnails extends Command
{
    protected $signature = 'media:thumbnails
        {--limit=200 : How many files to queue in this run}
        {--retry-failed : Put previously failed files back in the queue}
        {--prune : Delete thumbnails whose source file no longer exists}';

    protected $description = 'Queue missing Media Library thumbnails and prune orphaned ones';

    public function handle(MediaThumbnailDispatcher $dispatcher, FileManagerThumbnailService $thumbnails): int
    {
        if ($this->option('retry-failed')) {
            $retried = MediaFile::query()
                ->where('thumbnail_state', MediaFile::THUMB_FAILED)
                ->update([
                    'thumbnail_state' => MediaFile::THUMB_PENDING,
                    'thumbnail_error' => null,
                ]);

            $this->components->info("Reset {$retried} failed thumbnail(s) to pending.");
        }

        // --limit=0 means "queue nothing", so a prune-only run does not also
        // schedule work.
        $limit = max(0, (int) $this->option('limit'));

        if ($limit > 0) {
            $files = MediaFile::query()
                ->awaitingThumbnail()
                ->orderBy('id')
                ->limit($limit)
                ->get();

            $dispatched = $dispatcher->dispatchFor($files);

            $this->components->info(sprintf(
                'Queued %d thumbnail job(s) from %d candidate(s).',
                $dispatched,
                $files->count(),
            ));

            $remaining = MediaFile::query()->awaitingThumbnail()->count();

            if ($remaining > 0) {
                $this->components->warn("{$remaining} file(s) still awaiting a thumbnail — run again to continue.");
            }
        }

        if ($this->option('prune')) {
            $this->prune($thumbnails);
        }

        return self::SUCCESS;
    }

    /**
     * Delete generated thumbnails that no index row claims.
     *
     * The owned set is loaded once and compared in memory rather than queried
     * per file — a few megabytes for a large library, and this runs weekly
     * rather than on a request.
     */
    protected function prune(FileManagerThumbnailService $thumbnails): void
    {
        $directory = $thumbnails->directory();

        if (! Storage::disk('public')->exists($directory)) {
            $this->components->info('No thumbnail directory to prune.');

            return;
        }

        $owned = array_flip(
            MediaFile::query()
                ->whereNotNull('thumbnail_path')
                ->pluck('thumbnail_path')
                ->all()
        );

        $deleted = 0;

        foreach (Storage::disk('public')->allFiles($directory) as $path) {
            if (! isset($owned[$path])) {
                Storage::disk('public')->delete($path);
                $deleted++;
            }
        }

        $this->components->info("Pruned {$deleted} orphaned thumbnail(s).");
    }
}

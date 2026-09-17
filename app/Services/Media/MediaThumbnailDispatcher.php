<?php

namespace App\Services\Media;

use App\Jobs\GenerateMediaThumbnailJob;
use App\Models\MediaFile;
use App\Services\FileManagerThumbnailService;

/**
 * Decides which files get a thumbnail job, and marks the ones that never will.
 *
 * Shared by the page — which queues only the pending rows on the page you are
 * looking at — and by `media:thumbnails`, which backfills in bulk. Keeping the
 * rules in one place is what stops a full `media:index` of fifty thousand files
 * turning into fifty thousand queued jobs: the indexer only ever marks rows
 * `pending`, and something has to ask for them before any work is scheduled.
 */
class MediaThumbnailDispatcher
{
    public function __construct(
        protected FileManagerThumbnailService $thumbnails,
    ) {}

    /**
     * Queue thumbnails for whichever of these files still need one.
     *
     * @param  iterable<MediaFile>  $files
     * @return int how many jobs were dispatched
     */
    public function dispatchFor(iterable $files): int
    {
        $dispatched = 0;
        $unsupported = [];

        foreach ($files as $file) {
            if (! $this->needsWork($file)) {
                continue;
            }

            // Settle formats that can never produce one here without spending
            // a worker on finding that out again.
            if (! $this->thumbnails->supports((string) $file->extension)) {
                $unsupported[] = $file->id;

                continue;
            }

            $file->update(['thumbnail_state' => MediaFile::THUMB_QUEUED]);

            GenerateMediaThumbnailJob::dispatch($file->id);
            $dispatched++;
        }

        if ($unsupported !== []) {
            MediaFile::query()
                ->whereIn('id', $unsupported)
                ->update(['thumbnail_state' => MediaFile::THUMB_UNSUPPORTED]);
        }

        return $dispatched;
    }

    /**
     * Whether this row is still waiting on a thumbnail.
     *
     * `queued` counts as settled here: the job is unique per file, so asking
     * again would be discarded anyway, and a job that died outright is put back
     * to `failed` by its own failed() handler.
     */
    protected function needsWork(MediaFile $file): bool
    {
        return in_array(
            $file->thumbnail_state,
            [MediaFile::THUMB_PENDING, MediaFile::THUMB_UNAVAILABLE],
            true
        );
    }
}

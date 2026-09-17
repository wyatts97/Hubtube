<?php

namespace App\Observers;

use App\Models\Video;
use App\Services\AltTextService;
use App\Services\Media\MediaReferenceResolver;

class VideoObserver
{
    public function __construct(
        private readonly AltTextService $altText,
        private readonly MediaReferenceResolver $references,
    ) {}

    /**
     * Fill the thumbnail alt text when it is blank.
     *
     * Runs on saving rather than created so that a video which gains its
     * thumbnail later still gets alt text: ProcessVideoJob sets videos.thumbnail
     * long after the row exists, and VideoService::handleThumbnailUpload()
     * replaces it again on a custom upload. Both go through a save.
     *
     * A non-empty value is never overwritten, so an editor's manual alt text in
     * the admin panel survives every subsequent save. seo:backfill-alt-text
     * --force is the deliberate way to regenerate.
     */
    public function saving(Video $video): void
    {
        if (filled($video->thumbnail_alt_text)) {
            return;
        }

        if (blank($video->title)) {
            return;
        }

        $video->thumbnail_alt_text = $this->altText->forVideo($video);
    }

    /**
     * Keep the Media Library's "in use" flags honest.
     *
     * A video gains, changes and loses file paths throughout its life — the
     * encoder sets video_path, watermarking replaces it, a custom thumbnail
     * overwrites another. Each of those changes which files are safe to delete
     * from the library, so both the old and the new value are re-checked.
     *
     * Only the flag is maintained here, not the index itself: the file is
     * written by the encoder, and IndexMediaDirectoryJob indexes the whole
     * videos/{slug} folder once processing completes.
     */
    /**
     * A new video claims whatever files it was created pointing at.
     *
     * Separate from updated() rather than one `saved` hook keyed on
     * wasRecentlyCreated: that flag stays true for the rest of the object's
     * life, so an update on the same instance would take the create branch and
     * never release the file it moved away from.
     */
    public function created(Video $video): void
    {
        $this->references->syncForPaths($this->pathsOf($video));
    }

    public function updated(Video $video): void
    {
        $paths = [];

        foreach (MediaReferenceResolver::VIDEO_PATH_COLUMNS as $column) {
            if (! $video->wasChanged($column)) {
                continue;
            }

            // Both ends: the file it let go of and the one it took up.
            $paths[] = $video->getOriginal($column);
            $paths[] = $video->getAttribute($column);
        }

        $this->references->syncForPaths(array_filter($paths));
    }

    /**
     * A deleted video's files change hands.
     *
     * A soft delete keeps them referenced — the video can still be restored for
     * 30 days (videos:prune-deleted), and deleting its files from the library
     * would break that. A force delete releases them.
     */
    public function forceDeleted(Video $video): void
    {
        $this->references->syncForPaths($this->pathsOf($video));
    }

    public function restored(Video $video): void
    {
        $this->references->syncForPaths($this->pathsOf($video));
    }

    /** @return list<string> */
    private function pathsOf(Video $video): array
    {
        return array_values(array_filter(array_map(
            fn (string $column) => $video->getAttribute($column),
            MediaReferenceResolver::VIDEO_PATH_COLUMNS,
        )));
    }
}

<?php

namespace App\Observers;

use App\Models\Video;
use App\Services\AltTextService;

class VideoObserver
{
    public function __construct(private readonly AltTextService $altText) {}

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
}

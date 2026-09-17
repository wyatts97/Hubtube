<?php

namespace App\Observers;

use App\Models\Image;
use App\Services\AltTextService;
use App\Services\Media\MediaReferenceResolver;

class ImageObserver
{
    public function __construct(
        private readonly AltTextService $altText,
        private readonly MediaReferenceResolver $references,
    ) {}

    /**
     * See VideoObserver::saving() for why this is saving-scoped and why a
     * non-empty existing value is left alone.
     */
    public function saving(Image $image): void
    {
        if (filled($image->alt_text)) {
            return;
        }

        if (blank($image->title) && blank($image->description)) {
            return;
        }

        $image->alt_text = $this->altText->forImage($image);
    }

    /**
     * Keep the Media Library's "in use" flags honest.
     *
     * See VideoObserver::saved() — same reasoning, and likewise only the flag:
     * the files are written by ImageService, which indexes the image's own
     * directory itself.
     */
    /** See VideoObserver::created() for why this is not one `saved` hook. */
    public function created(Image $image): void
    {
        $this->references->syncForPaths($this->pathsOf($image));
    }

    public function updated(Image $image): void
    {
        $paths = [];

        foreach (MediaReferenceResolver::IMAGE_PATH_COLUMNS as $column) {
            if (! $image->wasChanged($column)) {
                continue;
            }

            $paths[] = $image->getOriginal($column);
            $paths[] = $image->getAttribute($column);
        }

        $this->references->syncForPaths(array_filter($paths));
    }

    public function deleted(Image $image): void
    {
        // Images are not soft-deleted, so the files are released immediately.
        $this->references->syncForPaths($this->pathsOf($image));
    }

    /** @return list<string> */
    private function pathsOf(Image $image): array
    {
        return array_values(array_filter(array_map(
            fn (string $column) => $image->getAttribute($column),
            MediaReferenceResolver::IMAGE_PATH_COLUMNS,
        )));
    }
}

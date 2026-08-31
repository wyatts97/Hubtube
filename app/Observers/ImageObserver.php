<?php

namespace App\Observers;

use App\Models\Image;
use App\Services\AltTextService;

class ImageObserver
{
    public function __construct(private readonly AltTextService $altText) {}

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
}

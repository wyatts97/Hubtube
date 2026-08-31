<?php

namespace App\Observers;

use App\Models\Gallery;
use App\Services\AltTextService;

class GalleryObserver
{
    public function __construct(private readonly AltTextService $altText) {}

    public function saving(Gallery $gallery): void
    {
        if (filled($gallery->cover_alt_text) || blank($gallery->title)) {
            return;
        }

        $gallery->cover_alt_text = $this->altText->forGallery($gallery);
    }
}

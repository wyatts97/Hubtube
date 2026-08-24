<?php

namespace App\Services\Translation\Sections;

use App\Models\Video;
use Illuminate\Database\Eloquent\Builder;

class VideoSection extends ModelSection
{
    public function key(): string
    {
        return 'videos';
    }

    protected function modelClass(): string
    {
        return Video::class;
    }

    protected function fields(): array
    {
        return ['title', 'description'];
    }

    protected function query(): Builder
    {
        // Only content a visitor could actually reach is worth translating.
        return Video::query()
            ->where('privacy', 'public')
            ->where('is_approved', true)
            ->where('status', 'processed');
    }
}

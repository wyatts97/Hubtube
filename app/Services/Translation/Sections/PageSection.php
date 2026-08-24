<?php

namespace App\Services\Translation\Sections;

use App\Models\Page;
use Illuminate\Database\Eloquent\Builder;

class PageSection extends ModelSection
{
    public function key(): string
    {
        return 'pages';
    }

    protected function modelClass(): string
    {
        return Page::class;
    }

    protected function fields(): array
    {
        return ['title', 'content'];
    }

    protected function query(): Builder
    {
        return Page::query()->where('is_published', true);
    }

    /**
     * PageController::localeShow() resolves the original slug only, so a
     * per-locale page slug would advertise URLs that 404.
     */
    protected function slugField(): ?string
    {
        return null;
    }
}

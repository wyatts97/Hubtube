<?php

namespace App\Services\Translation\Sections;

use App\Models\Category;
use Illuminate\Database\Eloquent\Builder;

class CategorySection extends ModelSection
{
    public function key(): string
    {
        return 'categories';
    }

    protected function modelClass(): string
    {
        return Category::class;
    }

    protected function fields(): array
    {
        return ['name', 'description'];
    }

    protected function query(): Builder
    {
        return Category::query();
    }

    /** Categories have no `title`; their slug is not locale-specific. */
    protected function slugField(): ?string
    {
        return null;
    }
}

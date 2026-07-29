<?php

namespace App\Observers;

use App\Models\Category;
use App\Models\MenuItem;

class CategoryObserver
{
    public function created(Category $category): void
    {
        $this->syncToMenu($category);
    }

    public function updated(Category $category): void
    {
        $menuItem = MenuItem::where('category_id', $category->id)->first();

        if (! $menuItem) {
            $this->syncToMenu($category);
            return;
        }

        $menuItem->update($this->menuAttributes($category));
    }

    public function deleted(Category $category): void
    {
        MenuItem::where('category_id', $category->id)->delete();
    }

    protected function syncToMenu(Category $category): void
    {
        MenuItem::create($this->menuAttributes($category));
    }

    protected function menuAttributes(Category $category): array
    {
        $parentMenuId = null;

        if ($category->parent_id) {
            $parentMenuId = MenuItem::where('category_id', $category->parent_id)->value('id');
        }

        return [
            'category_id'  => $category->id,
            'label'        => $category->name,
            'type'         => 'category',
            'url'          => '/category/' . $category->slug,
            'target'       => '_self',
            'icon'         => null,
            'parent_id'    => $parentMenuId,
            'sort_order'   => $category->sort_order,
            'is_active'    => $category->is_active,
            'is_mega'      => false,
            'mega_columns' => 4,
            'location'     => 'both',
        ];
    }
}

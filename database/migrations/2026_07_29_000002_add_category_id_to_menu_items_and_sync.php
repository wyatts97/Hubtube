<?php

use App\Models\Category;
use App\Models\MenuItem;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('menu_items', function (Blueprint $table) {
            $table->foreignId('category_id')
                ->nullable()
                ->after('id')
                ->constrained('categories')
                ->cascadeOnDelete();
        });

        // Sync existing categories into the menu builder
        $categories = Category::all();
        $map = [];

        foreach ($categories as $category) {
            $menuItem = MenuItem::create([
                'category_id'  => $category->id,
                'label'        => $category->name,
                'type'         => 'category',
                'url'          => '/category/' . $category->slug,
                'target'       => '_self',
                'icon'         => null,
                'parent_id'    => null,
                'sort_order'   => $category->sort_order,
                'is_active'    => $category->is_active,
                'is_mega'      => false,
                'mega_columns' => 4,
                'location'     => 'both',
            ]);

            $map[$category->id] = $menuItem->id;
        }

        foreach ($categories as $category) {
            if ($category->parent_id && isset($map[$category->parent_id])) {
                MenuItem::where('category_id', $category->id)
                    ->update(['parent_id' => $map[$category->parent_id]]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('menu_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('category_id');
        });
    }
};

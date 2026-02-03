<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['name' => 'Amateur', 'sort_order' => 1],
            ['name' => 'Asian', 'sort_order' => 2],
            ['name' => 'BBW', 'sort_order' => 3],
            ['name' => 'Big Tits', 'sort_order' => 4],
            ['name' => 'Blonde', 'sort_order' => 5],
            ['name' => 'Brunette', 'sort_order' => 6],
            ['name' => 'Ebony', 'sort_order' => 7],
            ['name' => 'Latina', 'sort_order' => 8],
            ['name' => 'Lesbian', 'sort_order' => 9],
            ['name' => 'MILF', 'sort_order' => 10],
            ['name' => 'Teen', 'sort_order' => 11],
            ['name' => 'Threesome', 'sort_order' => 12],
            ['name' => 'Anal', 'sort_order' => 13],
            ['name' => 'Blowjob', 'sort_order' => 14],
            ['name' => 'Creampie', 'sort_order' => 15],
            ['name' => 'Cumshot', 'sort_order' => 16],
            ['name' => 'Hardcore', 'sort_order' => 17],
            ['name' => 'Interracial', 'sort_order' => 18],
            ['name' => 'POV', 'sort_order' => 19],
            ['name' => 'Solo', 'sort_order' => 20],
        ];

        foreach ($categories as $category) {
            Category::create([
                'name' => $category['name'],
                'slug' => Str::slug($category['name']),
                'is_active' => true,
                'sort_order' => $category['sort_order'],
            ]);
        }
    }
}

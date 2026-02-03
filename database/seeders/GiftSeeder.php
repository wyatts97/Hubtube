<?php

namespace Database\Seeders;

use App\Models\Gift;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class GiftSeeder extends Seeder
{
    public function run(): void
    {
        $gifts = [
            ['name' => 'Rose', 'icon' => '🌹', 'price' => 1.00, 'animation_type' => 'float'],
            ['name' => 'Heart', 'icon' => '❤️', 'price' => 2.00, 'animation_type' => 'bounce'],
            ['name' => 'Kiss', 'icon' => '💋', 'price' => 5.00, 'animation_type' => 'float'],
            ['name' => 'Fire', 'icon' => '🔥', 'price' => 10.00, 'animation_type' => 'explode'],
            ['name' => 'Diamond', 'icon' => '💎', 'price' => 25.00, 'animation_type' => 'bounce'],
            ['name' => 'Crown', 'icon' => '👑', 'price' => 50.00, 'animation_type' => 'rain'],
            ['name' => 'Rocket', 'icon' => '🚀', 'price' => 100.00, 'animation_type' => 'explode'],
            ['name' => 'Money Bag', 'icon' => '💰', 'price' => 200.00, 'animation_type' => 'rain'],
            ['name' => 'Star', 'icon' => '⭐', 'price' => 500.00, 'animation_type' => 'explode'],
            ['name' => 'Unicorn', 'icon' => '🦄', 'price' => 1000.00, 'animation_type' => 'rain'],
        ];

        foreach ($gifts as $index => $gift) {
            Gift::create([
                'name' => $gift['name'],
                'slug' => Str::slug($gift['name']),
                'icon' => $gift['icon'],
                'price' => $gift['price'],
                'animation_type' => $gift['animation_type'],
                'is_active' => true,
                'sort_order' => $index + 1,
            ]);
        }
    }
}

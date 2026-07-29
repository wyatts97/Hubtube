<?php

namespace Database\Factories;

use App\Models\Image;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class ImageFactory extends Factory
{
    protected $model = Image::class;

    public function definition(): array
    {
        $title = fake()->sentence(3);
        $uuid = (string) Str::ulid();

        return [
            'user_id' => User::factory(),
            'uuid' => $uuid,
            'slug' => Str::slug($title) . '-' . Str::lower(Str::random(6)),
            'title' => $title,
            'description' => fake()->paragraph(),
            'file_path' => "images/{$uuid}/original.jpg",
            'thumbnail_path' => "images/{$uuid}/thumbnail.jpg",
            'storage_disk' => 'public',
            'mime_type' => 'image/jpeg',
            'width' => 1920,
            'height' => 1080,
            'file_size' => fake()->numberBetween(100000, 5000000),
            'is_animated' => false,
            'privacy' => 'public',
            'is_approved' => true,
            'views_count' => 0,
            'likes_count' => 0,
            'published_at' => now(),
        ];
    }

    public function unapproved(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_approved' => false,
        ]);
    }

    public function private(): static
    {
        return $this->state(fn (array $attributes) => [
            'privacy' => 'private',
        ]);
    }
}

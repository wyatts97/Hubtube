<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\User;
use App\Models\Video;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class VideoFactory extends Factory
{
    protected $model = Video::class;

    public function definition(): array
    {
        $title = fake()->sentence(4);

        return [
            'user_id' => User::factory(),
            'uuid' => (string) Str::ulid(),
            'title' => $title,
            'slug' => Str::slug($title) . '-' . Str::random(6),
            'description' => fake()->paragraph(),
            'thumbnail' => 'videos/test/thumbnail.jpg',
            'video_path' => 'videos/test/video.mp4',
            'storage_disk' => 'public',
            'duration' => fake()->numberBetween(30, 3600),
            'size' => fake()->numberBetween(1000000, 500000000),
            'privacy' => 'public',
            'status' => 'processed',
            'is_short' => false,
            'is_featured' => false,
            'is_approved' => true,
            'age_restricted' => false,
            'monetization_enabled' => false,
            'views_count' => fake()->numberBetween(0, 10000),
            'likes_count' => fake()->numberBetween(0, 500),
            'dislikes_count' => fake()->numberBetween(0, 50),
            'comments_count' => fake()->numberBetween(0, 100),
            'category_id' => Category::factory(),
            'tags' => ['test', 'video'],
            'published_at' => now(),
        ];
    }

    public function short(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_short' => true,
            'duration' => fake()->numberBetween(5, 60),
        ]);
    }

    public function featured(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_featured' => true,
        ]);
    }

    public function private(): static
    {
        return $this->state(fn (array $attributes) => [
            'privacy' => 'private',
        ]);
    }

    public function unlisted(): static
    {
        return $this->state(fn (array $attributes) => [
            'privacy' => 'unlisted',
        ]);
    }

    public function processing(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'processing',
            'is_approved' => false,
        ]);
    }

    public function unapproved(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_approved' => false,
        ]);
    }

    public function embedded(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_embedded' => true,
            'embed_url' => 'https://example.com/embed/test',
            'video_path' => null,
        ]);
    }

    public function paid(float $price = 9.99): static
    {
        return $this->state(fn (array $attributes) => [
            'price' => $price,
            'monetization_enabled' => true,
        ]);
    }
}

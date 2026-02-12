<?php

namespace Database\Factories;

use App\Models\Playlist;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class PlaylistFactory extends Factory
{
    protected $model = Playlist::class;

    public function definition(): array
    {
        $title = fake()->words(3, true);

        return [
            'user_id' => User::factory(),
            'title' => ucwords($title),
            'slug' => Str::slug($title) . '-' . Str::random(4),
            'description' => fake()->sentence(),
            'privacy' => 'public',
            'is_default' => false,
            'video_count' => 0,
        ];
    }

    public function private(): static
    {
        return $this->state(fn (array $attributes) => [
            'privacy' => 'private',
        ]);
    }
}

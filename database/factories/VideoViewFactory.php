<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\Video;
use App\Models\VideoView;
use Illuminate\Database\Eloquent\Factories\Factory;

class VideoViewFactory extends Factory
{
    protected $model = VideoView::class;

    public function definition(): array
    {
        return [
            'video_id' => Video::factory(),
            // Most views are from guests, which is also what makes the unique
            // viewer count fall back to the address.
            'user_id' => null,
            'ip_address' => fake()->ipv4(),
            'user_agent' => fake()->userAgent(),
            'country' => fake()->randomElement(['US', 'DE', 'GB', 'BR', 'JP']),
            'referrer' => null,
        ];
    }

    public function byMember(?User $user = null): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => $user?->id ?? User::factory(),
        ]);
    }
}

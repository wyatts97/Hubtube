<?php

namespace Database\Factories;

use App\Models\VideoAd;
use Illuminate\Database\Eloquent\Factories\Factory;

class VideoAdFactory extends Factory
{
    protected $model = VideoAd::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->sentence(3),
            'type' => 'mp4',
            'content' => $this->faker->url,
            'placement' => 'pre_roll',
            'target_roles' => ['guest', 'default', 'pro', 'admin'],
            'category_ids' => null,
            'weight' => 1,
            'is_active' => true,
        ];
    }
}

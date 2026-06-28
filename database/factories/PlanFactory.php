<?php

namespace Database\Factories;

use App\Models\Plan;
use Illuminate\Database\Eloquent\Factories\Factory;

class PlanFactory extends Factory
{
    protected $model = Plan::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->words(2, true),
            'slug' => $this->faker->unique()->slug,
            'stripe_price_id' => null,
            'amount_cents' => 999,
            'interval' => 'month',
            'annual_discount_percent' => 0,
            'is_active' => true,
        ];
    }
}

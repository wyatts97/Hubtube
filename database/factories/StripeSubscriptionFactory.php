<?php

namespace Database\Factories;

use App\Models\StripeSubscription;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class StripeSubscriptionFactory extends Factory
{
    protected $model = StripeSubscription::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'type' => 'pro',
            'stripe_id' => 'sub_' . $this->faker->unique()->uuid,
            'stripe_status' => 'active',
            'stripe_price' => 'price_' . $this->faker->uuid,
            'quantity' => 1,
            'trial_ends_at' => null,
            'ends_at' => null,
        ];
    }
}

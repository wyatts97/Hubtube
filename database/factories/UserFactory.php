<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserFactory extends Factory
{
    protected $model = User::class;

    public function definition(): array
    {
        return [
            'username' => fake()->unique()->userName(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => Hash::make('password'),
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'avatar' => null,
            'bio' => fake()->sentence(),
            'is_admin' => false,
            'is_pro' => false,
            'is_verified' => false,
            'wallet_balance' => 0,
            'remember_token' => Str::random(10),
        ];
    }

    public function admin(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_admin' => true,
        ]);
    }

    public function pro(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_pro' => true,
        ]);
    }

    public function verified(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_verified' => true,
        ]);
    }

    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    public function withBalance(float $amount): static
    {
        return $this->state(fn (array $attributes) => [
            'wallet_balance' => $amount,
        ]);
    }
}

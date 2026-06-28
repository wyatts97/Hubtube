<?php

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    public function run(): void
    {
        Plan::query()->updateOrCreate(
            ['slug' => 'pro-monthly'],
            [
                'name' => 'Pro Monthly',
                'amount_cents' => 999,
                'interval' => 'month',
                'annual_discount_percent' => 0,
                'is_active' => true,
            ]
        );

        Plan::query()->updateOrCreate(
            ['slug' => 'pro-annual'],
            [
                'name' => 'Pro Annual',
                'amount_cents' => 9599,
                'interval' => 'year',
                'annual_discount_percent' => 20,
                'is_active' => true,
            ]
        );
    }
}

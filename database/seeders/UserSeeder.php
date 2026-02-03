<?php

namespace Database\Seeders;

use App\Models\Channel;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::firstOrCreate(
            ['email' => 'admin@hubtube.com'],
            [
                'username' => 'admin',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
                'is_admin' => true,
                'is_verified' => true,
                'is_pro' => true,
                'wallet_balance' => 1000.00,
                'age_verified_at' => now(),
            ]
        );

        Channel::firstOrCreate(
            ['user_id' => $admin->id],
            [
                'name' => 'Admin',
                'slug' => 'admin-' . $admin->id,
                'is_verified' => true,
            ]
        );

        $demo = User::firstOrCreate(
            ['email' => 'demo@hubtube.com'],
            [
                'username' => 'demouser',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
                'is_verified' => true,
                'wallet_balance' => 100.00,
                'age_verified_at' => now(),
            ]
        );

        Channel::firstOrCreate(
            ['user_id' => $demo->id],
            [
                'name' => 'Demo User',
                'slug' => 'demouser-' . $demo->id,
            ]
        );
    }
}

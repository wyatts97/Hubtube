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
        // Outside production both accounts use the password "password". In
        // production the admin gets a random one, printed once, and the demo
        // account is not created.
        $production = app()->isProduction();
        $adminPassword = $production ? Str::password(20) : 'password';

        $admin = User::firstOrCreate(
            ['email' => 'admin@hubtube.com'],
            [
                'username' => 'admin',
                'password' => Hash::make($adminPassword),
                'email_verified_at' => now(),
                'is_admin' => true,
                // The only admin must reach settings, as the installer's does.
                'is_super_admin' => true,
                'is_verified' => true,
                'is_pro' => true,
                'wallet_balance' => $production ? 0 : 1000.00,
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

        if ($production) {
            if ($admin->wasRecentlyCreated) {
                $this->command?->warn("Admin login: admin@hubtube.com / {$adminPassword}");
                $this->command?->warn('Shown once — sign in and change the email and password now.');
            }

            return;
        }

        $demo = User::firstOrCreate(
            ['email' => 'demo@hubtube.com'],
            [
                'username' => 'demouser',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
                'is_verified' => false,
                'wallet_balance' => 100.00,
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

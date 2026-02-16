<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            CategorySeeder::class,
            GiftSeeder::class,
            UserSeeder::class,
            SettingsSeeder::class,
            PageSeeder::class,
            EmailTemplateSeeder::class,
        ]);
    }
}

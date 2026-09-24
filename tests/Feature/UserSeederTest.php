<?php

use App\Models\User;
use Database\Seeders\UserSeeder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;

test('in production the seeded admin gets a random password and no demo account', function () {
    app()->detectEnvironment(fn () => 'production');

    // Directly, as db:seed would, minus its production confirmation prompt.
    Model::unguarded(fn () => app(UserSeeder::class)->run());

    $admin = User::where('email', 'admin@hubtube.com')->first();
    expect($admin)->not->toBeNull()
        ->and($admin->is_super_admin)->toBeTrue()
        ->and(Hash::check('password', $admin->password))->toBeFalse()
        ->and(User::where('email', 'demo@hubtube.com')->exists())->toBeFalse();
});

test('outside production the seeder keeps its known demo logins', function () {
    $this->seed(UserSeeder::class);

    expect(Hash::check('password', User::where('email', 'admin@hubtube.com')->value('password')))->toBeTrue()
        ->and(User::where('email', 'demo@hubtube.com')->exists())->toBeTrue();
});

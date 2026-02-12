<?php

use App\Models\User;

/*
|--------------------------------------------------------------------------
| User Model — Accessors, Business Logic, Permissions
|--------------------------------------------------------------------------
*/

test('user name accessor returns full name when set', function () {
    $user = User::factory()->make([
        'first_name' => 'John',
        'last_name' => 'Doe',
        'username' => 'johndoe',
    ]);

    expect($user->name)->toBe('John Doe');
});

test('user name accessor falls back to username', function () {
    $user = User::factory()->make([
        'first_name' => null,
        'last_name' => null,
        'username' => 'johndoe',
    ]);

    expect($user->name)->toBe('johndoe');
});

test('admin can access filament panel', function () {
    $admin = User::factory()->admin()->make();
    expect($admin->is_admin)->toBeTrue();
});

test('regular user cannot access filament panel', function () {
    $user = User::factory()->make();
    expect($user->is_admin)->toBeFalse();
});

test('user isAgeVerified returns true when verified', function () {
    $user = User::factory()->make(['age_verified_at' => now()]);
    expect($user->isAgeVerified())->toBeTrue();
});

test('user isAgeVerified returns false when not verified', function () {
    $user = User::factory()->make(['age_verified_at' => null]);
    expect($user->isAgeVerified())->toBeFalse();
});

test('admin canEditVideo returns true', function () {
    $user = User::factory()->admin()->make();
    expect($user->canEditVideo())->toBeTrue();
});

test('pro user canEditVideo returns true', function () {
    $user = User::factory()->pro()->make();
    expect($user->canEditVideo())->toBeTrue();
});

test('regular user canEditVideo returns false', function () {
    $user = User::factory()->make(['is_admin' => false, 'is_pro' => false]);
    expect($user->canEditVideo())->toBeFalse();
});

test('user max video size is calculated correctly for free user', function () {
    $user = User::factory()->make(['is_pro' => false]);
    // Default is 500 MB for free users
    $expected = 500 * 1048576;
    expect($user->max_video_size)->toBe($expected);
});

test('user password is hidden from serialization', function () {
    $user = User::factory()->make();
    $array = $user->toArray();

    expect($array)->not->toHaveKey('password');
    expect($array)->not->toHaveKey('remember_token');
    expect($array)->not->toHaveKey('two_factor_secret');
});

test('user has many videos relationship', function () {
    $user = User::factory()->create();
    expect($user->videos())->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\HasMany::class);
});

test('user has many comments relationship', function () {
    $user = User::factory()->create();
    expect($user->comments())->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\HasMany::class);
});

test('user has many playlists relationship', function () {
    $user = User::factory()->create();
    expect($user->playlists())->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\HasMany::class);
});

test('user wallet_balance is cast to decimal', function () {
    $user = User::factory()->create(['wallet_balance' => 100.50]);
    $fresh = $user->fresh();
    expect((float) $fresh->wallet_balance)->toBe(100.50);
});

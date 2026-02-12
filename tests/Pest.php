<?php

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
*/

uses(Tests\TestCase::class)->in('Feature', 'Unit');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
*/

/**
 * Create and authenticate a regular user.
 */
function asUser(?App\Models\User $user = null): App\Models\User
{
    $user ??= App\Models\User::factory()->create();
    test()->actingAs($user);
    return $user;
}

/**
 * Create and authenticate an admin user.
 */
function asAdmin(?App\Models\User $user = null): App\Models\User
{
    $user ??= App\Models\User::factory()->admin()->create();
    test()->actingAs($user);
    return $user;
}

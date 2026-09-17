<?php

use App\Models\User;
use App\Services\UserBanService;

/*
|--------------------------------------------------------------------------
| Bans and suspensions
|--------------------------------------------------------------------------
*/

function banned(array $attributes = []): User
{
    $user = User::factory()->create($attributes);
    app(UserBanService::class)->ban($user, 'Spamming');

    return $user->fresh();
}

test('banning records who, why and when', function () {
    $admin = User::factory()->admin()->create();
    $user = User::factory()->create();

    app(UserBanService::class)->ban($user, 'Repeated copyright claims', $admin);
    $user->refresh();

    expect($user->isBanned())->toBeTrue()
        ->and($user->isBlocked())->toBeTrue()
        ->and($user->ban_reason)->toBe('Repeated copyright claims')
        ->and($user->banned_by)->toBe($admin->id);
});

test('a banned user cannot sign in, and is told why', function () {
    $user = banned(['password' => bcrypt('secret-password')]);

    $this->post('/login', ['login' => $user->username, 'password' => 'secret-password'])
        ->assertSessionHasErrors('login');

    expect(auth()->check())->toBeFalse();
    expect(session('errors')->first('login'))->toContain('banned')->toContain('Spamming');
});

test('a suspension blocks sign-in until it runs out', function () {
    $user = User::factory()->create(['password' => bcrypt('secret-password')]);
    app(UserBanService::class)->suspend($user, now()->addDay(), 'Cooling off');

    $this->post('/login', ['login' => $user->username, 'password' => 'secret-password'])
        ->assertSessionHasErrors('login');
    expect(auth()->check())->toBeFalse();

    // Once it has passed, the same account signs in normally.
    $user->forceFill(['suspended_until' => now()->subMinute()])->save();

    $this->post('/login', ['login' => $user->username, 'password' => 'secret-password']);
    expect(auth()->check())->toBeTrue();
});

test('a session open when the ban lands is signed out on the next request', function () {
    $user = asUser();

    $this->get('/')->assertOk();

    app(UserBanService::class)->ban($user, 'Abuse');

    $this->get('/')->assertRedirect(route('login'));
    expect(auth()->check())->toBeFalse();
});

test('a banned admin loses the panel too', function () {
    $admin = asAdmin();
    app(UserBanService::class)->ban($admin, 'Compromised account');

    $this->get('/admin')->assertRedirect(route('login'));
});

test('lifting a block restores access', function () {
    $user = banned(['password' => bcrypt('secret-password')]);

    app(UserBanService::class)->lift($user);
    $user->refresh();

    expect($user->isBlocked())->toBeFalse()
        ->and($user->ban_reason)->toBeNull();

    $this->post('/login', ['login' => $user->username, 'password' => 'secret-password']);
    expect(auth()->check())->toBeTrue();
});

test('the blocked scope finds banned and suspended accounts only', function () {
    $active = User::factory()->create();
    $banned = banned();
    $suspended = User::factory()->create();
    app(UserBanService::class)->suspend($suspended, now()->addHour());
    $expired = User::factory()->create(['suspended_until' => now()->subDay()]);

    $ids = User::blocked()->pluck('id');

    expect($ids)->toContain($banned->id)
        ->toContain($suspended->id)
        ->not->toContain($active->id)
        ->not->toContain($expired->id);
});

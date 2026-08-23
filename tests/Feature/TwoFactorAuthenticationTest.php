<?php

use App\Models\User;
use App\Services\TwoFactorAuthenticationService;

/*
|--------------------------------------------------------------------------
| TOTP Two-Factor Authentication
|--------------------------------------------------------------------------
*/

test('guest cannot access two-factor settings endpoints', function () {
    $this->postJson('/settings/two-factor/enable')->assertStatus(401);
});

test('user can enable two-factor authentication and receives a qr code', function () {
    asUser();

    $response = $this->postJson('/settings/two-factor/enable');

    $response->assertStatus(200);
    $response->assertJsonStructure(['qr_code_svg', 'secret']);
});

test('user can confirm two-factor setup with a valid code and receives recovery codes', function () {
    $user = asUser();
    $service = app(TwoFactorAuthenticationService::class);

    $this->postJson('/settings/two-factor/enable')->assertStatus(200);
    $user->refresh();

    $validCode = $service->getCurrentOtp($user->two_factor_secret);

    $response = $this->postJson('/settings/two-factor/confirm', ['code' => $validCode]);

    $response->assertStatus(200);
    $response->assertJsonStructure(['recovery_codes']);
    expect($response->json('recovery_codes'))->toHaveCount(8);
    expect($user->fresh()->hasTwoFactorEnabled())->toBeTrue();
});

test('confirming two-factor setup with an invalid code fails', function () {
    $user = asUser();

    $this->postJson('/settings/two-factor/enable')->assertStatus(200);

    $response = $this->postJson('/settings/two-factor/confirm', ['code' => '000000']);

    $response->assertStatus(422);
    expect($user->fresh()->hasTwoFactorEnabled())->toBeFalse();
});

test('user can disable two-factor authentication with correct password', function () {
    $user = User::factory()->create(['password' => bcrypt('MyPassword123!')]);
    $this->actingAs($user);
    $service = app(TwoFactorAuthenticationService::class);

    $this->postJson('/settings/two-factor/enable')->assertStatus(200);
    $user->refresh();
    $validCode = $service->getCurrentOtp($user->two_factor_secret);
    $this->postJson('/settings/two-factor/confirm', ['code' => $validCode])->assertStatus(200);

    $response = $this->postJson('/settings/two-factor/disable', ['password' => 'MyPassword123!']);

    $response->assertStatus(200);
    expect($user->fresh()->hasTwoFactorEnabled())->toBeFalse();
});

test('disabling two-factor with wrong password fails', function () {
    $user = User::factory()->create(['password' => bcrypt('MyPassword123!')]);
    $this->actingAs($user);
    $service = app(TwoFactorAuthenticationService::class);

    $this->postJson('/settings/two-factor/enable')->assertStatus(200);
    $user->refresh();
    $validCode = $service->getCurrentOtp($user->two_factor_secret);
    $this->postJson('/settings/two-factor/confirm', ['code' => $validCode])->assertStatus(200);

    $response = $this->postJson('/settings/two-factor/disable', ['password' => 'WrongPassword!']);

    $response->assertStatus(422);
    expect($user->fresh()->hasTwoFactorEnabled())->toBeTrue();
});

test('login with 2fa enabled requires a challenge before completing authentication', function () {
    $user = User::factory()->create(['password' => bcrypt('MyPassword123!')]);
    $this->actingAs($user);
    $service = app(TwoFactorAuthenticationService::class);
    $this->postJson('/settings/two-factor/enable')->assertStatus(200);
    $user->refresh();
    $validCode = $service->getCurrentOtp($user->two_factor_secret);
    $this->postJson('/settings/two-factor/confirm', ['code' => $validCode])->assertStatus(200);
    $this->post('/logout');

    $response = $this->post('/login', [
        'login' => $user->email,
        'password' => 'MyPassword123!',
    ]);

    $response->assertRedirect('/two-factor-challenge');
    $this->assertGuest();
});

test('user can complete login via the two-factor challenge with a valid totp code', function () {
    $user = User::factory()->create(['password' => bcrypt('MyPassword123!')]);
    $this->actingAs($user);
    $service = app(TwoFactorAuthenticationService::class);
    $this->postJson('/settings/two-factor/enable')->assertStatus(200);
    $user->refresh();
    $validCode = $service->getCurrentOtp($user->two_factor_secret);
    $this->postJson('/settings/two-factor/confirm', ['code' => $validCode])->assertStatus(200);
    $this->post('/logout');

    $this->post('/login', [
        'login' => $user->email,
        'password' => 'MyPassword123!',
    ])->assertRedirect('/two-factor-challenge');

    $freshCode = $service->getCurrentOtp($user->fresh()->two_factor_secret);

    $response = $this->post('/two-factor-challenge', ['code' => $freshCode]);

    $response->assertRedirect();
    $this->assertAuthenticatedAs($user);
});

test('user can complete login via the two-factor challenge with a recovery code', function () {
    $user = User::factory()->create(['password' => bcrypt('MyPassword123!')]);
    $this->actingAs($user);
    $service = app(TwoFactorAuthenticationService::class);
    $this->postJson('/settings/two-factor/enable')->assertStatus(200);
    $user->refresh();
    $validCode = $service->getCurrentOtp($user->two_factor_secret);
    $confirmResponse = $this->postJson('/settings/two-factor/confirm', ['code' => $validCode]);
    $recoveryCode = $confirmResponse->json('recovery_codes')[0];
    $this->post('/logout');

    $this->post('/login', [
        'login' => $user->email,
        'password' => 'MyPassword123!',
    ])->assertRedirect('/two-factor-challenge');

    $response = $this->post('/two-factor-challenge', ['code' => $recoveryCode]);

    $response->assertRedirect();
    $this->assertAuthenticatedAs($user);

    // Recovery code should be single-use
    expect($user->fresh()->two_factor_recovery_codes)->not->toContain($recoveryCode);
});

test('two-factor challenge fails with an invalid code', function () {
    $user = User::factory()->create(['password' => bcrypt('MyPassword123!')]);
    $this->actingAs($user);
    $service = app(TwoFactorAuthenticationService::class);
    $this->postJson('/settings/two-factor/enable')->assertStatus(200);
    $user->refresh();
    $validCode = $service->getCurrentOtp($user->two_factor_secret);
    $this->postJson('/settings/two-factor/confirm', ['code' => $validCode])->assertStatus(200);
    $this->post('/logout');

    $this->post('/login', [
        'login' => $user->email,
        'password' => 'MyPassword123!',
    ])->assertRedirect('/two-factor-challenge');

    $response = $this->post('/two-factor-challenge', ['code' => '000000']);

    $response->assertSessionHasErrors('code');
    $this->assertGuest();
});

test('login without 2fa enabled logs in immediately', function () {
    $user = User::factory()->create(['password' => bcrypt('MyPassword123!')]);

    $response = $this->post('/login', [
        'login' => $user->email,
        'password' => 'MyPassword123!',
    ]);

    $this->assertAuthenticatedAs($user);
});

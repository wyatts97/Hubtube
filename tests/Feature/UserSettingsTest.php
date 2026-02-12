<?php

use App\Models\User;

/*
|--------------------------------------------------------------------------
| User Settings — Profile, Password, Notifications, Privacy
|--------------------------------------------------------------------------
*/

test('guest cannot access settings page', function () {
    $this->get('/settings')->assertRedirect('/login');
});

test('authenticated user can access settings page', function () {
    asUser();
    $this->get('/settings')->assertStatus(200);
});

test('user can update profile', function () {
    $user = asUser();

    $response = $this->put('/settings/profile', [
        'username' => $user->username,
        'email' => $user->email,
        'bio' => 'Updated bio text',
    ]);

    $response->assertRedirect();
    expect($user->fresh()->bio)->toBe('Updated bio text');
});

test('user can update password', function () {
    $user = User::factory()->create(['password' => bcrypt('OldPassword123!')]);
    $this->actingAs($user);

    $response = $this->put('/settings/password', [
        'current_password' => 'OldPassword123!',
        'password' => 'NewPassword456!',
        'password_confirmation' => 'NewPassword456!',
    ]);

    $response->assertRedirect();
});

test('password update fails with wrong current password', function () {
    $user = User::factory()->create(['password' => bcrypt('OldPassword123!')]);
    $this->actingAs($user);

    $response = $this->put('/settings/password', [
        'current_password' => 'WrongPassword!',
        'password' => 'NewPassword456!',
        'password_confirmation' => 'NewPassword456!',
    ]);

    $response->assertSessionHasErrors();
});

test('guest cannot access dashboard', function () {
    $this->get('/dashboard')->assertRedirect('/login');
});

test('authenticated user can access dashboard', function () {
    asUser();
    $this->get('/dashboard')->assertStatus(200);
});

test('guest cannot access feed', function () {
    $this->get('/feed')->assertRedirect('/login');
});

test('authenticated user can access feed', function () {
    asUser();
    $this->get('/feed')->assertStatus(200);
});

test('guest cannot access history', function () {
    $this->get('/history')->assertRedirect('/login');
});

test('authenticated user can access history', function () {
    asUser();
    $this->get('/history')->assertStatus(200);
});

test('guest cannot access notifications', function () {
    $this->get('/notifications')->assertRedirect('/login');
});

test('authenticated user can access notifications', function () {
    asUser();
    $this->get('/notifications')->assertStatus(200);
});

test('guest cannot access wallet', function () {
    $this->get('/wallet')->assertRedirect('/login');
});

test('authenticated user can access wallet', function () {
    asUser();
    $this->get('/wallet')->assertStatus(200);
});

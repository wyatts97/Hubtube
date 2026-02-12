<?php

use App\Models\User;

/*
|--------------------------------------------------------------------------
| Authentication — Registration, Login, Logout, Password Reset
|--------------------------------------------------------------------------
*/

test('registration page loads for guests', function () {
    $this->get('/register')->assertStatus(200);
});

test('login page loads for guests', function () {
    $this->get('/login')->assertStatus(200);
});

test('user can register with valid data', function () {
    $response = $this->post('/register', [
        'username' => 'testuser',
        'email' => 'test@example.com',
        'password' => 'Password123!',
        'password_confirmation' => 'Password123!',
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('users', ['email' => 'test@example.com']);
});

test('registration fails with duplicate email', function () {
    User::factory()->create(['email' => 'taken@example.com']);

    $response = $this->post('/register', [
        'username' => 'newuser',
        'email' => 'taken@example.com',
        'password' => 'Password123!',
        'password_confirmation' => 'Password123!',
    ]);

    $response->assertSessionHasErrors('email');
});

test('registration fails with duplicate username', function () {
    User::factory()->create(['username' => 'takenname']);

    $response = $this->post('/register', [
        'username' => 'takenname',
        'email' => 'new@example.com',
        'password' => 'Password123!',
        'password_confirmation' => 'Password123!',
    ]);

    $response->assertSessionHasErrors('username');
});

test('user can login with valid credentials', function () {
    $user = User::factory()->create([
        'email' => 'login@example.com',
        'password' => bcrypt('Password123!'),
    ]);

    $response = $this->post('/login', [
        'email' => 'login@example.com',
        'password' => 'Password123!',
    ]);

    $response->assertRedirect();
    $this->assertAuthenticatedAs($user);
});

test('login fails with wrong password', function () {
    User::factory()->create([
        'email' => 'login@example.com',
        'password' => bcrypt('Password123!'),
    ]);

    $response = $this->post('/login', [
        'email' => 'login@example.com',
        'password' => 'WrongPassword!',
    ]);

    $this->assertGuest();
});

test('authenticated user can logout', function () {
    $user = asUser();

    $response = $this->post('/logout');

    $response->assertRedirect();
    $this->assertGuest();
});

test('forgot password page loads', function () {
    $this->get('/forgot-password')->assertStatus(200);
});

test('authenticated users cannot access login page', function () {
    asUser();
    $this->get('/login')->assertRedirect();
});

test('authenticated users cannot access register page', function () {
    asUser();
    $this->get('/register')->assertRedirect();
});

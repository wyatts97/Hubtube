<?php

use App\Models\User;

/*
|--------------------------------------------------------------------------
| Admin Panel — Access Control
|--------------------------------------------------------------------------
*/

test('guest cannot access admin panel', function () {
    $this->get('/admin')->assertRedirect();
});

test('regular user cannot access admin panel', function () {
    asUser();
    $this->get('/admin')->assertStatus(403);
});

test('admin user can access admin panel', function () {
    asAdmin();
    $this->get('/admin')->assertStatus(200);
});

test('guest cannot access horizon dashboard', function () {
    $this->get('/horizon')->assertRedirect();
});

test('expired session on Livewire request returns graceful 401', function () {
    $response = $this->withHeaders([
        'X-Livewire' => 'true',
    ])->post('/admin/livewire/update', ['foo' => 'bar']);

    $response->assertStatus(401)
        ->assertJson([
            'message' => 'Your session has expired. Please refresh the page and log in again.',
            'refresh' => true,
        ])
        ->assertHeader('X-Refresh-Required', 'true');
});

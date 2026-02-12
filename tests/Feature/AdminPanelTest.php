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

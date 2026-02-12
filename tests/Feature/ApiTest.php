<?php

use App\Models\User;

/*
|--------------------------------------------------------------------------
| API Endpoints — Sanctum Auth, JSON Responses
|--------------------------------------------------------------------------
*/

test('unauthenticated API user request returns 401', function () {
    $response = $this->getJson('/api/user');
    $response->assertStatus(401);
});

test('authenticated API user returns user data', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user, 'sanctum')->getJson('/api/user');

    $response->assertStatus(200);
    $response->assertJsonStructure([
        'id',
        'username',
        'email',
        'avatar',
        'is_verified',
        'is_pro',
    ]);
    $response->assertJson(['id' => $user->id]);
});

test('API search endpoint is publicly accessible', function () {
    $response = $this->getJson('/api/search?q=test');
    $response->assertStatus(200);
});

test('API wallet transactions require auth', function () {
    $response = $this->getJson('/api/wallet/transactions');
    $response->assertStatus(401);
});

test('translation API returns languages', function () {
    $response = $this->getJson('/api/languages');
    $response->assertStatus(200);
});

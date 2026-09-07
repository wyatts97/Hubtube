<?php

use App\Models\Setting;
use App\Models\User;

/*
|--------------------------------------------------------------------------
| Theme preference endpoint
|--------------------------------------------------------------------------
|
| /api/theme mirrors a signed-in user's light/dark choice onto their account.
| It returns plain JSON, so it must be called with a normal fetch — calling it
| through Inertia's router raised "All Inertia requests must receive a valid
| Inertia response" at the visitor during a background sync. These pin the
| contract the client depends on.
|
*/

it('stores a signed-in user theme choice and returns plain json', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->postJson('/api/theme', ['theme' => 'light']);

    $response->assertOk()->assertExactJson(['saved' => true]);

    // Explicitly not an Inertia response — that is the whole point.
    expect($response->headers->get('x-inertia'))->toBeNull();

    expect($user->fresh()->settings['theme'])->toBe('light');
});

it('rejects a theme value that is not light or dark', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson('/api/theme', ['theme' => 'chartreuse'])
        ->assertStatus(422);
});

it('ignores the choice when the admin has pinned the site to one theme', function () {
    Setting::set('theme_mode', 'dark', 'theme', 'string');

    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson('/api/theme', ['theme' => 'light'])
        ->assertOk()
        ->assertExactJson(['saved' => false]);

    expect($user->fresh()->settings['theme'] ?? null)->toBeNull();
});

it('does not fail for a guest', function () {
    $this->postJson('/api/theme', ['theme' => 'dark'])
        ->assertOk()
        ->assertExactJson(['saved' => false]);
});

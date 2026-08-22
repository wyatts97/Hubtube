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
    // Hits a real Filament panel route so AuthenticateFilament actually runs.
    // The old target, POST /admin/livewire/update, was never a registered route
    // under Livewire 4 — it namespaces its endpoint as /livewire-<hash>/update —
    // so the request 404'd before reaching any middleware.
    $response = $this->withHeaders([
        'X-Livewire' => 'true',
    ])->get('/admin');

    $response->assertStatus(401)
        ->assertJson([
            'message' => 'Your session has expired. Please refresh the page and log in again.',
            'refresh' => true,
        ])
        ->assertHeader('X-Refresh-Required', 'true');
});

test('SEO settings page renders with every setting the code reads', function () {
    asAdmin();

    $this->get(App\Filament\Pages\SeoSettings::getUrl())->assertStatus(200);

    // Spot-check keys that previously had no admin UI at all, so a schema that
    // silently drops them fails here rather than in production.
    $state = Livewire\Livewire::test(App\Filament\Pages\SeoSettings::class)->get('data');

    expect($state)->toHaveKeys([
        'seo_videos_index_title',
        'seo_shorts_title',
        'seo_sitemap_chunk_size',
        'seo_sitemap_max_images',
        'seo_sitemap_playlists_enabled',
    ]);
});

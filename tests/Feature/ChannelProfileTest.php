<?php

use App\Models\User;

/*
|--------------------------------------------------------------------------
| Channel profile payload
|--------------------------------------------------------------------------
|
| ChannelController used to hand Inertia the whole User model, and User's
| $hidden only covers password and the two-factor columns. Every public
| channel page therefore shipped the creator's email, wallet balance, points
| balance and privacy settings to anonymous visitors.
|
| ChannelProfileResource is an explicit allowlist now. These tests are the
| guard that keeps it one.
|
*/

/**
 * Fields that must never reach a public channel page.
 */
const LEAKY_CHANNEL_FIELDS = [
    'email',
    'settings',
    'wallet_balance',
    'points_balance',
    'is_admin',
    'password',
    'country',
    'gender',
    'pro_expires_at',
    'pro_source',
    'stripe_id',
    'two_factor_secret',
];

test('channel payload does not leak private user fields to guests', function () {
    $user = User::factory()->create([
        'email' => 'private@example.test',
        'settings' => ['show_liked_videos' => true],
    ]);

    $this->get("/channel/{$user->username}")
        ->assertOk()
        ->assertInertia(function ($page) {
            $channel = $page->toArray()['props']['channel'];

            foreach (LEAKY_CHANNEL_FIELDS as $field) {
                expect($channel)->not->toHaveKey($field);
            }
        });
});

test('channel payload does not leak private fields on any tab', function () {
    $user = User::factory()->create([
        'settings' => ['show_liked_videos' => true, 'show_watch_history' => true],
    ]);

    $paths = ['', '/videos', '/playlists', '/liked', '/history', '/about'];

    foreach ($paths as $path) {
        $this->get("/channel/{$user->username}{$path}")
            ->assertOk()
            ->assertInertia(function ($page) use ($path) {
                $channel = $page->toArray()['props']['channel'];

                foreach (LEAKY_CHANNEL_FIELDS as $field) {
                    expect($channel)
                        ->not->toHaveKey($field, "leaked {$field} on tab '{$path}'");
                }
            });
    }
});

test('channel payload exposes the fields the page actually renders', function () {
    $user = User::factory()->create();
    $user->channel()->update(['description' => 'A channel description.']);
    App\Models\Video::factory()->create(['user_id' => $user->id, 'views_count' => 1234]);

    $this->get("/channel/{$user->username}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('channel.username', $user->username)
            ->where('channel.description', 'A channel description.')
            ->where('channel.stats.views', 1234)
            ->has('channel.avatar_url')
            ->has('channel.stats.subscribers')
            ->has('channel.stats.videos')
            ->etc());
});

test('every tab reports the same video count', function () {
    $user = User::factory()->create();

    $counts = collect(['', '/playlists', '/about'])->map(function ($path) use ($user) {
        $response = $this->get("/channel/{$user->username}{$path}")->assertOk();

        return $response->viewData('page')['props']['channel']['stats']['videos'];
    });

    expect($counts->unique())->toHaveCount(1);
});

test('each tab marks itself active', function () {
    $user = User::factory()->create([
        'settings' => ['show_liked_videos' => true, 'show_watch_history' => true],
    ]);

    $expected = [
        '' => 'videos',
        '/videos' => 'videos',
        '/playlists' => 'playlists',
        '/liked' => 'liked',
        '/history' => 'history',
        '/about' => 'about',
    ];

    foreach ($expected as $path => $tab) {
        $this->get("/channel/{$user->username}{$path}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('activeTab', $tab)->etc());
    }
});

test('every tab receives seo metadata, not just the landing tab', function () {
    $user = User::factory()->create();

    foreach (['', '/playlists', '/about'] as $path) {
        $this->get("/channel/{$user->username}{$path}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('seo.canonical')->etc());
    }
});

test('liked and history tabs stay hidden when the owner has not opted in', function () {
    $user = User::factory()->create(['settings' => []]);

    $this->get("/channel/{$user->username}/liked")->assertNotFound();
    $this->get("/channel/{$user->username}/history")->assertNotFound();
});

test('the owner can always see their own liked and history tabs', function () {
    $user = User::factory()->create(['settings' => []]);
    asUser($user);

    $this->get("/channel/{$user->username}/liked")->assertOk();
    $this->get("/channel/{$user->username}/history")->assertOk();
});

/*
| channels.total_views is a denormalised counter with no writer anywhere in
| the app — Channel::incrementViews() has no callers, so it reads 0 for every
| channel regardless of traffic. The stat is derived from the videos instead.
*/

test('total views reflects the channel videos, not the dead counter', function () {
    $user = User::factory()->create();

    App\Models\Video::factory()->count(3)->create([
        'user_id' => $user->id,
        'views_count' => 1_000_000,
    ]);

    // The stored counter stays at its default; the stat must not read it.
    expect($user->channel->total_views)->toBe(0);

    $this->get("/channel/{$user->username}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('channel.stats.views', 3_000_000)
            ->etc());
});

test('total views excludes private and unapproved videos', function () {
    $user = User::factory()->create();

    App\Models\Video::factory()->create(['user_id' => $user->id, 'views_count' => 500]);
    App\Models\Video::factory()->private()->create(['user_id' => $user->id, 'views_count' => 900]);
    App\Models\Video::factory()->unapproved()->create(['user_id' => $user->id, 'views_count' => 900]);

    $this->get("/channel/{$user->username}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('channel.stats.views', 500)->etc());
});

/*
| Phase 3: channels.description is canonical. Settings used to edit users.bio
| while the channel page rendered channels.description, so editing your bio
| changed nothing a visitor could see.
*/

test('editing the profile bio updates what the channel page shows', function () {
    $user = asUser();

    $this->put('/settings/profile', [
        'username' => $user->username,
        'email' => $user->email,
        'bio' => 'Updated from settings.',
    ])->assertRedirect();

    expect($user->fresh()->channel->description)->toBe('Updated from settings.');

    $this->get("/channel/{$user->username}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('channel.description', 'Updated from settings.')
            ->etc());
});

test('the verified badge follows the user flag', function () {
    $user = User::factory()->create();

    $this->get("/channel/{$user->username}")
        ->assertInertia(fn ($page) => $page->where('channel.is_verified', false)->etc());

    $user->forceFill(['is_verified' => true])->save();

    $this->get("/channel/{$user->username}")
        ->assertInertia(fn ($page) => $page->where('channel.is_verified', true)->etc());
});

<?php

use App\Models\Setting;
use App\Models\User;

/*
|--------------------------------------------------------------------------
| Private profiles
|--------------------------------------------------------------------------
|
| private_profile was accepted and persisted long before this, with shipped
| copy promising "only approved followers can see your content" — but nothing
| ever read it and there was no UI toggle. It is now an admin-enabled feature
| that actually hides the channel.
|
*/

function enablePrivateProfiles(bool $on = true): void
{
    Setting::set('private_profiles_enabled', $on ? '1' : '0');
    Setting::clearCache();
}

function privateUser(): User
{
    return User::factory()->create(['settings' => ['private_profile' => true]]);
}

test('a private channel is hidden from guests', function () {
    enablePrivateProfiles();
    $user = privateUser();

    $this->get("/channel/{$user->username}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Channel/Private'));
});

test('a private channel is hidden on every tab', function () {
    enablePrivateProfiles();
    $user = privateUser();

    foreach (['', '/videos', '/playlists', '/about'] as $path) {
        $this->get("/channel/{$user->username}{$path}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Channel/Private'));
    }
});

test('the owner and admins still see a private channel', function () {
    enablePrivateProfiles();
    $user = privateUser();

    asUser($user);
    $this->get("/channel/{$user->username}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Channel/Show'));

    asAdmin();
    $this->get("/channel/{$user->username}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Channel/Show'));
});

test('a private channel leaks no content in the payload', function () {
    enablePrivateProfiles();
    $user = privateUser();
    App\Models\Video::factory()->create(['user_id' => $user->id, 'title' => 'Secret Video']);

    $this->get("/channel/{$user->username}")
        ->assertOk()
        ->assertDontSee('Secret Video')
        ->assertInertia(fn ($page) => $page->missing('videos')->etc());
});

test('disabling the feature site-wide makes private channels visible again', function () {
    enablePrivateProfiles();
    $user = privateUser();

    $this->get("/channel/{$user->username}")
        ->assertInertia(fn ($page) => $page->component('Channel/Private'));

    enablePrivateProfiles(false);

    // The saved preference is untouched, so re-enabling restores their choice.
    $this->get("/channel/{$user->username}")
        ->assertInertia(fn ($page) => $page->component('Channel/Show'));

    expect($user->fresh()->settings['private_profile'])->toBeTrue();
});

test('the private toggle cannot be set while the feature is disabled', function () {
    enablePrivateProfiles(false);
    $user = asUser();

    $this->put('/settings/privacy', [
        'private_profile' => true,
        'show_watch_history' => true,
        'show_liked_videos' => true,
        'allow_comments' => true,
    ])->assertRedirect();

    expect($user->fresh()->settings['private_profile'] ?? false)->toBeFalse();
});

test('the private toggle can be set once an admin enables the feature', function () {
    enablePrivateProfiles();
    $user = asUser();

    $this->put('/settings/privacy', [
        'private_profile' => true,
        'show_watch_history' => true,
        'show_liked_videos' => true,
        'allow_comments' => true,
    ])->assertRedirect();

    expect($user->fresh()->settings['private_profile'])->toBeTrue();
});

test('the settings page only advertises the toggle when enabled', function () {
    asUser();

    enablePrivateProfiles(false);
    $this->get('/settings')
        ->assertInertia(fn ($page) => $page->where('privateProfilesEnabled', false)->etc());

    enablePrivateProfiles();
    $this->get('/settings')
        ->assertInertia(fn ($page) => $page->where('privateProfilesEnabled', true)->etc());
});

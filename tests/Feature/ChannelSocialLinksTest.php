<?php

use App\Models\Setting;
use App\Models\User;

/*
|--------------------------------------------------------------------------
| Channel social links
|--------------------------------------------------------------------------
|
| Profile links are the main spam and XSS vector on a tube site, so the
| rejection cases below are the point of the feature, not an afterthought.
|
*/

function postLinks(array $links)
{
    return test()->put('/settings/social-links', ['social_links' => $links]);
}

test('a creator can save a valid link', function () {
    $user = asUser(User::factory()->create(['email_verified_at' => now()]));

    postLinks([['platform' => 'twitter', 'url' => 'https://x.com/someone']])
        ->assertRedirect();

    expect($user->fresh()->channel->social_links)->toBe([
        ['platform' => 'twitter', 'label' => null, 'url' => 'https://x.com/someone'],
    ]);
});

test('javascript and data URLs are rejected', function (string $url) {
    asUser(User::factory()->create(['email_verified_at' => now()]));

    postLinks([['platform' => 'website', 'url' => $url]])
        ->assertSessionHasErrors('social_links.0.url');
})->with([
    'javascript:alert(1)',
    'JavaScript:alert(1)',
    'data:text/html;base64,PHNjcmlwdD4=',
    'vbscript:msgbox(1)',
    'file:///etc/passwd',
]);

test('a URL with embedded credentials is rejected', function () {
    asUser(User::factory()->create(['email_verified_at' => now()]));

    // Everything before the @ is userinfo — the real host is evil.example.
    postLinks([['platform' => 'website', 'url' => 'https://onlyfans.com@evil.example/x']])
        ->assertSessionHasErrors('social_links.0.url');
});

test('a bare IP host is rejected', function () {
    asUser(User::factory()->create(['email_verified_at' => now()]));

    postLinks([['platform' => 'website', 'url' => 'http://127.0.0.1/admin']])
        ->assertSessionHasErrors('social_links.0.url');
});

test('a punycode host is rejected', function () {
    asUser(User::factory()->create(['email_verified_at' => now()]));

    postLinks([['platform' => 'website', 'url' => 'https://xn--80ak6aa92e.com']])
        ->assertSessionHasErrors('social_links.0.url');
});

test('a link must actually point at the platform it claims', function () {
    asUser(User::factory()->create(['email_verified_at' => now()]));

    postLinks([['platform' => 'onlyfans', 'url' => 'https://not-onlyfans.example/me']])
        ->assertSessionHasErrors('social_links.0.url');
});

test('an unsupported platform is rejected', function () {
    asUser(User::factory()->create(['email_verified_at' => now()]));

    postLinks([['platform' => 'myspace', 'url' => 'https://myspace.com/me']])
        ->assertSessionHasErrors('social_links.0.platform');
});

test('links are capped and duplicates collapse', function () {
    $user = asUser(User::factory()->create(['email_verified_at' => now()]));

    postLinks([
        ['platform' => 'twitter', 'url' => 'https://x.com/a'],
        ['platform' => 'twitter', 'url' => 'https://x.com/a'],
    ])->assertRedirect();

    expect($user->fresh()->channel->social_links)->toHaveCount(1);

    postLinks(array_fill(0, 9, ['platform' => 'website', 'url' => 'https://example.com']))
        ->assertSessionHasErrors('social_links');
});

test('a custom label is kept for website links but ignored for known platforms', function () {
    $user = asUser(User::factory()->create(['email_verified_at' => now()]));

    postLinks([
        ['platform' => 'website', 'url' => 'https://example.com', 'label' => 'My site'],
        // A known platform must not be able to relabel itself, or a link can
        // read "Twitter" while pointing somewhere else.
        ['platform' => 'twitter', 'url' => 'https://x.com/a', 'label' => 'Totally Safe'],
    ])->assertRedirect();

    $saved = $user->fresh()->channel->social_links;

    expect($saved[0]['label'])->toBe('My site');
    expect($saved[1]['label'])->toBeNull();
});

test('links are hidden on the channel page until the email is verified', function () {
    $user = User::factory()->create(['email_verified_at' => null]);
    $user->ensureChannel()->update([
        'social_links' => [['platform' => 'twitter', 'label' => null, 'url' => 'https://x.com/a']],
    ]);

    $this->get("/channel/{$user->username}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('channel.social_links', [])->etc());

    $user->forceFill(['email_verified_at' => now()])->save();

    $this->get("/channel/{$user->username}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('channel.social_links', 1)->etc());
});

test('the site-wide kill switch hides links without deleting them', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);
    $user->ensureChannel()->update([
        'social_links' => [['platform' => 'twitter', 'label' => null, 'url' => 'https://x.com/a']],
    ]);

    Setting::set('channel_social_links_enabled', '0');
    Setting::clearCache();

    $this->get("/channel/{$user->username}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('channel.social_links', [])->etc());

    expect($user->fresh()->channel->social_links)->toHaveCount(1);
});

test('a stored link whose host is no longer allowed stops rendering', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);

    // Saved while the host was permitted, then the allowlist changed.
    $user->ensureChannel()->update([
        'social_links' => [['platform' => 'twitter', 'label' => null, 'url' => 'https://old-twitter.example/a']],
    ]);

    $this->get("/channel/{$user->username}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('channel.social_links', [])->etc());
});

test('validated links are published as schema.org sameAs', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);
    $user->ensureChannel()->update([
        'social_links' => [['platform' => 'twitter', 'label' => null, 'url' => 'https://x.com/a']],
    ]);

    $this->get("/channel/{$user->username}")
        ->assertOk()
        ->assertSee('"sameAs"', false)
        ->assertSee('https://x.com/a', false);
});

test('guests cannot update links', function () {
    postLinks([['platform' => 'twitter', 'url' => 'https://x.com/a']])
        ->assertRedirect('/login');
});

test('the settings page exposes the platform list to the form', function () {
    asUser();

    $this->get('/settings')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('socialPlatforms')
            ->has('socialLinksEnabled')
            ->etc());
});

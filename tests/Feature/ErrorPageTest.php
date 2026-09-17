<?php

use App\Http\Middleware\HandleInertiaRequests;
use App\Models\Setting;
use App\Models\User;
use App\Models\Video;

/*
|--------------------------------------------------------------------------
| The SPA error page
|--------------------------------------------------------------------------
|
| Error pages are rendered from the exception handler, which usually runs
| before HandleInertiaRequests has shared anything: a failed route-model
| binding is thrown by SubstituteBindings, which sits ahead of it, and an
| unmatched URL never reaches the web group at all. That left the page with no
| translation catalogue (every label rendered as its raw dot-path) and no theme
| (the site's name and logo fell back to the built-in default).
|
*/

test('a 404 page carries the translation catalogue', function () {
    $page = inertiaPagePayload($this->get('/no-such-page-at-all'));

    expect($page['component'])->toBe('Error')
        ->and($page['props']['locale']['translations'])->not->toBeEmpty()
        ->and($page['props']['locale']['translations']['nav']['home'])->not->toBeEmpty();
});

test('a 404 page renders no raw translation keys', function () {
    $html = $this->get('/no-such-page-at-all')->getContent();

    expect($html)->not->toContain('nav.home')
        ->not->toContain('common.search_placeholder')
        ->not->toContain('errors.go_home')
        ->not->toContain('auth.login');
});

test('a 404 page carries the theme, so the site keeps its own name', function () {
    // site_title is the name in the header; site_name is the one in meta
    // titles. They are separate settings on purpose.
    Setting::set('site_title', 'WedgieTube', 'general', 'string');
    Setting::set('site_name', 'WedgieTube', 'general', 'string');

    $page = inertiaPagePayload($this->get('/no-such-page-at-all'));

    expect($page['props']['theme'])->not->toBeEmpty()
        ->and($page['props']['theme']['siteTitle'])->toBe('WedgieTube')
        ->and($page['props']['seo']['title'])->toContain('WedgieTube');
});

test('the error page never prints the internal model-binding message', function () {
    // Laravel's text for this is "No query results for model
    // [App\Models\Video] <id>", which names an internal class.
    $response = $this->get('/definitely-not-a-real-slug');
    $page = inertiaPagePayload($response);

    expect($response->status())->toBe(404)
        ->and($page['props']['message'])->toBeNull();

    expect($response->getContent())->not->toContain('App\Models\Video')
        ->not->toContain('No query results');
});

test('a message the application wrote deliberately is still shown', function () {
    $video = Video::factory()->create(['geo_blocked_countries' => ['DE']]);

    $page = inertiaPagePayload($this->get("/{$video->slug}", ['CF-IPCountry' => 'DE']));

    expect($page['props']['status'])->toBe(451)
        ->and($page['props']['message'])->toBe('This video is not available in your country.');
});

test('the error page knows who is signed in', function () {
    $user = asUser(User::factory()->create(['username' => 'ada']));

    $page = inertiaPagePayload($this->get('/no-such-page-at-all'));

    expect($page['props']['auth']['user']['username'])->toBe('ada');
});

test('403 and 451 pages are rendered by the same page, with their own status', function () {
    $private = Video::factory()->private()->create();

    $response = $this->get("/{$private->slug}");
    $page = inertiaPagePayload($response);

    expect($response->status())->toBe(403)
        ->and($page['component'])->toBe('Error')
        ->and($page['props']['status'])->toBe(403);
});

test('the session-free share subset still supplies translations and theme', function () {
    // This is the fallback used when there is no session to read a user or a
    // CSRF token from, so it must not touch either.
    $shared = app(HandleInertiaRequests::class)->shareForErrorPage(request());

    expect($shared['auth'])->toBe(['user' => null])
        ->and($shared['csrf_token'])->toBe('')
        ->and(($shared['locale'])()['translations'])->not->toBeEmpty()
        ->and(($shared['theme'])())->not->toBeEmpty();
});

test('the error page is noindex', function () {
    $page = inertiaPagePayload($this->get('/no-such-page-at-all'));

    expect($page['props']['seo']['robots'])->toBe('noindex, nofollow');
});

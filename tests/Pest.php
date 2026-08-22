<?php

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
*/

uses(Tests\TestCase::class)->in('Feature', 'Unit');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
*/

/**
 * Create and authenticate a regular user.
 */
function asUser(?App\Models\User $user = null): App\Models\User
{
    $user ??= App\Models\User::factory()->create();
    test()->actingAs($user);
    return $user;
}

/**
 * Create and authenticate an admin user.
 */
function asAdmin(?App\Models\User $user = null): App\Models\User
{
    $user ??= App\Models\User::factory()->admin()->create();
    test()->actingAs($user);
    return $user;
}

/**
 * Turn on multi-language mode for the given locales.
 *
 * getEnabledLocales() short-circuits to the default locale unless
 * translation_enabled is truthy, so all three settings must be written.
 */
function enableLocales(array $locales, string $default = 'en'): void
{
    App\Models\Setting::set('translation_enabled', '1', 'language');
    App\Models\Setting::set('default_language', $default, 'language');
    App\Models\Setting::set('enabled_languages', $locales, 'language');
    App\Models\Setting::clearCache();
}

/**
 * Extract the rel="alternate" hreflang links from a rendered page as
 * [hreflangCode => href], with the app URL stripped for readable assertions.
 */
function hreflangLinks(Illuminate\Testing\TestResponse $response): array
{
    preg_match_all(
        '/<link rel="alternate" hreflang="([^"]+)" href="([^"]*)"/',
        $response->getContent(),
        $matches,
        PREG_SET_ORDER
    );

    return collect($matches)
        ->mapWithKeys(fn ($m) => [$m[1] => str_replace(url('/'), '', $m[2]) ?: '/'])
        ->all();
}

/**
 * Decode the Inertia page object from a rendered HTML response.
 *
 * Prefer $response->assertInertia(). Use this only where that helper can't
 * read the response — it depends on $response->original still being the View,
 * which a few routes lose — since this reads the same data-page payload the
 * browser consumes.
 */
function inertiaPagePayload(Illuminate\Testing\TestResponse $response): array
{
    expect($response->getContent())->toMatch('/data-page="/');

    preg_match('/data-page="([^"]*)"/', $response->getContent(), $matches);

    $page = json_decode(html_entity_decode($matches[1], ENT_QUOTES), true);

    expect($page)->toBeArray()->toHaveKeys(['component', 'props']);

    return $page;
}

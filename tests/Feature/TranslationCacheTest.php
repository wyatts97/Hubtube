<?php

use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Support\Facades\Cache;

/*
| The UI catalogue used to be cached with a plain rememberForever(), so any
| release that edited resources/js/i18n/*.json served the stale copy and every
| new key rendered to users as its raw dot-path until someone remembered to run
| translations:clear-cache. The key is versioned by mtime now.
*/

test('the catalogue cache key changes when the file changes', function () {
    $file = resource_path('js/i18n/en.json');
    $before = HandleInertiaRequests::uiTranslationCacheKey('en');

    $original = file_get_contents($file);

    try {
        touch($file, time() + 60);
        clearstatcache(true, $file);

        expect(HandleInertiaRequests::uiTranslationCacheKey('en'))->not->toBe($before);
    } finally {
        file_put_contents($file, $original);
        clearstatcache(true, $file);
    }
});

test('an edited catalogue is served without clearing the cache by hand', function () {
    $file = resource_path('js/i18n/en.json');
    $original = file_get_contents($file);

    try {
        // Warm the cache with the catalogue as it stands.
        asUser();
        $this->get('/settings')->assertOk();

        $catalogue = json_decode($original, true);
        $catalogue['common']['__probe_key'] = 'Probe value';
        file_put_contents($file, json_encode($catalogue, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        touch($file, time() + 60);
        clearstatcache(true, $file);

        $this->get('/settings')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('locale.translations.common.__probe_key', 'Probe value')
                ->etc());
    } finally {
        file_put_contents($file, $original);
        clearstatcache(true, $file);
        Cache::forget(HandleInertiaRequests::uiTranslationCacheKey('en'));
    }
});

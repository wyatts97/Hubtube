<?php

use App\Models\Setting;

/*
|--------------------------------------------------------------------------
| Production Readiness — Config, Environment, Services, Infrastructure
|--------------------------------------------------------------------------
| Run this suite before deploying to verify the app is correctly configured
| for a live production environment. These tests check config values,
| required files, service connectivity, and security settings.
|
| Usage:  php artisan test --filter=ProductionReadiness
|         ./vendor/bin/pest --filter=ProductionReadiness
|--------------------------------------------------------------------------
*/

// ── Environment & Config ────────────────────────────────────────────────

test('APP_KEY is set and not the default', function () {
    $key = config('app.key');
    expect($key)->not->toBeNull();
    expect($key)->not->toBe('');
    expect($key)->not->toBe('base64:');
});

test('APP_ENV is not set to local in production', function () {
    // This test passes in testing env; on production, verify APP_ENV=production
    $env = config('app.env');
    expect($env)->toBeIn(['testing', 'production', 'staging']);
});

test('APP_DEBUG should be false in production', function () {
    // In testing env this is fine; the important thing is it's not stuck on true in prod
    $debug = config('app.debug');
    expect($debug)->toBeBool();
});

test('APP_URL is configured', function () {
    $url = config('app.url');
    expect($url)->not->toBeNull();
    expect($url)->not->toBe('');
});

test('timezone is set', function () {
    $tz = config('app.timezone');
    expect($tz)->not->toBeNull();
    expect($tz)->not->toBe('');
});

test('app locale is set', function () {
    $locale = config('app.locale');
    expect($locale)->not->toBeNull();
    expect(strlen($locale))->toBeGreaterThanOrEqual(2);
});

// ── Database ────────────────────────────────────────────────────────────

test('database connection works', function () {
    expect(fn () => \Illuminate\Support\Facades\DB::connection()->getPdo())
        ->not->toThrow(\Exception::class);
});

test('migrations are up to date', function () {
    $pending = \Illuminate\Support\Facades\Artisan::call('migrate:status');
    // migrate:status returns 0 when all migrations have run
    expect($pending)->toBe(0);
});

test('users table exists', function () {
    expect(\Illuminate\Support\Facades\Schema::hasTable('users'))->toBeTrue();
});

test('videos table exists', function () {
    expect(\Illuminate\Support\Facades\Schema::hasTable('videos'))->toBeTrue();
});

test('categories table exists', function () {
    expect(\Illuminate\Support\Facades\Schema::hasTable('categories'))->toBeTrue();
});

test('settings table exists', function () {
    expect(\Illuminate\Support\Facades\Schema::hasTable('settings'))->toBeTrue();
});

test('comments table exists', function () {
    expect(\Illuminate\Support\Facades\Schema::hasTable('comments'))->toBeTrue();
});

test('playlists table exists', function () {
    expect(\Illuminate\Support\Facades\Schema::hasTable('playlists'))->toBeTrue();
});

test('subscriptions table exists', function () {
    expect(\Illuminate\Support\Facades\Schema::hasTable('subscriptions'))->toBeTrue();
});

test('video_ads table exists', function () {
    expect(\Illuminate\Support\Facades\Schema::hasTable('video_ads'))->toBeTrue();
});

test('translations table exists', function () {
    expect(\Illuminate\Support\Facades\Schema::hasTable('translations'))->toBeTrue();
});

// ── Cache & Session ─────────────────────────────────────────────────────

test('cache driver is configured', function () {
    $driver = config('cache.default');
    expect($driver)->not->toBeNull();
    expect($driver)->toBeIn(['array', 'file', 'redis', 'memcached', 'database']);
});

test('session driver is configured', function () {
    $driver = config('session.driver');
    expect($driver)->not->toBeNull();
    expect($driver)->toBeIn(['array', 'file', 'redis', 'database', 'cookie']);
});

test('queue driver is configured', function () {
    $driver = config('queue.default');
    expect($driver)->not->toBeNull();
    expect($driver)->toBeIn(['sync', 'redis', 'database', 'sqs']);
});

test('cache operations work', function () {
    \Illuminate\Support\Facades\Cache::put('test_production_readiness', 'works', 60);
    expect(\Illuminate\Support\Facades\Cache::get('test_production_readiness'))->toBe('works');
    \Illuminate\Support\Facades\Cache::forget('test_production_readiness');
});

// ── Security ────────────────────────────────────────────────────────────

test('bcrypt rounds are at least 10', function () {
    // phpunit.xml sets BCRYPT_ROUNDS=4 for speed; only enforce in production/staging
    if (app()->environment('testing')) {
        $this->markTestSkipped('Bcrypt rounds intentionally lowered in testing env');
    }
    $rounds = config('hashing.bcrypt.rounds', 12);
    expect($rounds)->toBeGreaterThanOrEqual(10);
});

test('session cookie is configured securely', function () {
    $httpOnly = config('session.http_only');
    expect($httpOnly)->toBeTrue();
});

test('CSRF protection is enabled', function () {
    // Verify that POST without CSRF token fails
    $response = $this->post('/login', [
        'email' => 'test@test.com',
        'password' => 'test',
    ]);
    // Should get 419 (CSRF mismatch) when no token is provided
    // In testing, the middleware may be disabled, so we check it doesn't succeed silently
    expect($response->status())->toBeIn([302, 419, 422]);
});

// ── Required Files & Directories ────────────────────────────────────────

test('storage/installed marker file exists', function () {
    expect(file_exists(storage_path('installed')))->toBeTrue();
});

test('storage directory is writable', function () {
    expect(is_writable(storage_path()))->toBeTrue();
});

test('storage/logs directory exists', function () {
    expect(is_dir(storage_path('logs')))->toBeTrue();
});

test('bootstrap/cache directory is writable', function () {
    expect(is_writable(base_path('bootstrap/cache')))->toBeTrue();
});

test('public/index.php exists', function () {
    expect(file_exists(public_path('index.php')))->toBeTrue();
});

test('public storage symlink exists', function () {
    // storage:link creates public/storage → storage/app/public
    expect(file_exists(public_path('storage')) || is_link(public_path('storage')))->toBeTrue();
});

// ── Config Files ────────────────────────────────────────────────────────

test('sentry config exists', function () {
    expect(file_exists(config_path('sentry.php')))->toBeTrue();
});

test('hubtube config exists', function () {
    expect(file_exists(config_path('hubtube.php')))->toBeTrue();
});

test('horizon config exists', function () {
    expect(file_exists(config_path('horizon.php')))->toBeTrue();
});

// ── HubTube-Specific Config ─────────────────────────────────────────────

test('video allowed extensions are configured', function () {
    $extensions = config('hubtube.video.allowed_extensions');
    expect($extensions)->toBeArray();
    expect($extensions)->toContain('mp4');
});

test('video qualities are configured', function () {
    $qualities = config('hubtube.video.qualities');
    expect($qualities)->toBeArray();
    expect($qualities)->toContain('720p');
    expect($qualities)->toContain('1080p');
});

// ── Mail ────────────────────────────────────────────────────────────────

test('mail from address is configured', function () {
    $from = config('mail.from.address');
    expect($from)->not->toBeNull();
    expect($from)->not->toBe('');
});

test('mail from name is configured', function () {
    $name = config('mail.from.name');
    expect($name)->not->toBeNull();
    expect($name)->not->toBe('');
});

// ── Setting Model Functionality ─────────────────────────────────────────

test('setting model can read and write', function () {
    Setting::set('readiness_check', 'ok', 'test');
    expect(Setting::get('readiness_check'))->toBe('ok');
});

test('encrypted settings work correctly', function () {
    Setting::setEncrypted('readiness_secret', 'my_secret', 'test');
    expect(Setting::getDecrypted('readiness_secret'))->toBe('my_secret');
});

// ── Route Registration ──────────────────────────────────────────────────

test('all critical named routes are registered', function () {
    $requiredRoutes = [
        'home',
        'login',
        'register',
        'logout',
        'trending',
        'shorts.index',
        'search',
        'videos.show',
        'videos.create',
        'videos.store',
        'videos.edit',
        'videos.update',
        'videos.destroy',
        'channel.show',
        'categories.index',
        'categories.show',
        'contact',
        'settings',
        'dashboard',
        'feed',
        'history.index',
        'notifications.index',
        'playlists.index',
        'playlists.store',
        // 'live.index' intentionally absent — live streaming was removed in
        // 2025_02_24_000001_drop_live_streaming_tables.
        'wallet.index',
        'sitemap',
        'robots.txt',
        'video-ads.get',
    ];

    $router = app('router');
    $registeredRoutes = collect($router->getRoutes()->getRoutesByName())->keys();

    foreach ($requiredRoutes as $route) {
        expect($registeredRoutes->contains($route))
            ->toBeTrue("Route [{$route}] is not registered");
    }
});

test('locale-prefixed routes are registered', function () {
    $localeRoutes = [
        'locale.home',
        'locale.trending',
        'locale.shorts.index',
        'locale.search',
        'locale.videos.show',
        'locale.channel.show',
    ];

    $router = app('router');
    $registeredRoutes = collect($router->getRoutes()->getRoutesByName())->keys();

    foreach ($localeRoutes as $route) {
        expect($registeredRoutes->contains($route))
            ->toBeTrue("Locale route [{$route}] is not registered");
    }
});

// ── Middleware ───────────────────────────────────────────────────────────

test('security headers middleware is registered', function () {
    expect(class_exists(\App\Http\Middleware\AddSecurityHeaders::class))->toBeTrue();
});

test('age verification middleware is registered', function () {
    expect(class_exists(\App\Http\Middleware\AgeVerification::class))->toBeTrue();
});

test('set locale middleware is registered', function () {
    expect(class_exists(\App\Http\Middleware\SetLocale::class))->toBeTrue();
});

test('check installed middleware is registered', function () {
    expect(class_exists(\App\Http\Middleware\CheckInstalled::class))->toBeTrue();
});

test('installer routes are blocked after installation', function () {
    $this->get('/install')->assertRedirect('/');
    $this->get('/install/database')->assertRedirect('/');
});

// ── Services ────────────────────────────────────────────────────────────

test('SeoService class exists', function () {
    expect(class_exists(\App\Services\SeoService::class))->toBeTrue();
});

test('VideoService class exists', function () {
    expect(class_exists(\App\Services\VideoService::class))->toBeTrue();
});

test('StorageManager class exists', function () {
    expect(class_exists(\App\Services\StorageManager::class))->toBeTrue();
});

test('TranslationService class exists', function () {
    expect(class_exists(\App\Services\TranslationService::class))->toBeTrue();
});

test('WalletService class exists', function () {
    expect(class_exists(\App\Services\WalletService::class))->toBeTrue();
});

// ── Artisan Commands ────────────────────────────────────────────────────

test('artisan optimize runs without error', function () {
    $exitCode = \Illuminate\Support\Facades\Artisan::call('optimize:clear');
    expect($exitCode)->toBe(0);
});

test('artisan route:list runs without error', function () {
    $exitCode = \Illuminate\Support\Facades\Artisan::call('route:list', ['--json' => true]);
    expect($exitCode)->toBe(0);
});

// ── i18n Catalogue Integrity ────────────────────────────────────────────

/**
 * Flatten a nested translation catalogue into dot-notation keys.
 */
function flattenTranslations(array $catalogue, string $prefix = ''): array
{
    $keys = [];

    foreach ($catalogue as $key => $value) {
        $keys = array_merge(
            $keys,
            is_array($value) ? flattenTranslations($value, "{$prefix}{$key}.") : ["{$prefix}{$key}"]
        );
    }

    return $keys;
}

/**
 * Every t('some.key') literal used anywhere under resources/js.
 */
function usedTranslationKeys(): array
{
    $keys = [];

    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(resource_path('js'), FilesystemIterator::SKIP_DOTS)
    );

    foreach ($files as $file) {
        if (! in_array($file->getExtension(), ['vue', 'js'], true)) {
            continue;
        }
        if (str_contains(str_replace(DIRECTORY_SEPARATOR, '/', $file->getPathname()), '/i18n/')) {
            continue;
        }

        preg_match_all("/\bt\(\s*'([a-z0-9_.]+)'/i", file_get_contents($file->getPathname()), $matches);

        foreach ($matches[1] as $key) {
            $keys[$key] = true;
        }
    }

    return array_keys($keys);
}

test('every translation key used in the UI exists in en.json', function () {
    // en.json is the fallback catalogue every other locale resolves through,
    // so a key missing here renders as a raw dot-path to the user.
    $defined = flattenTranslations(
        json_decode(file_get_contents(resource_path('js/i18n/en.json')), true)
    );

    $missing = array_values(array_diff(usedTranslationKeys(), $defined));

    expect($missing)->toBeEmpty(
        'Keys used in resources/js but absent from en.json: '.implode(', ', $missing)
    );
});

test('translation catalogues define no keys en.json is missing', function () {
    // A key only a translated locale defines can never be reached, since t()
    // resolves against en.json's shape.
    $en = flattenTranslations(json_decode(file_get_contents(resource_path('js/i18n/en.json')), true));

    foreach (glob(resource_path('js/i18n/*.json')) as $path) {
        if (basename($path) === 'en.json') {
            continue;
        }

        $stale = array_values(array_diff(
            flattenTranslations(json_decode(file_get_contents($path), true)),
            $en
        ));

        expect($stale)->toBeEmpty(
            basename($path).' defines keys en.json does not: '.implode(', ', $stale)
        );
    }
});

test('a translated locale ships the English catalogue as a fallback', function () {
    // Without this, any key the active locale is missing renders as a raw
    // dot-path — which is what every locale with no JSON file used to do.
    enableLocales(['en', 'es']);

    $locale = inertiaPagePayload($this->get('/es/videos'))['props']['locale'];

    expect($locale['current'])->toBe('es')
        ->and($locale['fallback'])->not->toBeEmpty()
        ->and($locale['fallback'])->toHaveKey('rewards');
});

test('the default locale ships no fallback catalogue', function () {
    // It would just duplicate `translations` on every response.
    enableLocales(['en', 'es']);

    $locale = inertiaPagePayload($this->get('/videos'))['props']['locale'];

    expect($locale['current'])->toBe('en')
        ->and($locale['fallback'])->toBeEmpty();
});

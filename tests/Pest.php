<?php

use App\Models\Setting;
use App\Models\User;
use App\Models\Video;
use App\Models\VideoEncoding;
use App\Services\Storage\StorageReclaimService;
use App\Services\Translation\TranslationProviderManager;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\Support\FakeTranslationProvider;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
*/

uses(TestCase::class)->in('Feature', 'Unit');

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
function asUser(?User $user = null): User
{
    $user ??= User::factory()->create();
    test()->actingAs($user);

    return $user;
}

/**
 * Create and authenticate an admin user.
 */
function asAdmin(?User $user = null): User
{
    $user ??= User::factory()->admin()->create();
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
    Setting::set('translation_enabled', '1', 'language');
    Setting::set('default_language', $default, 'language');
    Setting::set('enabled_languages', $locales, 'language');
    Setting::clearCache();
}

/**
 * Extract the rel="alternate" hreflang links from a rendered page as
 * [hreflangCode => href], with the app URL stripped for readable assertions.
 */
function hreflangLinks(TestResponse $response): array
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
function inertiaPagePayload(TestResponse $response): array
{
    expect($response->getContent())->toMatch('/data-page="/');

    preg_match('/data-page="([^"]*)"/', $response->getContent(), $matches);

    $page = json_decode(html_entity_decode($matches[1], ENT_QUOTES), true);

    expect($page)->toBeArray()->toHaveKeys(['component', 'props']);

    return $page;
}

/**
 * Swap in the deterministic fake translation provider.
 *
 * Returns nothing — assert against Tests\Support\FakeTranslationProvider's
 * static call log.
 */
function useFakeTranslationProvider(array $config = []): void
{
    FakeTranslationProvider::reset();

    config([
        'translation.drivers.fake' => array_merge(
            ['class' => FakeTranslationProvider::class],
            $config,
        ),
        'translation.batch.fake' => array_merge(['max_items' => 25, 'max_chars' => 4000], $config),
        'translation.throttle.fake' => ['min_delay_ms' => 0, 'max_retries' => 1, 'backoff' => [0]],
        'translation.locale_map.fake' => ['pt' => 'pt-BR'],
    ]);

    Setting::set('translation_provider', 'fake', 'translation', 'string');
    Setting::clearCache();
    app(TranslationProviderManager::class)->forget();
}

/**
 * The storage reclaim service, for the reclaim feature tests.
 */
function reclaims(): StorageReclaimService
{
    return app(StorageReclaimService::class);
}

/**
 * A finished video with real files on the public disk: an original, two
 * renditions with completed encoding rows, an HLS segment tree and a master
 * playlist — the layout the encoder actually writes, which is what the
 * reclaim path parses and acts on.
 */
function processedVideo(array $attributes = []): Video
{
    $video = Video::factory()->create(array_merge([
        'status' => 'processed',
        'video_path' => 'videos/clip-'.Str::random(6).'/original.mp4',
        'duration' => 600,
        'qualities_available' => ['original', '720p', '480p'],
    ], $attributes));

    $disk = Storage::disk('public');
    $disk->put($video->video_path, str_repeat('x', 900_000));

    $dir = dirname($video->video_path);

    foreach (['720p', '480p'] as $quality) {
        $disk->put($dir."/processed/{$quality}.mp4", str_repeat('x', 200_000));

        VideoEncoding::create([
            'video_id' => $video->id,
            'quality' => $quality,
            'status' => VideoEncoding::COMPLETED,
            'run_id' => (string) Str::uuid(),
            'size' => 200_000,
        ]);
    }

    $disk->put($dir.'/processed/hls/720p/segment_000.ts', str_repeat('x', 150_000));
    $disk->put($dir.'/processed/master.m3u8', '#EXTM3U');

    return $video->fresh();
}

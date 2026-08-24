<?php

use App\Jobs\TranslateModelJob;
use App\Models\Category;
use App\Models\Page;
use App\Models\Translation;
use App\Models\Video;
use App\Services\Translation\Providers\LibreTranslateProvider;
use App\Services\Translation\TranslationProviderException;
use App\Services\Translation\TranslationProviderManager;
use App\Services\Translation\TranslationSchedule;
use App\Services\TranslationService;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Tests\Support\FakeTranslationProvider;

/*
|--------------------------------------------------------------------------
| Nothing on the request path may call a translation provider
|--------------------------------------------------------------------------
*/

test('no page view queues translation work or calls the provider', function () {
    // This is the regression test for the incident: an on-view dispatch loop
    // re-queued the same doomed job on every render until the provider banned
    // the whole server. Translation is now scheduled-only.
    Bus::fake();
    enableLocales(['en', 'es']);
    useFakeTranslationProvider();

    $category = Category::factory()->create(['slug' => 'cars']);
    $video = Video::factory()->create([
        'slug' => 'a-video',
        'category_id' => $category->id,
        'privacy' => 'public',
        'is_approved' => true,
        'status' => 'processed',
        'tags' => ['alpha', 'beta'],
    ]);
    Page::create(['title' => 'Legal', 'slug' => 'legal', 'content' => 'x', 'is_published' => true]);

    foreach ([
        '/es', '/es/videos', '/es/categories', '/es/category/cars',
        '/es/tags', '/es/tag/alpha', '/es/pages/legal', "/es/{$video->slug}",
    ] as $path) {
        expect($this->get($path)->status())->toBeLessThan(400, "{$path} failed");
    }

    $this->postJson('/api/translate/batch', [
        'type' => 'video', 'ids' => [$video->id], 'fields' => ['title'], 'locale' => 'es',
    ])->assertStatus(200);

    $this->postJson('/api/translate', [
        'type' => 'video', 'id' => $video->id, 'fields' => ['title'], 'locale' => 'es',
    ])->assertStatus(200);

    Bus::assertNothingDispatched();

    // Belt and braces: a future inline provider call would slip past Bus::fake().
    expect(FakeTranslationProvider::callCount())->toBe(0);
});

/*
|--------------------------------------------------------------------------
| Provider abstraction
|--------------------------------------------------------------------------
*/

test('the configured provider is resolved from settings', function () {
    useFakeTranslationProvider();

    $manager = app(TranslationProviderManager::class);

    expect($manager->default()->key())->toBe('fake')
        ->and($manager->available())->toHaveKeys(['google', 'libretranslate']);
});

test('an unknown provider throws instead of silently falling back to Google', function () {
    // Silent failover is what turned a LibreTranslate outage into a Google ban.
    App\Models\Setting::set('translation_provider', 'nope', 'translation', 'string');
    App\Models\Setting::clearCache();
    app(TranslationProviderManager::class)->forget();

    expect(fn () => app(TranslationProviderManager::class)->default())
        ->toThrow(InvalidArgumentException::class);
});

test('LibreTranslate maps pt to pt-BR and sends the key in the body', function () {
    // The API exposes Brazilian Portuguese as `pt-BR` (the Argos package is
    // named `pb`, but that code is only for LT_LOAD_ONLY). Plain `pt` is
    // European Portuguese — a different model.
    Http::fake(['*/translate' => Http::response(['translatedText' => ['A', 'B']])]);

    $provider = new LibreTranslateProvider([
        'endpoint' => 'http://lt.test',
        'api_key' => 'secret',
        'locale_map' => ['pt' => 'pt-BR'],
        'max_items' => 25,
    ]);

    $result = $provider->translateBatch(['x' => 'one', 'y' => 'two'], 'pt', 'en');

    expect($result)->toBe(['x' => 'A', 'y' => 'B']);

    Http::assertSent(function ($request) {
        return $request['target'] === 'pt-BR'
            && $request['source'] === 'en'
            && $request['api_key'] === 'secret'
            && $request['q'] === ['one', 'two'];
    });
});

test('a mismatched batch response is rejected rather than mis-zipped', function () {
    // Pairing translations with the wrong records would put one video's title
    // on another — worse than failing outright.
    Http::fake(['*/translate' => Http::response(['translatedText' => ['only one']])]);

    $provider = new LibreTranslateProvider(['endpoint' => 'http://lt.test']);

    expect(fn () => $provider->translateBatch(['a' => '1', 'b' => '2'], 'es', 'en'))
        ->toThrow(TranslationProviderException::class);
});

test('LibreTranslate HTTP statuses map to the right failure reasons', function (int $status, string $reason, bool $fatal) {
    Http::fake(['*/translate' => Http::response(['error' => 'nope'], $status)]);

    $provider = new LibreTranslateProvider(['endpoint' => 'http://lt.test']);

    try {
        $provider->translate('hello', 'es', 'en');
        $this->fail('Expected a TranslationProviderException');
    } catch (TranslationProviderException $e) {
        expect($e->reason)->toBe($reason)->and($e->isFatal())->toBe($fatal);
    }
})->with([
    [429, TranslationProviderException::RATE_LIMITED, false],
    [403, TranslationProviderException::UNAUTHORIZED, true],
    [503, TranslationProviderException::UNAVAILABLE, false],
]);

test('test connection names languages with no installed model', function () {
    // LT_LOAD_ONLY means a healthy instance can still be missing half the
    // enabled languages — a bare "connected" would hide that.
    enableLocales(['en', 'es', 'pt']);
    Http::fake(['*/languages' => Http::response([['code' => 'en'], ['code' => 'es']])]);

    $result = (new LibreTranslateProvider([
        'endpoint' => 'http://lt.test',
        'locale_map' => ['pt' => 'pt-BR'],
    ]))->testConnection();

    expect($result['success'])->toBeFalse()
        ->and($result['message'])->toContain('pt → pt-BR');
});

/*
|--------------------------------------------------------------------------
| Scheduled run
|--------------------------------------------------------------------------
*/

test('the scheduled run translates content and records provenance', function () {
    enableLocales(['en', 'es']);
    useFakeTranslationProvider();

    $video = Video::factory()->create([
        'title' => 'Original Title',
        'privacy' => 'public',
        'is_approved' => true,
        'status' => 'processed',
    ]);

    $this->artisan('translations:run', ['--section' => ['videos'], '--limit' => 10])
        ->assertSuccessful();

    $row = Translation::where('translatable_type', Video::class)
        ->where('translatable_id', $video->id)
        ->where('field', 'title')
        ->where('locale', 'es')
        ->first();

    expect($row)->not->toBeNull()
        ->and($row->value)->toBe('[es] Original Title')
        ->and($row->provider)->toBe('fake')
        ->and($row->source_locale)->toBe('en')
        ->and($row->translated_slug)->not->toBeEmpty();
});

test('a second run makes no provider calls', function () {
    // Idempotency comes purely from "select rows with no translation yet" —
    // no cursor, no state file.
    enableLocales(['en', 'es']);
    useFakeTranslationProvider();

    Video::factory()->count(3)->create([
        'privacy' => 'public', 'is_approved' => true, 'status' => 'processed',
    ]);

    $this->artisan('translations:run', ['--section' => ['videos']])->assertSuccessful();
    expect(FakeTranslationProvider::callCount())->toBeGreaterThan(0);

    FakeTranslationProvider::reset();
    $this->artisan('translations:run', ['--section' => ['videos']])->assertSuccessful();

    expect(FakeTranslationProvider::callCount())->toBe(0);
});

test('a dry run calls nothing', function () {
    enableLocales(['en', 'es']);
    useFakeTranslationProvider();

    Video::factory()->create(['privacy' => 'public', 'is_approved' => true, 'status' => 'processed']);

    $this->artisan('translations:run', ['--section' => ['videos'], '--dry-run' => true])
        ->assertSuccessful();

    expect(FakeTranslationProvider::callCount())->toBe(0)
        ->and(Translation::count())->toBe(0);
});

test('a failed chunk is re-split so one bad string cannot poison its neighbours', function () {
    enableLocales(['en', 'es']);
    useFakeTranslationProvider();

    Video::factory()->count(3)->create([
        'privacy' => 'public', 'is_approved' => true, 'status' => 'processed',
    ]);

    // Fail the first (batch) call; the retry and per-string fallbacks succeed.
    FakeTranslationProvider::failOnCall(0, TranslationProviderException::unavailable('boom'));

    $this->artisan('translations:run', ['--section' => ['videos'], '--limit' => 10])
        ->assertSuccessful();

    expect(Translation::where('locale', 'es')->count())->toBeGreaterThan(0);
});

/*
|--------------------------------------------------------------------------
| Schedule gate
|--------------------------------------------------------------------------
*/

test('the schedule is due on exactly one minute per period', function () {
    App\Models\Setting::set('translation_schedule_frequency', 'daily', 'translation', 'string');
    App\Models\Setting::set('translation_schedule_time', '03:30', 'translation', 'string');
    App\Models\Setting::clearCache();

    $tz = TranslationSchedule::timezone();

    expect(TranslationSchedule::isDueAt(Carbon\Carbon::parse('2026-01-01 03:30:00', $tz)))->toBeTrue()
        ->and(TranslationSchedule::isDueAt(Carbon\Carbon::parse('2026-01-01 03:31:00', $tz)))->toBeFalse()
        ->and(TranslationSchedule::isDueAt(Carbon\Carbon::parse('2026-01-01 04:30:00', $tz)))->toBeFalse();
});

test('a disabled schedule is never due', function () {
    App\Models\Setting::set('translation_schedule_frequency', 'disabled', 'translation', 'string');
    App\Models\Setting::clearCache();

    expect(TranslationSchedule::isDueAt(Carbon\Carbon::now()))->toBeFalse()
        ->and(TranslationSchedule::isDueNow())->toBeFalse();
});

test('a missed run is caught up rather than lost for a whole period', function () {
    // Without catch-up a monthly run missed by one minute waits a month.
    App\Models\Setting::set('translation_schedule_frequency', 'daily', 'translation', 'string');
    App\Models\Setting::set('translation_schedule_time', '03:30', 'translation', 'string');
    App\Models\Setting::set('translation_last_run_at', now()->subDays(3)->toDateTimeString(), 'translation', 'string');
    App\Models\Setting::clearCache();

    $now = Carbon\Carbon::parse('2099-01-01 09:00:00', TranslationSchedule::timezone());

    expect(TranslationSchedule::isDueAt($now))->toBeFalse()
        ->and(TranslationSchedule::isDueNow($now))->toBeTrue();
});

/*
|--------------------------------------------------------------------------
| Admin settings
|--------------------------------------------------------------------------
*/

test('language settings persist with correct types and never expose the API key', function () {
    // These are read back inside a scheduler closure, where a day arriving as
    // the string "3" would quietly misbehave.
    asAdmin();

    Livewire\Livewire::test(App\Filament\Pages\LanguageSettings::class)
        ->fillForm([
            'translation_enabled' => true,
            'default_language' => 'en',
            'enabled_languages' => ['en', 'es'],
            'translation_provider' => 'libretranslate',
            'libretranslate_endpoint' => 'http://lt.test',
            'libretranslate_api_key' => 'super-secret',
            'translation_schedule_frequency' => 'weekly',
            'translation_schedule_time' => '04:15',
            'translation_schedule_day' => 3,
            'translation_run_limit' => 250,
        ])
        ->call('save');

    App\Models\Setting::clearCache();

    expect(App\Models\Setting::get('translation_schedule_day'))->toBe(3)
        ->and(App\Models\Setting::get('translation_run_limit'))->toBe(250)
        ->and(App\Models\Setting::get('translation_schedule_frequency'))->toBe('weekly')
        ->and(App\Models\Setting::getDecrypted('libretranslate_api_key'))->toBe('super-secret');

    // Setting::get() returns raw cached column values and never decrypts, so
    // the ciphertext must not be mistaken for the key.
    expect(App\Models\Setting::get('libretranslate_api_key'))->not->toBe('super-secret');

    // A blank key on the next save keeps the stored one.
    Livewire\Livewire::test(App\Filament\Pages\LanguageSettings::class)
        ->fillForm(['libretranslate_api_key' => ''])
        ->call('save');

    expect(App\Models\Setting::getDecrypted('libretranslate_api_key'))->toBe('super-secret');
});

/*
|--------------------------------------------------------------------------
| Admin overrides and queue routing
|--------------------------------------------------------------------------
*/

test('admin translation overrides are applied to single and batch translations', function () {
    // Overrides are the correction layer for terms the engine gets wrong
    // ("Segunda corda" for "second string"). They must apply on every path.
    enableLocales(['en', 'es']);
    useFakeTranslationProvider();

    App\Models\TranslationOverride::create([
        'locale' => 'es',
        'original_text' => 'corda',
        'replacement_text' => 'cadena',
        'case_sensitive' => false,
        'is_active' => true,
    ]);
    App\Models\TranslationOverride::clearCache();

    $service = app(TranslationService::class);

    // Single: the fake returns "[es] <text>", so feed it text containing the term.
    expect($service->tryTranslateText('corda', 'es'))->toBe('[es] cadena');

    // Batch: the path used by translations:run.
    expect($service->tryTranslateBatch(['a' => 'corda', 'b' => 'plain'], 'es'))
        ->toBe(['a' => '[es] cadena', 'b' => '[es] plain']);
});

test('overrides survive a scheduled run and reach the stored translation', function () {
    enableLocales(['en', 'es']);
    useFakeTranslationProvider();

    App\Models\TranslationOverride::create([
        'locale' => 'es',
        'original_text' => 'Wedgie',
        'replacement_text' => 'Calzón',
        'case_sensitive' => false,
        'is_active' => true,
    ]);
    App\Models\TranslationOverride::clearCache();

    $video = App\Models\Video::factory()->create([
        'title' => 'Wedgie Compilation',
        'privacy' => 'public', 'is_approved' => true, 'status' => 'processed',
    ]);

    $this->artisan('translations:run', ['--section' => ['videos']])->assertSuccessful();

    $stored = Translation::where('translatable_id', $video->id)
        ->where('field', 'title')->where('locale', 'es')->value('value');

    expect($stored)->toContain('Calzón')->not->toContain('Wedgie');
});

test('translation work runs on its own queue, not the 60s default', function () {
    // A full sweep takes minutes; the default supervisor kills at 60s.
    expect((new TranslateModelJob(App\Models\Video::class, 1, ['title'], 'es'))->queue)
        ->toBe('translations');

    expect(config('horizon.environments.production.translations.timeout'))->toBe(3600)
        ->and(config('horizon.environments.local.translations.timeout'))->toBe(3600);
});

test('the queue retry window exceeds every declared job timeout', function () {
    // retry_after below a job's timeout means a merely-slow job is handed to a
    // second worker and runs twice — duplicate transcodes, duplicate provider calls.
    $longest = 0;

    foreach (glob(app_path('Jobs/*.php')) as $file) {
        if (preg_match('/public int \$timeout\s*=\s*(\d+)/', file_get_contents($file), $m)) {
            $longest = max($longest, (int) $m[1]);
        }
    }

    expect(config('queue.connections.redis.retry_after'))->toBeGreaterThan($longest);
});

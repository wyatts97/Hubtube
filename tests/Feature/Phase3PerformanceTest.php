<?php

use App\Models\Setting;
use App\Models\Video;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

test('liking a video does not flush the listing caches', function () {
    $video = Video::factory()->create();
    Cache::put('home:popular', 'cached', 600);
    asUser();

    $this->postJson(route('videos.like', $video))->assertOk();

    expect(Cache::get('home:popular'))->toBe('cached')
        ->and($video->fresh()->likes_count)->toBeGreaterThan(0);
});

test('changing a listed field still flushes the listing caches', function () {
    $video = Video::factory()->create();
    Cache::put('home:popular', 'cached', 600);

    $video->update(['title' => 'A new title']);

    expect(Cache::has('home:popular'))->toBeFalse();
});

test('visits are counted for people but not for crawlers', function () {
    $this->get('/', ['User-Agent' => 'Mozilla/5.0 (Windows NT 10.0) Firefox/130.0'])->assertOk();
    expect(DB::table('visitor_daily')->count())->toBe(1);

    $this->get('/', ['User-Agent' => 'Googlebot/2.1 (+http://www.google.com/bot.html)'])->assertOk();
    expect(DB::table('visitor_daily')->count())->toBe(1);
});

test('encrypted settings are read from the cached settings map', function () {
    Setting::setEncrypted('stripe_webhook_secret', 'whsec_cached');
    Setting::getAll();

    DB::enableQueryLog();
    $value = Setting::getDecrypted('stripe_webhook_secret');

    expect($value)->toBe('whsec_cached')
        ->and(DB::getQueryLog())->toBeEmpty();
});

test('an unknown trending period falls back to the week', function () {
    $this->get('/trending?period=../../evil&page=99999')
        ->assertOk()
        ->assertInertia(fn ($page) => expect($page->toArray()['props']['period'])->toBe('week'));
});

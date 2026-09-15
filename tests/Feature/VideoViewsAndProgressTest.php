<?php

use App\Models\Video;
use App\Models\VideoView;
use App\Models\WatchHistory;

/*
|--------------------------------------------------------------------------
| View counting and watch progress
|--------------------------------------------------------------------------
*/

const BROWSER_UA = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0 Safari/537.36';

// ── Views ───────────────────────────────────────────────────────────────────

test('a watch page visit counts one view and logs it', function () {
    $video = Video::factory()->create(['views_count' => 0]);

    $this->get("/{$video->slug}", ['User-Agent' => BROWSER_UA, 'CF-IPCountry' => 'NL'])->assertOk();

    expect($video->fresh()->views_count)->toBe(1);

    $row = VideoView::where('video_id', $video->id)->sole();
    expect($row->country)->toBe('NL');
});

test('reloading the page does not count again', function () {
    $video = Video::factory()->create(['views_count' => 0]);

    $this->get("/{$video->slug}", ['User-Agent' => BROWSER_UA])->assertOk();
    $this->get("/{$video->slug}", ['User-Agent' => BROWSER_UA])->assertOk();
    $this->get("/{$video->slug}", ['User-Agent' => BROWSER_UA])->assertOk();

    expect($video->fresh()->views_count)->toBe(1);
    expect(VideoView::where('video_id', $video->id)->count())->toBe(1);
});

test('different signed-in viewers each count', function () {
    $video = Video::factory()->create(['views_count' => 0]);

    asUser();
    $this->get("/{$video->slug}", ['User-Agent' => BROWSER_UA])->assertOk();

    asUser();
    $this->get("/{$video->slug}", ['User-Agent' => BROWSER_UA])->assertOk();

    expect($video->fresh()->views_count)->toBe(2);
});

test('crawlers and prefetches are not counted', function () {
    $video = Video::factory()->create(['views_count' => 0]);

    $this->get("/{$video->slug}", ['User-Agent' => 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)'])->assertOk();
    $this->get("/{$video->slug}", ['User-Agent' => BROWSER_UA, 'Purpose' => 'prefetch'])->assertOk();
    $this->get("/{$video->slug}", ['User-Agent' => ''])->assertOk();

    expect($video->fresh()->views_count)->toBe(0);
});

test('counting a view does not flush the home page caches', function () {
    $video = Video::factory()->create();
    cache()->put('home:popular', 'cached');

    $this->get("/{$video->slug}", ['User-Agent' => BROWSER_UA])->assertOk();

    expect(cache()->get('home:popular'))->toBe('cached');
});

test('old video_views rows are pruned', function () {
    $video = Video::factory()->create();
    $old = VideoView::create(['video_id' => $video->id]);
    $old->forceFill(['created_at' => now()->subDays(120)])->save();
    $recent = VideoView::create(['video_id' => $video->id]);

    $this->artisan('model:prune', ['--model' => [VideoView::class]])->assertSuccessful();

    expect(VideoView::pluck('id')->all())->toBe([$recent->id]);
});

// ── Watch progress ──────────────────────────────────────────────────────────

test('the player position is saved to watch history', function () {
    $user = asUser();
    $video = Video::factory()->create(['duration' => 600]);

    $this->postJson("/videos/{$video->id}/progress", ['seconds' => 125.7])->assertNoContent();

    $history = WatchHistory::where('user_id', $user->id)->where('video_id', $video->id)->sole();
    expect($history->watched_seconds)->toBe(125);
    expect($history->completed)->toBeFalse();
});

test('reaching the end marks the video completed and it stays completed', function () {
    $user = asUser();
    $video = Video::factory()->create(['duration' => 100]);

    $this->postJson("/videos/{$video->id}/progress", ['seconds' => 95])->assertNoContent();
    $this->postJson("/videos/{$video->id}/progress", ['seconds' => 10])->assertNoContent();

    $history = WatchHistory::where('user_id', $user->id)->where('video_id', $video->id)->sole();
    expect($history->watched_seconds)->toBe(10);
    expect($history->completed)->toBeTrue();
});

test('progress is clamped to the video duration', function () {
    $user = asUser();
    $video = Video::factory()->create(['duration' => 60]);

    $this->postJson("/videos/{$video->id}/progress", ['seconds' => 5000])->assertNoContent();

    expect(WatchHistory::where('user_id', $user->id)->sole()->watched_seconds)->toBe(60);
});

test('progress cannot be recorded against a private video the user cannot see', function () {
    asUser();
    $video = Video::factory()->private()->create();

    $this->postJson("/videos/{$video->id}/progress", ['seconds' => 5])->assertNotFound();

    expect(WatchHistory::count())->toBe(0);
});

test('guests cannot record progress', function () {
    $video = Video::factory()->create();

    $this->postJson("/videos/{$video->id}/progress", ['seconds' => 5])->assertUnauthorized();
});

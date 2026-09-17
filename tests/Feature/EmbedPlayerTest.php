<?php

use App\Models\Setting;
use App\Models\User;
use App\Models\Video;

/*
|--------------------------------------------------------------------------
| Embeddable player and oEmbed
|--------------------------------------------------------------------------
*/

beforeEach(function () {
    Setting::set('embed_enabled', true, 'general', 'boolean');
});

test('the embed page renders a bare player', function () {
    $video = Video::factory()->create(['title' => 'Embeddable Clip']);

    $page = inertiaPagePayload($this->get("/embed/{$video->slug}"));

    expect($page['component'])->toBe('Embed')
        ->and($page['props']['video']['slug'])->toBe($video->slug)
        ->and($page['props']['watchUrl'])->toBe(url("/{$video->slug}"));
});

test('the embed page may be framed by other sites, and nothing else may', function () {
    $video = Video::factory()->create();

    $embed = $this->get("/embed/{$video->slug}");
    expect($embed->headers->get('Content-Security-Policy'))->toContain('frame-ancestors *');
    expect($embed->headers->get('X-Frame-Options'))->toBeNull();

    $watch = $this->get("/{$video->slug}");
    expect($watch->headers->get('Content-Security-Policy'))->toContain("frame-ancestors 'self'");
    expect($watch->headers->get('X-Frame-Options'))->toBe('SAMEORIGIN');
});

test('videos that are not public are never embeddable', function () {
    $private = Video::factory()->private()->create();
    $draft = Video::factory()->create(['is_draft' => true]);
    $unapproved = Video::factory()->unapproved()->create();
    $unlisted = Video::factory()->unlisted()->create();

    $this->get("/embed/{$private->slug}")->assertNotFound();
    $this->get("/embed/{$draft->slug}")->assertNotFound();
    $this->get("/embed/{$unapproved->slug}")->assertNotFound();

    // Unlisted is "anyone with the link", so an embed is fine.
    $this->get("/embed/{$unlisted->slug}")->assertOk();
});

test('embedding can be switched off site-wide', function () {
    Setting::set('embed_enabled', false, 'general', 'boolean');
    $video = Video::factory()->create();

    $this->get("/embed/{$video->slug}")->assertNotFound();
    $this->getJson('/api/oembed?url='.urlencode(url("/{$video->slug}")))->assertNotFound();
});

test('a geo-blocked video refuses to embed in that country', function () {
    $video = Video::factory()->create(['geo_blocked_countries' => ['DE']]);

    $this->get("/embed/{$video->slug}", ['CF-IPCountry' => 'DE'])->assertStatus(451);
    $this->get("/embed/{$video->slug}", ['CF-IPCountry' => 'US'])->assertOk();
});

// ── oEmbed ──────────────────────────────────────────────────────────────────

test('oEmbed describes a watch URL', function () {
    $video = Video::factory()->create(['title' => 'Described Clip', 'duration' => 125]);

    $response = $this->getJson('/api/oembed?url='.urlencode(url("/{$video->slug}")))->assertOk();

    $response->assertJson([
        'type' => 'video',
        'version' => '1.0',
        'title' => 'Described Clip',
        'author_name' => $video->user->username,
        'width' => 640,
        'height' => 360,
        'duration' => 125,
    ]);

    expect($response->json('html'))
        ->toContain('<iframe')
        ->toContain(route('videos.embed', $video->slug))
        ->toContain('allowfullscreen');
});

test('oEmbed honours maxwidth and keeps the aspect ratio', function () {
    $video = Video::factory()->create();

    $response = $this->getJson('/api/oembed?url='.urlencode(url("/{$video->slug}")).'&maxwidth=320')->assertOk();

    expect($response->json('width'))->toBe(320)
        ->and($response->json('height'))->toBe(180);
});

test('oEmbed accepts an embed URL and a locale-prefixed watch URL', function () {
    $video = Video::factory()->create();

    $this->getJson('/api/oembed?url='.urlencode(route('videos.embed', $video->slug)))->assertOk();
    $this->getJson('/api/oembed?url='.urlencode(url("/es/{$video->slug}")))->assertOk();
});

test('oEmbed refuses unknown, foreign and non-public URLs', function () {
    $private = Video::factory()->private()->create();

    $this->getJson('/api/oembed?url='.urlencode(url('/no-such-video')))->assertNotFound();
    $this->getJson('/api/oembed?url='.urlencode('https://example.com/some-video'))->assertNotFound();
    $this->getJson('/api/oembed?url='.urlencode(url("/{$private->slug}")))->assertNotFound();
    $this->getJson('/api/oembed')->assertStatus(422);
});

// ── Discovery from the watch page ───────────────────────────────────────────

test('the watch page advertises oEmbed and the real embed URL', function () {
    $video = Video::factory()->create();

    $html = $this->get("/{$video->slug}")->assertOk()->getContent();

    expect($html)->toContain('application/json+oembed');
    expect($html)->toContain('\/embed\/'.$video->slug);

    $page = inertiaPagePayload($this->get("/{$video->slug}"));
    expect($page['props']['embedCode'])->toContain('<iframe')->toContain('/embed/'.$video->slug);
});

test('the share dialog gets no embed code for a video that cannot be embedded', function () {
    $user = User::factory()->create();
    $video = Video::factory()->private()->create(['user_id' => $user->id]);
    asUser($user);

    $page = inertiaPagePayload($this->get("/{$video->slug}"));

    expect($page['props']['embedCode'])->toBe('');
});

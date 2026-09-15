<?php

use App\Models\Setting;
use App\Models\User;
use App\Models\Video;
use App\Services\ProtectedMediaService;
use Illuminate\Support\Facades\Storage;

/*
|--------------------------------------------------------------------------
| Protected media — private video files and geo-blocking
|--------------------------------------------------------------------------
|
| Nginx serves /storage/ directly; for a private video's directory it hands
| the request to Laravel instead (see ProtectedMediaService). These tests
| exercise the Laravel half: markers, authorisation and the file response.
|
*/

function privateVideoWithFile(array $attributes = []): Video
{
    $video = Video::factory()->private()->create($attributes);
    Storage::disk('public')->put("videos/{$video->slug}/processed/720p.mp4", 'fake-mp4-bytes');

    return $video;
}

beforeEach(function () {
    Storage::fake('public');
});

// ── Markers ─────────────────────────────────────────────────────────────────

test('creating a private video writes the privacy marker', function () {
    $video = Video::factory()->private()->create();

    Storage::disk('public')->assertExists("videos/{$video->slug}/".ProtectedMediaService::MARKER);
});

test('the marker follows privacy changes', function () {
    $video = Video::factory()->create();
    $marker = "videos/{$video->slug}/".ProtectedMediaService::MARKER;

    Storage::disk('public')->assertMissing($marker);

    $video->update(['privacy' => 'private']);
    Storage::disk('public')->assertExists($marker);

    $video->update(['privacy' => 'unlisted']);
    Storage::disk('public')->assertMissing($marker);
});

test('the sync command restores missing markers and removes stale ones', function () {
    $private = Video::factory()->private()->create();
    $public = Video::factory()->create();
    $disk = Storage::disk('public');

    $disk->delete("videos/{$private->slug}/".ProtectedMediaService::MARKER);
    $disk->put("videos/{$public->slug}/".ProtectedMediaService::MARKER, '');

    $this->artisan('videos:sync-media-protection')->assertSuccessful();

    $disk->assertExists("videos/{$private->slug}/".ProtectedMediaService::MARKER);
    $disk->assertMissing("videos/{$public->slug}/".ProtectedMediaService::MARKER);
});

// ── Authorisation ───────────────────────────────────────────────────────────

test('guests get 404 for a private video file', function () {
    $video = privateVideoWithFile();

    $this->get("/storage/videos/{$video->slug}/processed/720p.mp4")->assertNotFound();
});

test('other signed-in users get 404 for a private video file', function () {
    $video = privateVideoWithFile();
    asUser();

    $this->get("/storage/videos/{$video->slug}/processed/720p.mp4")->assertNotFound();
});

test('the owner receives the file with private cache headers', function () {
    $user = User::factory()->create();
    $video = privateVideoWithFile(['user_id' => $user->id]);
    asUser($user);

    $response = $this->get("/storage/videos/{$video->slug}/processed/720p.mp4");

    $response->assertOk();
    expect($response->headers->get('Content-Type'))->toBe('video/mp4');
    expect($response->headers->get('Cache-Control'))->toContain('no-store')->toContain('private');
});

test('admins receive the file', function () {
    $video = privateVideoWithFile();
    asAdmin();

    $this->get("/storage/videos/{$video->slug}/processed/720p.mp4")->assertOk();
});

test('with X-Accel-Redirect enabled nginx is told where the file is', function () {
    Setting::set('media_x_accel_redirect', true, 'storage', 'boolean');
    $video = privateVideoWithFile();
    asAdmin();

    $response = $this->get("/storage/videos/{$video->slug}/processed/720p.mp4");

    $response->assertOk();
    expect($response->headers->get('X-Accel-Redirect'))
        ->toBe("/_protected-media/videos/{$video->slug}/processed/720p.mp4");
    expect($response->getContent())->toBe('');
});

test('the proxied /protected-media route applies the same checks', function () {
    // Server layouts that proxy to a separate PHP block (CloudPanel) forward
    // the rewritten URI rather than the original /storage/ one.
    $video = privateVideoWithFile();
    $query = '/protected-media?path='.urlencode("/storage/videos/{$video->slug}/processed/720p.mp4");

    $this->get($query)->assertNotFound();

    asAdmin();
    $this->get($query)->assertOk();
    $this->get('/protected-media?path='.urlencode('/etc/passwd'))->assertNotFound();
    $this->get('/protected-media')->assertNotFound();
});

test('the marker file and traversal paths are never served', function () {
    $video = privateVideoWithFile();
    asAdmin();

    $this->get("/storage/videos/{$video->slug}/.private")->assertNotFound();
    $this->get("/storage/videos/{$video->slug}/processed/../../../.env")->assertNotFound();
    $this->get("/storage/videos/{$video->slug}/processed/missing.mp4")->assertNotFound();
});

// ── URLs ────────────────────────────────────────────────────────────────────

test('private videos bypass the CDN so the viewer\'s session reaches the app', function () {
    Setting::set('cdn_enabled', true, 'storage', 'boolean');
    Setting::set('cdn_url', 'https://cdn.example.test', 'storage');

    $private = Video::factory()->private()->create();

    expect($private->video_url)->toStartWith(url('/storage/'));
});

// ── Geo-blocking ────────────────────────────────────────────────────────────

test('a geo-blocked video returns 451 in the blocked country', function () {
    $video = Video::factory()->create(['geo_blocked_countries' => ['DE']]);

    $this->get("/{$video->slug}", ['CF-IPCountry' => 'DE'])->assertStatus(451);
    $this->get("/{$video->slug}", ['CF-IPCountry' => 'US'])->assertOk();
});

test('geo-blocking does not apply without a country or to the owner', function () {
    $user = User::factory()->create();
    $video = Video::factory()->create(['user_id' => $user->id, 'geo_blocked_countries' => ['de']]);

    $this->get("/{$video->slug}")->assertOk();

    asUser($user);
    $this->get("/{$video->slug}", ['CF-IPCountry' => 'DE'])->assertOk();
});

test('the availableIn scope excludes videos blocked in that country', function () {
    $blocked = Video::factory()->create(['geo_blocked_countries' => ['FR']]);
    $open = Video::factory()->create(['geo_blocked_countries' => null]);

    $ids = Video::query()->availableIn('FR')->pluck('id');

    expect($ids)->toContain($open->id)->not->toContain($blocked->id);
});

test('a private short is not exposed through its shorts deep link', function () {
    $short = Video::factory()->private()->create(['is_portrait' => true, 'duration' => 30]);

    $page = inertiaPagePayload($this->get("/shorts/{$short->uuid}"));

    expect($page['props']['currentShort'])->toBeNull();
});

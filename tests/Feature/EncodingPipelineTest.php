<?php

use App\Filament\Resources\EncodeProfileResource;
use App\Jobs\EncodeVideoChunkJob;
use App\Jobs\ProcessVideoJob;
use App\Livewire\VideoEncodingProgress;
use App\Models\EncodeProfile;
use App\Models\Setting;
use App\Models\User;
use App\Models\Video;
use App\Models\VideoEncoding;
use App\Services\Encoding\FfmpegCommands;
use App\Services\Encoding\FfmpegRunner;
use App\Services\Encoding\RenditionCoordinator;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Support\FakeFfmpegRunner;

/*
|--------------------------------------------------------------------------
| Encoding pipeline — profiles, chunks, low-res first, tracking, sprites
|--------------------------------------------------------------------------
|
| FFmpeg is replaced by FakeFfmpegRunner, and the sync queue runs the whole
| job graph inline, so one ProcessVideoJob::dispatchSync() walks a video from
| upload to fully encoded.
|
*/

function fakeFfmpeg(float $duration = 60, int $width = 1920, int $height = 1080, bool $audio = true): FakeFfmpegRunner
{
    $fake = new FakeFfmpegRunner($duration, $width, $height, $audio);
    app()->instance(FfmpegRunner::class, $fake);

    return $fake;
}

function onlyProfiles(array $names): void
{
    EncodeProfile::query()->update(['is_active' => false]);
    EncodeProfile::whereIn('name', $names)->update(['is_active' => true]);
}

function uploadedVideo(array $attributes = []): Video
{
    $video = Video::factory()->create(array_merge([
        'slug' => 'clip-'.uniqid(),
        'status' => 'pending',
        'is_approved' => false,
        'qualities_available' => null,
        'scrubber_vtt_path' => null,
    ], $attributes));

    $video->update(['video_path' => "videos/{$video->slug}/clip.mp4"]);
    Storage::disk('public')->put($video->video_path, str_repeat('v', 4096));

    return $video->fresh();
}

beforeEach(function () {
    Setting::set('video_auto_approve', false, 'general', 'boolean');
});

// ── Profiles ────────────────────────────────────────────────────────────────

test('the migration seeds a ladder from 144p to 2160p with the old defaults active', function () {
    expect(EncodeProfile::orderBy('height')->pluck('name')->all())
        ->toBe(['144p', '240p', '360p', '480p', '720p', '1080p', '1440p', '2160p']);

    expect(EncodeProfile::active()->orderBy('height')->pluck('name')->all())
        ->toBe(['360p', '480p', '720p']);
});

test('bitrates are parsed for the HLS playlist', function () {
    expect(EncodeProfile::parseBitrate('2800k'))->toBe(2800000)
        ->and(EncodeProfile::parseBitrate('5M'))->toBe(5000000)
        ->and(EncodeProfile::parseBitrate('750000'))->toBe(750000)
        ->and(EncodeProfile::parseBitrate('nonsense'))->toBe(0);
});

// ── End to end ──────────────────────────────────────────────────────────────

test('a short upload is encoded to every active profile below its height', function () {
    onlyProfiles(['360p', '480p', '720p', '1080p']);
    $fake = fakeFfmpeg(duration: 60, height: 720, width: 1280);
    $video = uploadedVideo();

    ProcessVideoJob::dispatchSync($video);
    $video->refresh();

    expect($video->status)->toBe('processed')
        ->and($video->qualities_available)->toBe(['360p', '480p', 'original'])
        ->and($video->processing_stage)->toBeNull()
        ->and($video->processing_fallback_reason)->toBeNull();

    // Never upscaled, never re-encoded at the source height.
    expect($video->encodings()->pluck('quality')->sort()->values()->all())->toBe(['360p', '480p']);
    expect($video->encodings()->where('status', 'completed')->count())->toBe(2);
    expect($fake->commandsFor('chunks/720p'))->toBeEmpty();

    $processed = Storage::disk('public')->path("videos/{$video->slug}/processed");
    expect(file_get_contents("{$processed}/master.m3u8"))
        ->toContain('hls/360p/playlist.m3u8')
        ->toContain('hls/480p/playlist.m3u8');
    expect(is_dir("{$processed}/chunks"))->toBeFalse();
});

test('the lowest rendition is encoded first and the video goes live on it', function () {
    onlyProfiles(['360p', '480p', '720p']);
    $fake = fakeFfmpeg(duration: 60);
    $video = uploadedVideo();

    $seenWhenHigherStarted = null;
    $fake->onRun = function (string $cmd) use ($video, &$seenWhenHigherStarted) {
        if ($seenWhenHigherStarted === null && (str_contains($cmd, 'chunks/480p') || str_contains($cmd, 'chunks/720p'))) {
            $fresh = $video->fresh();
            $seenWhenHigherStarted = [$fresh->status, $fresh->qualities_available];
        }
    };

    ProcessVideoJob::dispatchSync($video);

    $encodeOrder = collect($fake->commands)
        ->map(fn ($cmd) => preg_match('#chunks/(\d+p)/#', $cmd, $m) ? $m[1] : null)
        ->filter()->unique()->values()->all();

    expect($encodeOrder[0])->toBe('360p');
    expect($seenWhenHigherStarted)->toBe(['processed', ['360p', 'original']]);
    expect($video->encodings()->where('quality', '360p')->value('is_priority'))->toBeTrue();
});

test('long videos are encoded as chunks joined to a shared audio track', function () {
    onlyProfiles(['360p']);
    Setting::set('chunked_encoding_min_duration', 300, 'general', 'integer');
    Setting::set('chunked_encoding_chunk_seconds', 240, 'general', 'integer');
    $fake = fakeFfmpeg(duration: 700);
    $video = uploadedVideo();

    ProcessVideoJob::dispatchSync($video);

    $encoding = $video->encodings()->where('quality', '360p')->sole();
    expect($encoding->chunks_total)->toBe(3)
        ->and($encoding->chunk_seconds)->toBe(240)
        ->and($encoding->status)->toBe('completed');

    $chunks = $fake->commandsFor('chunks/360p');
    $encodes = array_values(array_filter($chunks, fn ($c) => str_contains($c, 'libx264')));
    expect($encodes)->toHaveCount(3);
    expect($encodes[0])->not->toContain('-ss ')->toContain('-t 240')->toContain('-an');
    expect($encodes[1])->toContain('-ss 240')->toContain('-t 240');
    // The last chunk runs to the end of the file rather than a rounded length.
    expect($encodes[2])->toContain('-ss 480')->not->toContain('-t ');

    expect($fake->commandsFor('audio.part.m4a'))->toHaveCount(1);
    $join = collect($fake->commands)->first(fn ($c) => str_contains($c, '-f concat'));
    expect($join)->toContain('audio.m4a')->toContain('-c copy');
});

test('if the shared audio track fails, renditions are encoded whole instead', function () {
    onlyProfiles(['360p']);
    Setting::set('chunked_encoding_min_duration', 300, 'general', 'integer');
    $fake = fakeFfmpeg(duration: 700);
    $fake->failWhen = fn (string $cmd) => str_contains($cmd, 'audio.part.m4a');
    $video = uploadedVideo();

    ProcessVideoJob::dispatchSync($video);

    $encoding = $video->encodings()->sole();
    expect($encoding->chunks_total)->toBe(1)->and($encoding->status)->toBe('completed');
    expect($fake->commandsFor('libx264')[0])->toContain('-c:a aac');
});

test('chunk length is rounded up onto the keyframe grid', function () {
    $commands = new FfmpegCommands([
        'chunked_encoding_enabled' => true,
        'chunked_encoding_min_duration' => 300,
        'chunked_encoding_chunk_seconds' => 125,
    ]);

    expect(app(RenditionCoordinator::class)->chunkPlan(['duration' => 1000.0], $commands))->toBe([8, 126]);
    expect(app(RenditionCoordinator::class)->chunkPlan(['duration' => 200.0], $commands))->toBe([1, 0]);
});

test('a failed rendition does not stop the others from publishing', function () {
    onlyProfiles(['360p', '480p', '720p']);
    $fake = fakeFfmpeg(duration: 60);
    $fake->failWhen = fn (string $cmd) => str_contains($cmd, 'chunks/720p');
    $video = uploadedVideo();

    ProcessVideoJob::dispatchSync($video);
    $video->refresh();

    expect($video->status)->toBe('processed')
        ->and($video->qualities_available)->toBe(['360p', '480p', 'original'])
        ->and($video->processing_fallback_reason)->toStartWith('Some renditions failed: 720p');

    $failed = $video->encodings()->where('quality', '720p')->sole();
    expect($failed->status)->toBe('failed')->and($failed->error)->toContain('simulated ffmpeg failure');
});

test('when every rendition fails the upload is still published', function () {
    onlyProfiles(['360p', '480p']);
    $fake = fakeFfmpeg(duration: 60);
    $fake->failWhen = fn (string $cmd) => str_contains($cmd, 'libx264');
    $video = uploadedVideo();

    ProcessVideoJob::dispatchSync($video);
    $video->refresh();

    expect($video->status)->toBe('processed')
        ->and($video->qualities_available)->toBe(['original'])
        ->and($video->processing_fallback_reason)->toBe('Transcoding failed; shipped as unprocessed original.');
});

// ── Watermark ───────────────────────────────────────────────────────────────

test('with a watermark, renditions draw it and the watermarked original replaces the upload last', function () {
    onlyProfiles(['360p']);
    Setting::set('watermark_text_enabled', true, 'general', 'boolean');
    Setting::set('watermark_text', 'HubTube', 'general');
    $fake = fakeFfmpeg(duration: 60);
    $video = uploadedVideo();

    $qualitiesAtFirstPublish = null;
    $fake->onRun = function (string $cmd) use ($video, &$qualitiesAtFirstPublish) {
        if ($qualitiesAtFirstPublish === null && str_contains($cmd, 'chunks/original')) {
            $qualitiesAtFirstPublish = $video->fresh()->qualities_available;
        }
    };

    ProcessVideoJob::dispatchSync($video);
    $video->refresh();

    // The unwatermarked upload is held back until its watermarked copy lands.
    expect($qualitiesAtFirstPublish)->toBe(['360p']);
    expect($video->qualities_available)->toBe(['360p', 'original']);

    $rendition = collect($fake->commandsFor('chunks/360p'))->first(fn ($c) => str_contains($c, 'libx264'));
    expect($rendition)->toContain('drawtext')->toContain('scale=-2:360');

    Storage::disk('public')->assertExists("videos/{$video->slug}/.watermark_done");
    Storage::disk('public')->assertMissing("videos/{$video->slug}/processed/original_watermarked.mp4");

    // A later re-encode must not watermark the already-watermarked source again.
    EncodeProfile::where('name', '240p')->update(['is_active' => true]);
    $fake->commands = [];
    ProcessVideoJob::dispatchSync($video->fresh());

    $again = collect($fake->commandsFor('chunks/240p'))->first(fn ($c) => str_contains($c, 'libx264'));
    expect($again)->not->toContain('drawtext');
    expect($fake->commandsFor('chunks/original'))->toBeEmpty();
});

test('scrolling watermark time is offset by the chunk start', function () {
    $commands = new FfmpegCommands([
        'watermark_text_enabled' => true,
        'watermark_text' => 'Hi',
        'watermark_text_scroll_enabled' => true,
    ]);

    expect($commands->textWatermarkFilter(1920, 1080, 240.0))->toContain('(t+240)');
    expect($commands->textWatermarkFilter(1920, 1080, 0.0))->not->toContain('t+');
});

// ── Re-encoding and stale jobs ──────────────────────────────────────────────

test('encoding missing renditions keeps the video live and skips finished ones', function () {
    onlyProfiles(['360p']);
    $fake = fakeFfmpeg(duration: 60);
    $video = uploadedVideo();
    ProcessVideoJob::dispatchSync($video);

    EncodeProfile::where('name', '480p')->update(['is_active' => true]);
    $fake->commands = [];

    $statuses = [];
    $fake->onRun = function () use ($video, &$statuses) {
        $statuses[] = $video->fresh()->status;
    };

    ProcessVideoJob::dispatchSync($video->fresh());
    $video->refresh();

    expect(array_unique($statuses))->toBe(['processed']);
    expect($fake->commandsFor('chunks/360p'))->toBeEmpty();
    expect($fake->commandsFor('chunks/480p'))->not->toBeEmpty();
    expect($video->qualities_available)->toBe(['360p', '480p', 'original']);
});

test('videos processed before rendition tracking have their existing files adopted', function () {
    onlyProfiles(['360p', '480p']);
    $fake = fakeFfmpeg(duration: 60);
    $video = uploadedVideo(['status' => 'processed', 'qualities_available' => ['360p', 'original']]);
    Storage::disk('public')->put("videos/{$video->slug}/processed/360p.mp4", str_repeat('x', 20480));

    ProcessVideoJob::dispatchSync($video);

    expect($fake->commandsFor('chunks/360p'))->toBeEmpty();
    expect($fake->commandsFor('chunks/480p'))->not->toBeEmpty();
    expect($video->fresh()->qualities_available)->toBe(['360p', '480p', 'original']);
});

test('chunk jobs from a superseded run stand down', function () {
    $fake = fakeFfmpeg();
    $video = uploadedVideo();
    $encoding = VideoEncoding::create([
        'video_id' => $video->id,
        'quality' => '360p',
        'height' => 360,
        'status' => 'queued',
        'run_id' => 'current-run',
    ]);

    EncodeVideoChunkJob::dispatchSync($encoding->id, 'old-run', 0);

    expect($fake->commands)->toBeEmpty();
    expect($encoding->fresh()->status)->toBe('queued');
});

// ── Progress ────────────────────────────────────────────────────────────────

test('chunk progress rolls up into the rendition row', function () {
    $video = uploadedVideo();
    $encoding = VideoEncoding::create([
        'video_id' => $video->id,
        'quality' => '720p',
        'height' => 720,
        'status' => 'processing',
        'chunks_total' => 2,
        'run_id' => 'run-1',
    ]);

    app(RenditionCoordinator::class)->recordChunkProgress($encoding, 0, 80);

    expect($encoding->fresh()->progress)->toBe(40);
    expect($video->fresh()->encodingProgress())->toBe(40);
});

test('the admin progress panel shows per-rendition bars while encoding', function () {
    asAdmin();
    $video = uploadedVideo(['status' => 'processing', 'processing_stage' => 'encoding']);
    VideoEncoding::create(['video_id' => $video->id, 'quality' => '360p', 'height' => 360, 'status' => 'completed', 'progress' => 100, 'run_id' => 'a']);
    VideoEncoding::create(['video_id' => $video->id, 'quality' => '720p', 'height' => 720, 'status' => 'processing', 'progress' => 30, 'chunks_total' => 3, 'chunks_completed' => 1, 'run_id' => 'b']);

    Livewire::test(VideoEncodingProgress::class, ['videoId' => $video->id])
        ->assertSee('Encoding')
        ->assertSee('360p')
        ->assertSee('720p')
        ->assertSee('1/3 chunks')
        ->assertSee('65%');
});

test('the progress panel is hidden and stops polling once a video is done', function () {
    asAdmin();
    $video = uploadedVideo(['status' => 'processed']);
    VideoEncoding::create(['video_id' => $video->id, 'quality' => '360p', 'height' => 360, 'status' => 'completed', 'progress' => 100, 'run_id' => 'a']);

    Livewire::test(VideoEncodingProgress::class, ['videoId' => $video->id])
        ->assertDontSee('ht-encprog-panel')
        ->assertDontSeeHtml('wire:poll');
});

// ── Sprites ─────────────────────────────────────────────────────────────────

test('seek previews are one sprite sheet indexed by xywh fragments', function () {
    onlyProfiles([]);
    $fake = fakeFfmpeg(duration: 120);
    $video = uploadedVideo();

    ProcessVideoJob::dispatchSync($video);

    $spriteCmd = collect($fake->commands)->first(fn ($c) => str_contains($c, 'sprite.jpg'));
    expect($spriteCmd)->toContain('tile=10x3')->toContain('-frames:v 1');

    $vtt = Storage::disk('public')->get("videos/{$video->slug}/scrubber.vtt");
    expect($vtt)->toStartWith('WEBVTT')
        ->toContain("00:00:00.000 --> 00:00:05.000\nsprites/sprite.jpg#xywh=0,0,200,112")
        ->toContain('sprites/sprite.jpg#xywh=200,0,200,112')
        ->toContain('sprites/sprite.jpg#xywh=0,112,200,112');
    expect(substr_count($vtt, '#xywh='))->toBe(24);
});

// ── Admin ───────────────────────────────────────────────────────────────────

test('super admins can manage encoding profiles', function () {
    asAdmin();

    $this->get(EncodeProfileResource::getUrl('index'))
        ->assertOk()
        ->assertSee('1080p');
});

test('admins without the super-admin tier cannot', function () {
    asAdmin(User::factory()->plainAdmin()->create());

    $this->get(EncodeProfileResource::getUrl('index'))->assertForbidden();
});

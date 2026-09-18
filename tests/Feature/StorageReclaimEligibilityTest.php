<?php

use App\Filament\Resources\VideoResource;
use App\Models\Setting;
use App\Models\StorageReclaim;
use App\Models\Video;
use App\Models\VideoEncoding;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/*
|--------------------------------------------------------------------------
| Storage reclaim — what may be re-compressed, and from which file
|--------------------------------------------------------------------------
|
| Re-compressing means replacing a file a live site is serving, on a box whose
| media is not backed up. One predicate decides whether that is allowed, and
| one lookup decides which video a selected file belongs to; the buttons, the
| console command and the tests all go through those two.
|
| The scope is the original upload and nothing else. A rendition or an HLS
| segment resolving to *nothing* is the most important assertion in this file:
| those are the files the player streams.
|
*/

// ── The shared predicate ────────────────────────────────────────────────────

test('a finished video with a local upload can be re-compressed', function () {
    expect(reclaims()->eligibility(processedVideo()))->toBeNull();
});

test('canReencode and the admin action agree on every case', function () {
    // They were two copies of the same condition; the admin's is now a wrapper
    // over the model, so nothing can pass one gate and fail the other.
    $cases = [
        processedVideo(),
        processedVideo(['status' => 'processing']),
        processedVideo(['is_embedded' => true, 'embed_url' => 'https://example.test/v']),
        Video::factory()->create(['status' => 'processed', 'video_path' => 'videos/gone/missing.mp4']),
    ];

    foreach ($cases as $video) {
        expect(VideoResource::canEncodeMissing($video))->toBe($video->canReencode());
    }
});

test('an embedded video has nothing to re-compress', function () {
    $video = processedVideo(['is_embedded' => true, 'embed_url' => 'https://example.test/v']);

    expect(reclaims()->eligibility($video))->toContain('Embedded');
});

test('a video on cloud storage is refused', function () {
    // The source is read from the local disk, and the encoder offloads only
    // after it finishes — there is no local file to work from.
    expect(reclaims()->eligibility(processedVideo(['storage_disk' => 's3'])))
        ->toContain('local storage');
});

test('a video still encoding is refused', function () {
    $video = processedVideo();

    VideoEncoding::create([
        'video_id' => $video->id,
        'quality' => '1080p',
        'status' => VideoEncoding::PROCESSING,
        'run_id' => (string) Str::uuid(),
    ]);

    expect(reclaims()->eligibility($video->fresh()))->toContain('still encoding');
});

test('an unprocessed video is refused', function () {
    expect(reclaims()->eligibility(processedVideo(['status' => 'pending'])))->not->toBeNull();
});

test('a video with no known duration is refused', function () {
    // The acceptance check compares durations, so without one there is no way
    // to tell a good re-encode from a truncated one.
    expect(reclaims()->eligibility(processedVideo(['duration' => 0])))->toContain('duration');
});

test('a configured watermark that has not been applied blocks the reclaim', function () {
    // The watermark is drawn once and .watermark_done records it. Re-encoding
    // the current upload without that marker risks a second watermark drawn
    // over the first.
    Setting::set('watermark_enabled', true, 'general', 'boolean');
    $video = processedVideo();

    expect(reclaims()->eligibility($video))->toContain('watermark');

    Storage::disk('public')->put(dirname($video->video_path).'/.watermark_done', '');

    expect(reclaims()->eligibility($video))->toBeNull();
});

test('a second attempt on the same video is refused while the first is live', function () {
    $video = processedVideo();

    reclaims()->request($video);

    expect(reclaims()->eligibility($video))->toContain('already in progress');
});

test('a running attempt can still re-check its own eligibility', function () {
    // Its own row is active by definition, so without excluding it the answer
    // would mask every check below it — which is how the job re-verifies the
    // state it is about to act on.
    $video = processedVideo();
    $reclaim = reclaims()->request($video)['reclaim'];

    expect(reclaims()->eligibility($video, $reclaim->id))->toBeNull();
});

test('a finished attempt does not block another', function () {
    $video = processedVideo();

    $reclaim = reclaims()->request($video)['reclaim'];
    $reclaim->forceFill(['status' => StorageReclaim::SKIPPED])->save();

    expect(reclaims()->eligibility($video))->toBeNull();
});

// ── Resolving a selected file ───────────────────────────────────────────────

test('the uploaded file resolves to its video', function () {
    $video = processedVideo();

    expect(reclaims()->videoForPath($video->video_path)?->id)->toBe($video->id);
});

test('the files the player streams resolve to nothing', function () {
    // The scope of the whole feature, asserted directly: a rendition MP4, an
    // HLS segment and the master playlist are what playback depends on, so
    // selecting them must never offer to touch them.
    $video = processedVideo();
    $dir = dirname($video->video_path);

    expect(reclaims()->videoForPath($dir.'/processed/720p.mp4'))->toBeNull()
        ->and(reclaims()->videoForPath($dir.'/processed/480p.mp4'))->toBeNull()
        ->and(reclaims()->videoForPath($dir.'/processed/hls/720p/segment_000.ts'))->toBeNull()
        ->and(reclaims()->videoForPath($dir.'/processed/master.m3u8'))->toBeNull();
});

test('artwork and unrelated files resolve to nothing', function () {
    $video = processedVideo();
    $dir = dirname($video->video_path);

    expect(reclaims()->videoForPath($dir.'/thumb_0.webp'))->toBeNull()
        ->and(reclaims()->videoForPath('media/promos/clip.mp4'))->toBeNull()
        ->and(reclaims()->videoForPath('videos/nobody/original.mp4'))->toBeNull()
        ->and(reclaims()->videoForPath('videos'))->toBeNull();
});

test('a soft-deleted video keeps its files out of reach', function () {
    // It can still be restored for 30 days, so its files are not ours.
    $video = processedVideo();
    $path = $video->video_path;
    $video->delete();

    expect(reclaims()->videoForPath($path))->toBeNull();
});

test('a selection resolves to each distinct video once', function () {
    $first = processedVideo();
    $second = processedVideo();
    $dir = dirname($first->video_path);

    $videos = reclaims()->videosForPaths([
        $first->video_path,
        $first->video_path,
        $second->video_path,
        // Neither of these contributes anything.
        $dir.'/processed/720p.mp4',
        $dir.'/processed/hls/720p/segment_000.ts',
    ]);

    expect($videos)->toHaveCount(2)
        ->and(collect($videos)->pluck('id')->sort()->values()->all())
        ->toBe(collect([$first->id, $second->id])->sort()->values()->all());
});

// ── Requesting ──────────────────────────────────────────────────────────────

test('a request records the settings it will encode with', function () {
    Setting::set('ffmpeg_crf', 22, 'general', 'integer');
    Setting::set('reclaim_crf_delta', 3, 'general', 'integer');
    $admin = asAdmin();
    $video = processedVideo();

    $reclaim = reclaims()->request($video)['reclaim'];

    expect($reclaim->status)->toBe(StorageReclaim::PENDING)
        ->and($reclaim->target)->toBe(StorageReclaim::TARGET_ORIGINAL)
        ->and($reclaim->requested_by)->toBe($admin->id)
        ->and($reclaim->run_id)->not->toBeEmpty()
        // Snapshotted, because site settings drift and a saving is
        // uninterpretable without knowing what produced it.
        ->and($reclaim->settings_snapshot['crf'])->toBe(25)
        ->and($reclaim->settings_snapshot['preset'])->toBe('slow');
});

test('a refused request creates no row and says why', function () {
    $result = reclaims()->request(processedVideo(['storage_disk' => 's3']));

    expect($result['reclaim'])->toBeNull()
        ->and($result['reason'])->toContain('local storage')
        ->and(StorageReclaim::count())->toBe(0);
});

test('the original upload is the only target there is', function () {
    // A second target would need its own worker, its own eligibility branch
    // and a statement of what capability it costs. This is the guard that one
    // cannot be added quietly.
    expect(StorageReclaim::TARGETS)->toBe([StorageReclaim::TARGET_ORIGINAL]);
});

test('nothing expires on its own', function () {
    // Accepting deletes an upload permanently, so it only ever happens because
    // a person pressed the button. There is no sweep and no keep_until.
    $reclaim = reclaims()->request(processedVideo())['reclaim'];

    expect(array_key_exists('keep_until', $reclaim->getAttributes()))->toBeFalse()
        ->and(StorageReclaim::TERMINAL)->not->toContain('expired');
});

// ── What a row means ────────────────────────────────────────────────────────

test('a row awaiting review has not saved anything yet', function () {
    // Both copies are on disk while it waits, so reporting it as saved would
    // be a lie about how much disk the box has.
    $reclaim = new StorageReclaim([
        'status' => StorageReclaim::AWAITING_REVIEW,
        'before_bytes' => 1000,
        'after_bytes' => 400,
    ]);

    expect($reclaim->savedBytes())->toBe(600)
        ->and($reclaim->savedPercent())->toBe(60.0)
        ->and($reclaim->savingLabel())->toBe('600 B (60%)')
        ->and($reclaim->isRealised())->toBeFalse();

    $reclaim->status = StorageReclaim::ACCEPTED;
    expect($reclaim->isRealised())->toBeTrue();

    $reclaim->status = StorageReclaim::REVERTED;
    expect($reclaim->isRealised())->toBeFalse();
});

test('a larger result never reads as a negative saving', function () {
    expect((new StorageReclaim(['before_bytes' => 400, 'after_bytes' => 1000]))->savedBytes())->toBe(0);
});

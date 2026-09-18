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
| Storage reclaim — what may be reclaimed, and from which file
|--------------------------------------------------------------------------
|
| Reclaiming means deleting or replacing files a live site is serving, on a
| box whose media is not backed up. One predicate decides whether that is
| allowed, and one parser decides which video a selected file belongs to;
| everything else — the buttons, the bulk actions, the console commands —
| goes through those two. These are the tests for them.
|
*/

// ── The shared predicate ────────────────────────────────────────────────────

test('a finished video with local files can be reclaimed', function () {
    $video = processedVideo();

    expect(reclaims()->eligibility($video, StorageReclaim::TARGET_ORIGINAL))->toBeNull()
        ->and(reclaims()->eligibility($video, StorageReclaim::TARGET_HLS))->toBeNull()
        ->and(reclaims()->eligibility($video, StorageReclaim::TARGET_RENDITION, '720p'))->toBeNull();
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

test('an embedded video has nothing to reclaim', function () {
    $video = processedVideo(['is_embedded' => true, 'embed_url' => 'https://example.test/v']);

    expect(reclaims()->eligibility($video, StorageReclaim::TARGET_ORIGINAL))
        ->toContain('Embedded');
});

test('a video on cloud storage is refused', function () {
    // sourcePath() is local-only, and the encoder offloads only after it
    // finishes — there is no local file to work from.
    $video = processedVideo(['storage_disk' => 's3']);

    expect(reclaims()->eligibility($video, StorageReclaim::TARGET_ORIGINAL))
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

    expect(reclaims()->eligibility($video->fresh(), StorageReclaim::TARGET_ORIGINAL))
        ->toContain('still encoding');
});

test('an unprocessed video is refused', function () {
    $video = processedVideo(['status' => 'pending']);

    expect(reclaims()->eligibility($video, StorageReclaim::TARGET_ORIGINAL))->not->toBeNull();
});

test('a video with no known duration is refused', function () {
    // The acceptance check compares durations, so without one there is no way
    // to tell a good re-encode from a truncated one.
    $video = processedVideo(['duration' => 0]);

    expect(reclaims()->eligibility($video, StorageReclaim::TARGET_ORIGINAL))
        ->toContain('duration');
});

test('a configured watermark that has not been applied blocks the original', function () {
    // The watermark is drawn once and .watermark_done records it. Re-encoding
    // the current original without that marker risks a second watermark drawn
    // over the first.
    Setting::set('watermark_enabled', true, 'general', 'boolean');
    $video = processedVideo();

    expect(reclaims()->eligibility($video, StorageReclaim::TARGET_ORIGINAL))
        ->toContain('watermark');

    Storage::disk('public')->put(dirname($video->video_path).'/.watermark_done', '');

    expect(reclaims()->eligibility($video, StorageReclaim::TARGET_ORIGINAL))->toBeNull();
});

test('a second reclaim of the same file is refused while the first is live', function () {
    $video = processedVideo();

    reclaims()->request($video, StorageReclaim::TARGET_HLS);

    expect(reclaims()->eligibility($video, StorageReclaim::TARGET_HLS))
        ->toContain('already in progress')
        // A different target is unaffected.
        ->and(reclaims()->eligibility($video, StorageReclaim::TARGET_ORIGINAL))->toBeNull();
});

test('a finished reclaim does not block another attempt', function () {
    $video = processedVideo();

    $reclaim = reclaims()->request($video, StorageReclaim::TARGET_HLS)['reclaim'];
    $reclaim->forceFill(['status' => StorageReclaim::SKIPPED])->save();

    expect(reclaims()->eligibility($video, StorageReclaim::TARGET_HLS))->toBeNull();
});

// ── Per-target checks ───────────────────────────────────────────────────────

test('a rendition with no completed encode is refused', function () {
    $video = processedVideo();

    expect(reclaims()->eligibility($video, StorageReclaim::TARGET_RENDITION, '1080p'))
        ->toContain('no completed 1080p encode');
});

test('a rendition whose file has gone is refused', function () {
    $video = processedVideo();
    Storage::disk('public')->delete(dirname($video->video_path).'/processed/720p.mp4');

    expect(reclaims()->eligibility($video, StorageReclaim::TARGET_RENDITION, '720p'))
        ->toContain('missing from disk');
});

test('a rendition target needs a quality', function () {
    $video = processedVideo();

    expect(reclaims()->eligibility($video, StorageReclaim::TARGET_RENDITION))
        ->toContain('Pick a rendition');
});

test('HLS cannot be dropped when it is the only copy of a quality', function () {
    // The one path in the feature that could make a video unplayable: HLS is
    // the sole copy of any quality whose MP4 has been lost.
    $video = processedVideo();
    Storage::disk('public')->delete(dirname($video->video_path).'/processed/480p.mp4');

    expect(reclaims()->eligibility($video, StorageReclaim::TARGET_HLS))
        ->toContain('only copy');
});

test('a truncated rendition does not count as a copy', function () {
    // exists() is perfectly happy with the header-only file a killed ffmpeg
    // leaves behind.
    $video = processedVideo();
    Storage::disk('public')->put(dirname($video->video_path).'/processed/480p.mp4', 'tiny');

    expect(reclaims()->eligibility($video, StorageReclaim::TARGET_HLS))
        ->toContain('only copy');
});

test('a video with no HLS copy has nothing to reclaim there', function () {
    $video = processedVideo();
    Storage::disk('public')->deleteDirectory(dirname($video->video_path).'/processed/hls');

    expect(reclaims()->eligibility($video, StorageReclaim::TARGET_HLS))
        ->toContain('no HLS copy');
});

test('an unknown target is refused', function () {
    expect(reclaims()->eligibility(processedVideo(), 'everything'))->toContain('Unknown');
});

// ── Resolving a selected file ───────────────────────────────────────────────

test('a file selected in the library resolves to the reclaim it implies', function () {
    $video = processedVideo();
    $dir = dirname($video->video_path);

    expect(reclaims()->targetForPath($video->video_path))
        ->toMatchArray(['target' => StorageReclaim::TARGET_ORIGINAL, 'quality' => null]);

    expect(reclaims()->targetForPath($dir.'/processed/720p.mp4'))
        ->toMatchArray(['target' => StorageReclaim::TARGET_RENDITION, 'quality' => '720p']);

    expect(reclaims()->targetForPath($dir.'/processed/hls/720p/segment_000.ts'))
        ->toMatchArray(['target' => StorageReclaim::TARGET_HLS, 'quality' => null]);
});

test('files that are not reclaimable resolve to nothing', function () {
    $video = processedVideo();
    $dir = dirname($video->video_path);

    // Artwork, the scrubber track, the master playlist, another root, and a
    // directory that belongs to no video.
    expect(reclaims()->targetForPath($dir.'/thumb_0.webp'))->toBeNull()
        ->and(reclaims()->targetForPath($dir.'/processed/master.m3u8'))->toBeNull()
        ->and(reclaims()->targetForPath('media/promos/clip.mp4'))->toBeNull()
        ->and(reclaims()->targetForPath('videos/nobody/original.mp4'))->toBeNull()
        ->and(reclaims()->targetForPath('videos'))->toBeNull();
});

test('a soft-deleted video keeps its files out of reach', function () {
    // It can still be restored for 30 days, so its files are not ours.
    $video = processedVideo();
    $path = $video->video_path;
    $video->delete();

    expect(reclaims()->targetForPath($path))->toBeNull();
});

test('a selection of many files collapses into the reclaims it implies', function () {
    $video = processedVideo();
    $dir = dirname($video->video_path);

    $paths = [
        $dir.'/processed/hls/720p/segment_000.ts',
        $dir.'/processed/hls/720p/segment_001.ts',
        $dir.'/processed/hls/480p/segment_000.ts',
        $dir.'/processed/720p.mp4',
        $dir.'/thumb_0.webp',
    ];

    $targets = reclaims()->targetsForPaths($paths);

    // Three HLS segments are one tree, not three reclaims.
    expect($targets)->toHaveCount(2)
        ->and(collect($targets)->pluck('target')->sort()->values()->all())
        ->toBe([StorageReclaim::TARGET_HLS, StorageReclaim::TARGET_RENDITION]);
});

// ── Requesting ──────────────────────────────────────────────────────────────

test('a request records what it will do and when it stops holding the old file', function () {
    Setting::set('ffmpeg_crf', 22, 'general', 'integer');
    Setting::set('reclaim_crf_delta', 3, 'general', 'integer');
    $admin = asAdmin();
    $video = processedVideo();

    $reclaim = reclaims()->request($video, StorageReclaim::TARGET_RENDITION, '720p')['reclaim'];

    expect($reclaim->status)->toBe(StorageReclaim::PENDING)
        ->and($reclaim->requested_by)->toBe($admin->id)
        ->and($reclaim->quality)->toBe('720p')
        ->and($reclaim->run_id)->not->toBeEmpty()
        // Snapshotted, because site settings drift and a saving is
        // uninterpretable without knowing what produced it.
        ->and($reclaim->settings_snapshot['crf'])->toBe(25)
        ->and($reclaim->settings_snapshot['preset'])->toBe('slow')
        ->and($reclaim->keep_until)->not->toBeNull();
});

test('a refused request creates no row and says why', function () {
    $video = processedVideo(['storage_disk' => 's3']);

    $result = reclaims()->request($video, StorageReclaim::TARGET_ORIGINAL);

    expect($result['reclaim'])->toBeNull()
        ->and($result['reason'])->toContain('local storage')
        ->and(StorageReclaim::count())->toBe(0);
});

test('the quality is not recorded for targets that have none', function () {
    $video = processedVideo();

    $reclaim = reclaims()->request($video, StorageReclaim::TARGET_HLS, '720p')['reclaim'];

    expect($reclaim->quality)->toBeNull();
});

// ── What a row means ────────────────────────────────────────────────────────

test('a row awaiting review has not saved anything yet', function () {
    // Bytes held by a kept file are not free, and reporting them as saved
    // would be a lie about how much disk the box has.
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

    // An auto-accept frees the bytes just as much as a manual one.
    $reclaim->status = StorageReclaim::EXPIRED;
    expect($reclaim->isRealised())->toBeTrue();

    // A reverted row freed nothing.
    $reclaim->status = StorageReclaim::REVERTED;
    expect($reclaim->isRealised())->toBeFalse();
});

test('a larger result never reads as a negative saving', function () {
    $reclaim = new StorageReclaim(['before_bytes' => 400, 'after_bytes' => 1000]);

    expect($reclaim->savedBytes())->toBe(0);
});

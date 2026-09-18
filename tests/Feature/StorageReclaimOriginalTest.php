<?php

use App\Models\MediaFile;
use App\Models\Setting;
use App\Models\StorageReclaim;
use Illuminate\Support\Facades\Storage;

/*
|--------------------------------------------------------------------------
| Storage reclaim — re-compressing the original upload
|--------------------------------------------------------------------------
|
| The original is the biggest file a video owns and is served three ways: the
| player's fallback source, "original" in the quality menu, and the Pro
| download. So every test here asks the same question in a different way —
| what is the site serving right now? Until an admin accepts, the answer must
| be "exactly the file it was serving before", whatever happened to the
| re-encode.
|
*/

/** A processed video whose original is big enough to be worth shrinking. */
function videoWithBigOriginal(int $bytes = 900_000)
{
    $video = encodedVideo();

    Storage::disk('public')->put($video->video_path, str_repeat('O', $bytes));
    $video->forceFill(['size' => $bytes])->saveQuietly();

    return $video->fresh();
}

// ── Where the output goes ───────────────────────────────────────────────────

test('the re-encode lands at a new filename beside the original', function () {
    $fake = fakeFfmpeg(duration: 60, height: 720, width: 1280);
    $video = videoWithBigOriginal();
    $fake->outputBytes = 300_000;

    $reclaim = reclaims()->requestAndStart($video, StorageReclaim::TARGET_ORIGINAL)['reclaim']->fresh();

    expect($reclaim->new_path)->toBe(dirname($video->video_path).'/clip_r'.$reclaim->id.'.mp4')
        // A new name sidesteps nginx's 30-day mp4 cache, which has no
        // Cloudflare purge behind it, and *is* the kept-file requirement.
        ->and($reclaim->new_path)->not->toBe($video->video_path)
        // Never here: complete() swaps a completed `original` encoding row
        // over video_path, so a file at that path would be picked up later by
        // an unrelated re-encode.
        ->and($reclaim->new_path)->not->toContain('processed/original_watermarked')
        ->and(Storage::disk('public')->exists($reclaim->new_path))->toBeTrue();
});

test('nothing about the video changes before it is accepted', function () {
    $fake = fakeFfmpeg(duration: 60, height: 720, width: 1280);
    $video = videoWithBigOriginal();
    $fake->outputBytes = 300_000;

    $reclaim = reclaims()->requestAndStart($video, StorageReclaim::TARGET_ORIGINAL)['reclaim']->fresh();
    $after = $video->fresh();

    expect($reclaim->status)->toBe(StorageReclaim::AWAITING_REVIEW)
        // The column, the file, the size and the quality menu are all as they
        // were: the site is still serving the original upload.
        ->and($after->video_path)->toBe($video->video_path)
        ->and(Storage::disk('public')->size($after->video_path))->toBe(900_000)
        ->and($after->size)->toBe(900_000)
        ->and($after->quality_urls['original'])->toContain(basename($video->video_path))
        // No cache-bust needed, because nothing a viewer can reach moved.
        ->and($after->media_version)->toBe(0);
});

test('the command re-compresses rather than re-encoding the audio or the keyframes', function () {
    $fake = fakeFfmpeg(duration: 60, height: 720, width: 1280);
    $video = videoWithBigOriginal();
    $fake->outputBytes = 300_000;
    $fake->commands = [];

    reclaims()->requestAndStart($video, StorageReclaim::TARGET_ORIGINAL);

    $encode = collect($fake->commands)->first(fn ($c) => str_contains($c, 'libx264'));

    expect($encode)->toContain('slow')
        ->and($encode)->toContain('-crf 24')
        ->and($encode)->toContain('-c:a copy')
        // Pure waste on a file that is neither chunked nor HLS-packaged.
        ->and($encode)->not->toContain('-force_key_frames')
        // Still progressive-download friendly.
        ->and($encode)->toContain('-movflags +faststart');
});

// ── Accepting ───────────────────────────────────────────────────────────────

test('accepting repoints the video and deletes the upload', function () {
    $fake = fakeFfmpeg(duration: 60, height: 720, width: 1280);
    $video = videoWithBigOriginal();
    $fake->outputBytes = 300_000;

    $reclaim = reclaims()->requestAndStart($video, StorageReclaim::TARGET_ORIGINAL)['reclaim']->fresh();
    $old = $video->video_path;

    expect(reclaims()->accept($reclaim))->toBeTrue();

    $after = $video->fresh();

    expect($after->video_path)->toBe($reclaim->new_path)
        ->and(Storage::disk('public')->exists($old))->toBeFalse()
        ->and($after->size)->toBe(300_000)
        ->and($after->quality_urls['original'])->toContain(basename($reclaim->new_path));
});

test('the markers that gate re-processing survive', function () {
    // .watermark_done and .faststart_done are directory-level and record work
    // that must not be repeated; a reclaim touches one file, never the folder.
    $fake = fakeFfmpeg(duration: 60, height: 720, width: 1280);
    $video = videoWithBigOriginal();
    $fake->outputBytes = 300_000;
    $dir = dirname($video->video_path);
    Storage::disk('public')->put($dir.'/.watermark_done', '');

    $reclaim = reclaims()->requestAndStart($video, StorageReclaim::TARGET_ORIGINAL)['reclaim']->fresh();
    reclaims()->accept($reclaim);

    expect(Storage::disk('public')->exists($dir.'/.watermark_done'))->toBeTrue()
        ->and(Storage::disk('public')->exists($dir.'/.faststart_done'))->toBeTrue()
        // And the renditions are untouched throughout.
        ->and(Storage::disk('public')->exists($dir.'/processed/480p.mp4'))->toBeTrue();
});

test('the re-compressed file is in use, so the library will not offer to delete it', function () {
    // Every reclaim write is saveQuietly(), which skips the observer that
    // maintains that flag — so it has to be synced explicitly.
    $fake = fakeFfmpeg(duration: 60, height: 720, width: 1280);
    $video = videoWithBigOriginal();
    $fake->outputBytes = 300_000;

    $reclaim = reclaims()->requestAndStart($video, StorageReclaim::TARGET_ORIGINAL)['reclaim']->fresh();
    reclaims()->accept($reclaim);

    $this->artisan('media:index --full');

    expect(MediaFile::where('path', $reclaim->new_path)->value('is_referenced'))->toBeTruthy();
});

// ── Every way it can be refused ─────────────────────────────────────────────

dataset('bad results', [
    'a bigger file' => [1_200_000, 60.0, 'threshold'],
    'a saving below the threshold' => [850_000, 60.0, 'threshold'],
    'a truncated file' => [4096, 60.0, 'too small'],
    'a drifted duration' => [300_000, 52.0, 'drifted'],
]);

test('a result that cannot be trusted is discarded and the live file left alone', function (int $bytes, float $duration, string $expected) {
    $fake = fakeFfmpeg(duration: 60, height: 720, width: 1280);
    $video = videoWithBigOriginal();
    $fake->outputBytes = $bytes;

    // The probe only starts reporting the new length once encoding begins, so
    // before_duration_ms is still taken from the video as it was.
    $fake->onRun = function (string $command) use ($fake, $duration) {
        if (str_contains($command, 'libx264')) {
            $fake->duration = $duration;
        }
    };

    $reclaim = reclaims()->requestAndStart($video, StorageReclaim::TARGET_ORIGINAL)['reclaim']->fresh();
    $after = $video->fresh();

    expect($reclaim->status)->toBe(StorageReclaim::SKIPPED)
        ->and($reclaim->error)->toContain($expected)
        // The three things that would break if this were wrong.
        ->and($after->video_path)->toBe($video->video_path)
        ->and(Storage::disk('public')->size($after->video_path))->toBe(900_000)
        ->and(Storage::disk('public')->exists($reclaim->new_path ?? 'nothing'))->toBeFalse();
})->with('bad results');

test('a failed encode leaves no half-written file behind', function () {
    $fake = fakeFfmpeg(duration: 60, height: 720, width: 1280);
    $video = videoWithBigOriginal();
    $fake->failWhen = fn (string $command) => str_contains($command, '_r');

    $reclaim = reclaims()->requestAndStart($video, StorageReclaim::TARGET_ORIGINAL)['reclaim']->fresh();

    expect($reclaim->status)->toBe(StorageReclaim::FAILED)
        ->and($reclaim->error)->toContain('exited with code')
        ->and($video->fresh()->video_path)->toBe($video->video_path)
        ->and(Storage::disk('public')->size($video->video_path))->toBe(900_000);
});

test('a host with no ffmpeg skips rather than failing', function () {
    Setting::set('ffmpeg_enabled', false, 'general', 'boolean');
    fakeFfmpeg(duration: 60, height: 720, width: 1280);
    $video = videoWithBigOriginal();

    $reclaim = reclaims()->requestAndStart($video, StorageReclaim::TARGET_ORIGINAL)['reclaim']->fresh();

    expect($reclaim->status)->toBe(StorageReclaim::SKIPPED)
        ->and($reclaim->error)->toContain('FFmpeg is not available');
});

// ── Reverting ───────────────────────────────────────────────────────────────

test('reverting discards the candidate and keeps the upload', function () {
    $fake = fakeFfmpeg(duration: 60, height: 720, width: 1280);
    $video = videoWithBigOriginal();
    $fake->outputBytes = 300_000;

    $reclaim = reclaims()->requestAndStart($video, StorageReclaim::TARGET_ORIGINAL)['reclaim']->fresh();

    expect(reclaims()->revert($reclaim))->toBeTrue()
        ->and(Storage::disk('public')->exists($reclaim->new_path))->toBeFalse()
        ->and($video->fresh()->video_path)->toBe($video->video_path)
        ->and(Storage::disk('public')->size($video->video_path))->toBe(900_000);
});

test('every reclaim target has a worker', function () {
    // start() refuses a target with no job behind it rather than leaving the
    // row looking queued forever. This is the guard that a new target cannot
    // be added to the ledger without one.
    fakeFfmpeg(duration: 60, height: 720, width: 1280);

    foreach (StorageReclaim::TARGETS as $target) {
        $video = $target === StorageReclaim::TARGET_ORIGINAL ? videoWithBigOriginal() : encodedVideo();
        $quality = $target === StorageReclaim::TARGET_RENDITION ? '480p' : null;

        $reclaim = reclaims()->request($video, $target, $quality)['reclaim'];

        expect($reclaim)->not->toBeNull()
            ->and($reclaim->status)->not->toBe(StorageReclaim::FAILED);
    }
});

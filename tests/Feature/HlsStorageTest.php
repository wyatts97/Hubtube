<?php

use App\Jobs\CompressMediaFileJob;
use App\Jobs\ProcessVideoJob;
use App\Models\Setting;
use App\Models\VideoEncoding;
use App\Services\Encoding\FfmpegCommands;
use App\Services\Encoding\HlsPackager;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

/*
|--------------------------------------------------------------------------
| One stored copy of each rendition
|--------------------------------------------------------------------------
|
| HLS segments used to be a `-c copy` of the rendition MP4 beside them, so
| every quality was stored twice — measured on a real library, 21.0 GB of
| MP4s against 21.7 GB of the same bytes as segments. The MP4 is now deleted
| once its stream is proven to play, and only then.
|
*/

beforeEach(function () {
    asAdmin();
});

function processedDirOf($video): string
{
    return Storage::disk('public')->path("videos/{$video->slug}/processed");
}

test('a rendition is stored once: its HLS stream, not the MP4 as well', function () {
    onlyProfiles(['360p', '480p']);
    fakeFfmpeg(duration: 60, height: 720, width: 1280);
    $video = uploadedVideo();

    ProcessVideoJob::dispatchSync($video);

    $processed = processedDirOf($video);

    foreach (['360p', '480p'] as $quality) {
        expect(file_exists("{$processed}/{$quality}.mp4"))->toBeFalse()
            ->and(HlsPackager::isPackaged($processed, $quality))->toBeTrue();
    }

    // Still watchable: the master lists every quality.
    expect(file_get_contents("{$processed}/master.m3u8"))
        ->toContain('hls/360p/playlist.m3u8')
        ->toContain('hls/480p/playlist.m3u8');

    // And the rows report the stream's size, not a file that no longer exists.
    expect($video->fresh()->encodings()->where('quality', '360p')->value('size'))->toBeGreaterThan(0);
});

test('segments are packaged into one file per rendition, not hundreds', function () {
    onlyProfiles(['360p']);
    $fake = fakeFfmpeg(duration: 60);
    $video = uploadedVideo();

    ProcessVideoJob::dispatchSync($video);

    // 19,280 segment files on the measured library became one per rendition.
    expect(collect($fake->commandsFor('-f hls'))->first())->toContain('single_file');
});

test('verification refuses a stream whose length does not match the rendition', function () {
    // A truncated package can still exit 0, and nothing may be deleted on the
    // strength of an exit code alone.
    onlyProfiles(['360p']);
    fakeFfmpeg(duration: 60);
    $video = uploadedVideo();
    ProcessVideoJob::dispatchSync($video);

    $processed = processedDirOf($video);
    $packager = app(HlsPackager::class);
    $commands = new FfmpegCommands(Setting::getAll());

    expect($packager->verifyPackaged($commands, $processed, '360p', 60.0))->toBeNull()
        ->and($packager->verifyPackaged($commands, $processed, '360p', 900.0))
        ->toContain('against the rendition');
});

test('verification refuses a missing playlist or an empty stream', function () {
    onlyProfiles(['360p']);
    fakeFfmpeg(duration: 60);
    $video = uploadedVideo();
    ProcessVideoJob::dispatchSync($video);

    $packager = app(HlsPackager::class);
    $commands = new FfmpegCommands(Setting::getAll());
    $processed = processedDirOf($video);

    expect($packager->verifyPackaged($commands, $processed, '720p', 60.0))->toBe('the playlist is missing');

    array_map('unlink', glob("{$processed}/hls/360p/*.ts") ?: []);
    expect($packager->verifyPackaged($commands, $processed, '360p', 60.0))->toBe('no segment data was written');
});

test('with HLS switched off the MP4 ladder is kept, because it is all there is', function () {
    Setting::set('generate_hls', false, 'general', 'boolean');
    Setting::clearCache();
    onlyProfiles(['360p']);
    fakeFfmpeg(duration: 60);
    $video = uploadedVideo();

    ProcessVideoJob::dispatchSync($video);

    expect(file_exists(processedDirOf($video).'/360p.mp4'))->toBeTrue();
});

test('a re-run does not re-encode a rendition whose MP4 has been deleted', function () {
    // The MP4's absence used to mean "missing rendition", which would have
    // re-encoded the whole ladder on every pass once they were deleted.
    onlyProfiles(['360p']);
    $fake = fakeFfmpeg(duration: 60);
    $video = uploadedVideo();
    ProcessVideoJob::dispatchSync($video);

    $fake->commands = [];
    ProcessVideoJob::dispatchSync($video->fresh());

    expect($fake->commandsFor('chunks/360p'))->toBeEmpty()
        ->and($video->fresh()->encodings()->where('quality', '360p')->value('status'))
        ->toBe(VideoEncoding::COMPLETED);
});

test('a legacy video with only HLS on disk is adopted rather than re-encoded', function () {
    onlyProfiles(['360p', '480p']);
    $fake = fakeFfmpeg(duration: 60);
    $video = uploadedVideo(['status' => 'processed', 'qualities_available' => ['360p', 'original']]);

    // Packaged, MP4 already reclaimed by the backfill.
    $hlsDir = processedDirOf($video).'/hls/360p';
    mkdir($hlsDir, 0755, true);
    file_put_contents("{$hlsDir}/playlist.m3u8", "#EXTM3U\n");
    file_put_contents("{$hlsDir}/stream.ts", str_repeat('t', 20480));

    ProcessVideoJob::dispatchSync($video);

    expect($fake->commandsFor('chunks/360p'))->toBeEmpty()
        ->and($fake->commandsFor('chunks/480p'))->not->toBeEmpty()
        ->and($video->fresh()->qualities_available)->toBe(['360p', '480p', 'original']);
});

// ── The master ──────────────────────────────────────────────────────────────

test('a finished video has its upload compressed and swapped in', function () {
    Cache::put('media-compress:encoders', ['libsvtav1'], 600);
    onlyProfiles(['360p']);
    $fake = fakeFfmpeg(duration: 60);

    // The compressed master has to come out genuinely smaller, or the service
    // refuses to swap it in — which is the behaviour the next test relies on.
    $fake->onRun = function (string $command) use ($fake) {
        $fake->outputBytes = str_contains($command, 'libsvtav1') ? 16384 : 65536;
    };

    $video = uploadedVideo();
    $original = $video->video_path;

    ProcessVideoJob::dispatchSync($video);
    $video->refresh();

    expect($video->video_path)->toBe(dirname($original).'/'.pathinfo($original, PATHINFO_FILENAME).'.av1.mp4')
        ->and(Storage::disk('public')->exists($video->video_path))->toBeTrue()
        ->and(Storage::disk('public')->exists($original))->toBeFalse()
        ->and((int) $video->size)->toBe(Storage::disk('public')->size($video->video_path));
});

test('an already compressed master is never compressed again', function () {
    Cache::put('media-compress:encoders', ['libsvtav1'], 600);
    onlyProfiles(['360p']);
    $fake = fakeFfmpeg(duration: 60);
    $fake->onRun = function (string $command) use ($fake) {
        $fake->outputBytes = str_contains($command, 'libsvtav1') ? 16384 : 65536;
    };
    $video = uploadedVideo();

    ProcessVideoJob::dispatchSync($video);
    $fake->commands = [];

    // A second pass (encode missing renditions) must not re-encode the master:
    // every pass would cost quality.
    ProcessVideoJob::dispatchSync($video->fresh());

    expect(collect($fake->commands)->filter(fn ($c) => str_contains($c, 'libsvtav1')))->toBeEmpty()
        ->and($video->fresh()->video_path)->not->toContain('.av1.av1.');
});

test('a video offloaded to the cloud is left alone', function () {
    Cache::put('media-compress:encoders', ['libsvtav1'], 600);
    Queue::fake([CompressMediaFileJob::class]);
    onlyProfiles(['360p']);
    fakeFfmpeg(duration: 60);
    $video = uploadedVideo(['storage_disk' => 's3']);

    ProcessVideoJob::dispatchSync($video);

    Queue::assertNotPushed(CompressMediaFileJob::class);
});

test('downloads and progressive playback fall back to the master', function () {
    onlyProfiles(['360p']);
    fakeFfmpeg(duration: 60);
    $video = uploadedVideo();
    ProcessVideoJob::dispatchSync($video);
    $video->refresh();

    // No rendition MP4s left, so both resolve to the upload itself.
    expect($video->bestDownloadPath())->toBe($video->video_path)
        ->and(array_keys($video->quality_urls))->toBe(['original']);
});

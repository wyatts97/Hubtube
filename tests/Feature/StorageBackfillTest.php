<?php

use App\Jobs\CompressMediaFileJob;
use App\Jobs\ProcessVideoJob;
use App\Models\Setting;
use App\Models\Video;
use App\Services\Encoding\HlsPackager;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

/*
|--------------------------------------------------------------------------
| Backfilling an existing library
|--------------------------------------------------------------------------
|
| Both commands delete or replace files on a box with no media backups, so
| both are dry runs until --apply, and neither removes anything it has not
| first proven is safe to remove.
|
*/

beforeEach(function () {
    asAdmin();
    Cache::put('media-compress:encoders', ['libsvtav1', 'libx265'], 600);
});

/** A video packaged the old way: HLS segments beside the rendition MP4. */
function legacyPackagedVideo(int $mp4Bytes = 65536): Video
{
    $video = uploadedVideo(['status' => 'processed', 'duration' => 60, 'qualities_available' => ['360p', 'original']]);
    $processed = Storage::disk('public')->path("videos/{$video->slug}/processed");

    mkdir("{$processed}/hls/360p", 0755, true);
    file_put_contents("{$processed}/360p.mp4", str_repeat('m', $mp4Bytes));
    file_put_contents("{$processed}/hls/360p/playlist.m3u8", "#EXTM3U\n");

    foreach (range(0, 9) as $i) {
        file_put_contents(sprintf('%s/hls/360p/segment_%03d.ts', $processed, $i), str_repeat('t', 6000));
    }

    $video->encodings()->create([
        'quality' => '360p',
        'height' => 360,
        'status' => 'completed',
        'progress' => 100,
        'run_id' => (string) Str::uuid(),
        'size' => $mp4Bytes,
    ]);

    return $video->fresh();
}

test('repack-hls reports what it would reclaim and changes nothing', function () {
    fakeFfmpeg(duration: 60);
    $video = legacyPackagedVideo();

    $this->artisan('videos:repack-hls')
        ->expectsOutputToContain('Would reclaim')
        ->assertSuccessful();

    expect(file_exists(Storage::disk('public')->path("videos/{$video->slug}/processed/360p.mp4")))->toBeTrue();
});

test('repack-hls stores the rendition once and reclaims the MP4', function () {
    fakeFfmpeg(duration: 60);
    $video = legacyPackagedVideo();
    $processed = Storage::disk('public')->path("videos/{$video->slug}/processed");

    $this->artisan('videos:repack-hls --apply')->assertSuccessful();

    expect(file_exists("{$processed}/360p.mp4"))->toBeFalse()
        ->and(HlsPackager::isPackaged($processed, '360p'))->toBeTrue()
        // The hundreds of segment files collapse into one.
        ->and(glob("{$processed}/hls/360p/segment_*.ts"))->toBeEmpty()
        ->and($video->encodings()->where('quality', '360p')->value('size'))->toBeGreaterThan(0);
});

test('repack-hls keeps the MP4 when the stream cannot be verified', function () {
    // The packaged stream probes at 60s; this video claims 900s, so the two
    // disagree and nothing may be deleted.
    fakeFfmpeg(duration: 60);
    $video = legacyPackagedVideo();
    $video->update(['duration' => 900]);

    $this->artisan('videos:repack-hls --apply')->assertSuccessful();

    expect(file_exists(Storage::disk('public')->path("videos/{$video->slug}/processed/360p.mp4")))->toBeTrue();
});

test('repack-hls refuses to run when HLS is switched off', function () {
    Setting::set('generate_hls', false, 'general', 'boolean');
    Setting::clearCache();

    $this->artisan('videos:repack-hls --apply')->assertFailed();
});

test('repack-hls leaves a video alone when its upload has gone', function () {
    // Its renditions are then the only progressive copy — bestDownloadPath()
    // falls back to them — so they are not a duplicate of anything.
    fakeFfmpeg(duration: 60);
    $video = legacyPackagedVideo();
    Storage::disk('public')->delete($video->video_path);

    $this->artisan('videos:repack-hls --apply')->assertSuccessful();

    expect(file_exists(Storage::disk('public')->path("videos/{$video->slug}/processed/360p.mp4")))->toBeTrue();
});

// ── The import backlog ──────────────────────────────────────────────────────

test('encode-backlog queues imported videos that have no ladder', function () {
    Bus::fake([ProcessVideoJob::class]);

    $import = uploadedVideo(['status' => 'processed', 'qualities_available' => ['original'], 'views_count' => 500]);
    $done = legacyPackagedVideo();

    $this->artisan('videos:encode-backlog --apply --limit=5')->assertSuccessful();

    Bus::assertDispatched(ProcessVideoJob::class, 1);
    Bus::assertDispatched(ProcessVideoJob::class, fn ($job) => $job->video->is($import));
    Bus::assertNotDispatched(ProcessVideoJob::class, fn ($job) => $job->video->is($done));
});

test('encode-backlog is a dry run by default and respects the limit', function () {
    Bus::fake([ProcessVideoJob::class]);

    foreach (range(1, 3) as $i) {
        uploadedVideo(['status' => 'processed', 'qualities_available' => ['original'], 'views_count' => $i * 100]);
    }

    $this->artisan('videos:encode-backlog')->expectsOutputToContain('Would queue')->assertSuccessful();
    Bus::assertNothingDispatched();

    $this->artisan('videos:encode-backlog --apply --limit=2')->assertSuccessful();
    Bus::assertDispatched(ProcessVideoJob::class, 2);
});

test('encode-backlog refuses to run into a watermark by accident', function () {
    // Imports have no .watermark_done marker, and some already carry the old
    // site's burnt-in watermark.
    Bus::fake([ProcessVideoJob::class]);
    Setting::set('watermark_enabled', true, 'general', 'boolean');
    Setting::clearCache();
    uploadedVideo(['status' => 'processed', 'qualities_available' => ['original']]);

    $this->artisan('videos:encode-backlog --apply')->assertFailed();
    Bus::assertNothingDispatched();

    $this->artisan('videos:encode-backlog --apply --watermark')->assertSuccessful();
    Bus::assertDispatched(ProcessVideoJob::class, 1);
});

test('encoding an import keeps the thumbnail it arrived with', function () {
    // Imports carry their own poster under a name this pipeline does not
    // generate, so re-encoding one must not swap it for an auto-grabbed frame
    // — that would change the poster on every imported video at once.
    onlyProfiles(['360p']);
    fakeFfmpeg(duration: 60);

    $video = uploadedVideo(['status' => 'processed', 'qualities_available' => ['original']]);
    Storage::disk('public')->put("videos/{$video->slug}/thumbnail.jpg", 'chosen');
    $video->forceFill(['thumbnail' => "videos/{$video->slug}/thumbnail.jpg"])->saveQuietly();

    ProcessVideoJob::dispatchSync($video->fresh());

    expect($video->fresh()->thumbnail)->toBe("videos/{$video->slug}/thumbnail.jpg");
});

test('a video with no thumbnail still gets one from the encoder', function () {
    onlyProfiles(['360p']);
    fakeFfmpeg(duration: 60);

    $video = uploadedVideo(['thumbnail' => null]);

    ProcessVideoJob::dispatchSync($video);

    expect($video->fresh()->thumbnail)->toContain("videos/{$video->slug}/");
});

test('encode-backlog skips videos whose upload has gone', function () {
    Bus::fake([ProcessVideoJob::class]);

    $video = uploadedVideo(['status' => 'processed', 'qualities_available' => ['original']]);
    Storage::disk('public')->delete($video->video_path);

    $this->artisan('videos:encode-backlog --apply')->assertSuccessful();

    Bus::assertNothingDispatched();
});

/** A video that already streams from HLS, so its upload is a spare copy. */
function streamedVideo(array $attributes = []): Video
{
    $video = uploadedVideo($attributes);
    $hlsDir = Storage::disk('public')->path("videos/{$video->slug}/processed/hls/360p");

    mkdir($hlsDir, 0755, true);
    file_put_contents("{$hlsDir}/playlist.m3u8", "#EXTM3U\n");
    file_put_contents("{$hlsDir}/stream.ts", str_repeat('t', 20480));

    return $video;
}

test('compress-originals queues the biggest uploads, largest first', function () {
    Queue::fake([CompressMediaFileJob::class]);

    $small = streamedVideo(['status' => 'processed', 'size' => 30 * 1024 * 1024]);
    $big = streamedVideo(['status' => 'processed', 'size' => 900 * 1024 * 1024]);

    $this->artisan('videos:compress-originals --apply --limit=1')->assertSuccessful();

    Queue::assertPushed(CompressMediaFileJob::class, 1);
    Queue::assertPushed(CompressMediaFileJob::class, fn ($job) => $job->sourcePath === $big->video_path
        && $job->replaceOriginal === true
        && $job->codec === 'av1');
    Queue::assertNotPushed(CompressMediaFileJob::class, fn ($job) => $job->sourcePath === $small->video_path);
});

test('compress-originals refuses a video whose upload is its only playable copy', function () {
    // An import has no HLS ladder, so the player streams the upload itself.
    // Re-encoding it to AV1 would leave anyone without that decoder unable to
    // watch it — those go through videos:encode-backlog first.
    Queue::fake([CompressMediaFileJob::class]);
    uploadedVideo(['status' => 'processed', 'size' => 900 * 1024 * 1024, 'qualities_available' => ['original']]);

    $this->artisan('videos:compress-originals --apply')->assertSuccessful();

    Queue::assertNothingPushed();
});

test('compress-originals is a dry run by default', function () {
    Queue::fake([CompressMediaFileJob::class]);
    streamedVideo(['status' => 'processed', 'size' => 900 * 1024 * 1024]);

    $this->artisan('videos:compress-originals')
        ->expectsOutputToContain('Would queue')
        ->assertSuccessful();

    Queue::assertNothingPushed();
});

test('compress-originals skips uploads it has already compressed', function () {
    Queue::fake([CompressMediaFileJob::class]);

    $video = streamedVideo(['status' => 'processed', 'size' => 900 * 1024 * 1024]);
    $compressed = dirname($video->video_path).'/clip.av1.mp4';
    Storage::disk('public')->put($compressed, str_repeat('c', 65536));
    $video->forceFill(['video_path' => $compressed])->saveQuietly();

    $this->artisan('videos:compress-originals --apply')->assertSuccessful();

    Queue::assertNothingPushed();
});

test('compress-originals leaves cloud-stored videos alone', function () {
    Queue::fake([CompressMediaFileJob::class]);
    streamedVideo(['status' => 'processed', 'size' => 900 * 1024 * 1024, 'storage_disk' => 's3']);

    $this->artisan('videos:compress-originals --apply')->assertSuccessful();

    Queue::assertNothingPushed();
});

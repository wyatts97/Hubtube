<?php

use App\Filament\Pages\MediaLibrary;
use App\Jobs\ReclaimHlsJob;
use App\Models\Setting;
use App\Models\StorageReclaim;
use App\Services\Encoding\HlsPackager;
use App\Services\Storage\StorageReclaimService;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

/*
|--------------------------------------------------------------------------
| Storage reclaim — the duplicate HLS copy
|--------------------------------------------------------------------------
|
| generate_hls defaults to on, so every video carries a complete second copy
| of its renditions as .ts segments. Giving that up re-encodes nothing: the
| tree is renamed aside and the master playlist rewritten, after which the
| player falls back to progressive MP4.
|
| It is also the one reclaim that could make a video unplayable — HLS is the
| only copy of any quality whose MP4 has been lost — so the pre-flight refusal
| leaving the tree completely intact is the most important test here.
|
*/

/** Rewrite the master playlist so it lists the HLS renditions on disk. */
function writeMaster(string $processedDir, $video): void
{
    app(HlsPackager::class)->writeMasterPlaylist(
        Storage::disk('public')->path($processedDir),
        $video->encodings()->with('profile')->get(),
    );
}

// ── The operation ───────────────────────────────────────────────────────────

test('reclaiming HLS moves the tree aside rather than deleting it', function () {
    $video = processedVideo();
    $dir = dirname($video->video_path);
    Storage::disk('public')->put($dir.'/processed/hls/720p/playlist.m3u8', '#EXTM3U');

    $reclaim = reclaims()->requestAndStart($video, StorageReclaim::TARGET_HLS)['reclaim'];
    $reclaim->refresh();

    expect($reclaim->status)->toBe(StorageReclaim::AWAITING_REVIEW)
        ->and(Storage::disk('public')->directoryExists($dir.'/processed/hls'))->toBeFalse()
        // Renamed, not copied: reverting costs nothing either way.
        ->and(Storage::disk('public')->exists($reclaim->kept_path.'/720p/segment_000.ts'))->toBeTrue()
        ->and($reclaim->before_bytes)->toBeGreaterThan(0)
        ->and($reclaim->after_bytes)->toBe(0)
        ->and($reclaim->keep_until)->not->toBeNull();
});

test('the player falls back to MP4 while the renditions stay advertised', function () {
    $video = processedVideo();
    $dir = dirname($video->video_path);
    Storage::disk('public')->put($dir.'/processed/hls/720p/playlist.m3u8', '#EXTM3U');
    writeMaster($dir.'/processed', $video);

    expect($video->fresh()->hls_playlist_url)->not->toBeNull();

    reclaims()->requestAndStart($video, StorageReclaim::TARGET_HLS);

    $video = $video->fresh();

    expect($video->hls_playlist_url)->toBeNull()
        // The master is unlinked by the packager once no playlists remain.
        ->and(Storage::disk('public')->exists($dir.'/processed/master.m3u8'))->toBeFalse()
        // Unchanged: every rendition MP4 is still there, so the quality menu
        // must keep offering them.
        ->and($video->qualities_available)->toContain('720p', '480p')
        ->and($video->quality_urls)->toHaveKeys(['original', '720p', '480p']);
});

test('the master playlist change is cache-busted', function () {
    // It is rewritten under its own name, and nginx caches it.
    $video = processedVideo();

    reclaims()->requestAndStart($video, StorageReclaim::TARGET_HLS);

    expect($video->fresh()->media_version)->toBe(1);
});

test('a refused pre-flight leaves the HLS tree completely intact', function () {
    // The "never lose the only copy" test. A quality whose MP4 has gone is
    // served by HLS alone, so dropping the tree would take it with it.
    $video = processedVideo();
    $dir = dirname($video->video_path);
    Storage::disk('public')->delete($dir.'/processed/480p.mp4');

    $result = reclaims()->requestAndStart($video, StorageReclaim::TARGET_HLS);

    expect($result['reclaim'])->toBeNull()
        ->and($result['reason'])->toContain('only copy')
        ->and(Storage::disk('public')->exists($dir.'/processed/hls/720p/segment_000.ts'))->toBeTrue()
        ->and(StorageReclaim::count())->toBe(0);
});

test('a rendition lost after the job was queued still stops it', function () {
    // The request may sit on the queue while something else deletes an MP4,
    // so the pre-flight is re-run inside the job rather than trusted from
    // request time.
    Queue::fake();
    $video = processedVideo();
    $dir = dirname($video->video_path);

    $reclaim = reclaims()->requestAndStart($video, StorageReclaim::TARGET_HLS)['reclaim'];

    Queue::assertPushed(ReclaimHlsJob::class);

    Storage::disk('public')->delete($dir.'/processed/480p.mp4');

    app(ReclaimHlsJob::class, ['reclaimId' => $reclaim->id])->handle(
        app(StorageReclaimService::class),
        app(HlsPackager::class),
    );

    expect($reclaim->fresh()->status)->toBe(StorageReclaim::SKIPPED)
        ->and($reclaim->fresh()->error)->toContain('only copy')
        ->and(Storage::disk('public')->exists($dir.'/processed/hls/720p/segment_000.ts'))->toBeTrue();
});

test('reclaim jobs run on the niced serial queue, never the priority one', function () {
    // video-priority is drained first by the encoding supervisor, so a bulk
    // reclaim landing there would starve live uploads.
    Queue::fake();

    reclaims()->requestAndStart(processedVideo(), StorageReclaim::TARGET_HLS);

    Queue::assertPushed(ReclaimHlsJob::class, fn (ReclaimHlsJob $job) => $job->queue === 'storage-reclaim');
});

// ── Reverting and rebuilding ────────────────────────────────────────────────

test('reverting restores adaptive streaming', function () {
    $video = processedVideo();
    $dir = dirname($video->video_path);
    Storage::disk('public')->put($dir.'/processed/hls/720p/playlist.m3u8', '#EXTM3U');
    writeMaster($dir.'/processed', $video);

    $reclaim = reclaims()->requestAndStart($video, StorageReclaim::TARGET_HLS)['reclaim'];

    $reclaim->refresh();
    $kept = $reclaim->kept_path;

    expect(reclaims()->revert($reclaim))->toBeTrue()
        ->and(Storage::disk('public')->exists($dir.'/processed/hls/720p/segment_000.ts'))->toBeTrue()
        ->and(Storage::disk('public')->directoryExists($kept))->toBeFalse();
});

test('a video can be repackaged from its MP4s after the tree is gone', function () {
    // The recovery path once a reclaim has been accepted and the kept tree
    // deleted: a remux, so minutes rather than hours.
    $video = processedVideo();
    $dir = dirname($video->video_path);

    $reclaim = reclaims()->requestAndStart($video, StorageReclaim::TARGET_HLS)['reclaim'];
    reclaims()->accept($reclaim->fresh());

    expect(Storage::disk('public')->directoryExists($dir.'/processed/hls'))->toBeFalse();

    // FakeFfmpegRunner stands in for the remux; what matters here is that the
    // command reaches the packager for every completed rendition.
    $this->artisan('storage:repackage-hls', ['video' => [$video->id]]);
})->skip('Needs the shared fake ffmpeg runner fixture, which lands with the rendition phase.');

test('repackaging refuses to clobber an existing tree without --force', function () {
    $video = processedVideo();

    $this->artisan('storage:repackage-hls', ['video' => [$video->id]])
        ->expectsOutputToContain('already has an HLS tree')
        ->assertSuccessful();

    expect(Storage::disk('public')->exists(dirname($video->video_path).'/processed/hls/720p/segment_000.ts'))->toBeTrue();
});

test('repackaging an unknown video fails loudly', function () {
    $this->artisan('storage:repackage-hls', ['video' => [99999]])
        ->expectsOutputToContain('was not found')
        ->assertFailed();
});

// ── Fanning out ─────────────────────────────────────────────────────────────

test('the reclaim command queues the biggest duplicate trees first', function () {
    Queue::fake();

    $small = processedVideo();
    $big = processedVideo();

    // Make one tree clearly the bigger prize, then let the index rank them.
    Storage::disk('public')->put(dirname($big->video_path).'/processed/hls/720p/segment_001.ts', str_repeat('x', 5_000_000));
    $this->artisan('media:index --full');

    $this->artisan('storage:reclaim --target=hls --limit=1')
        ->expectsOutputToContain('Queued 1 reclaim')
        ->assertSuccessful();

    expect(StorageReclaim::count())->toBe(1)
        ->and(StorageReclaim::first()->video_id)->toBe($big->id);
});

test('the reclaim command can rehearse without queueing anything', function () {
    Queue::fake();
    processedVideo();
    $this->artisan('media:index --full');

    $this->artisan('storage:reclaim --target=hls --dry-run')
        ->expectsOutputToContain('would be queued')
        ->assertSuccessful();

    expect(StorageReclaim::count())->toBe(0);
    Queue::assertNothingPushed();
});

test('the reclaim command reports each refusal rather than failing silently', function () {
    Queue::fake();
    $video = processedVideo();
    Storage::disk('public')->delete(dirname($video->video_path).'/processed/480p.mp4');
    $this->artisan('media:index --full');

    $this->artisan('storage:reclaim --target=hls --video='.$video->id)
        ->expectsOutputToContain('skipped')
        ->assertSuccessful();

    expect(StorageReclaim::count())->toBe(0);
});

test('an unknown target is refused by the command', function () {
    $this->artisan('storage:reclaim --target=everything')->assertFailed();
});

// ── Requesting from the Media Library ───────────────────────────────────────

test('selecting many segments queues one reclaim of the tree', function () {
    Queue::fake();
    asAdmin();
    $video = processedVideo();
    $dir = dirname($video->video_path);

    foreach (range(1, 5) as $i) {
        Storage::disk('public')->put($dir."/processed/hls/720p/segment_00{$i}.ts", str_repeat('x', 1000));
    }
    $this->artisan('media:index --full');

    $paths = collect(range(0, 5))
        ->map(fn ($i) => $dir."/processed/hls/720p/segment_00{$i}.ts")
        ->all();

    $component = Livewire::test(MediaLibrary::class)->call('startReclaim', $paths);

    expect($component->get('reclaimPlan'))->toHaveCount(1);

    $component->call('confirmReclaim');

    expect(StorageReclaim::count())->toBe(1)
        ->and(StorageReclaim::first()->target)->toBe(StorageReclaim::TARGET_HLS);
});

test('the library explains why a selected file cannot be reclaimed', function () {
    asAdmin();
    $video = processedVideo();
    $dir = dirname($video->video_path);
    Storage::disk('public')->delete($dir.'/processed/480p.mp4');
    $this->artisan('media:index --full');

    $component = Livewire::test(MediaLibrary::class)
        ->call('startReclaim', [$dir.'/processed/hls/720p/segment_000.ts']);

    expect($component->get('reclaimPlan'))->toBe([])
        ->and($component->get('reclaimRefusals')[0]['reason'])->toContain('only copy')
        ->and($component->get('showReclaimModal'))->toBeTrue();
});

test('selecting files that are nothing to do with a video queues nothing', function () {
    asAdmin();
    Storage::disk('public')->put('media/promo.jpg', 'x');
    $this->artisan('media:index --full');

    $component = Livewire::test(MediaLibrary::class)->call('startReclaim', ['media/promo.jpg']);

    expect($component->get('showReclaimModal'))->toBeFalse();
});

test('the library can turn off HLS generation in the same click', function () {
    // Otherwise the next encode of that video writes the copy straight back.
    Queue::fake();
    asAdmin();
    Setting::set('generate_hls', true, 'general', 'boolean');
    $video = processedVideo();
    $this->artisan('media:index --full');

    Livewire::test(MediaLibrary::class)
        ->call('startReclaim', [dirname($video->video_path).'/processed/hls/720p/segment_000.ts'])
        ->set('disableHlsGeneration', true)
        ->call('confirmReclaim');

    expect(Setting::get('generate_hls'))->toBeFalsy();
});

test('the setting is left alone when no HLS reclaim was queued', function () {
    Queue::fake();
    asAdmin();
    Setting::set('generate_hls', true, 'general', 'boolean');
    $video = processedVideo();
    $this->artisan('media:index --full');

    Livewire::test(MediaLibrary::class)
        ->call('startReclaim', [dirname($video->video_path).'/processed/720p.mp4'])
        ->set('disableHlsGeneration', true)
        ->call('confirmReclaim');

    expect(Setting::get('generate_hls'))->toBeTruthy();
});

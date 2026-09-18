<?php

use App\Jobs\EncodeVideoChunkJob;
use App\Jobs\ProcessVideoJob;
use App\Jobs\ReencodeRenditionJob;
use App\Models\Notification;
use App\Models\Setting;
use App\Models\StorageReclaim;
use App\Models\VideoEncoding;
use App\Services\Encoding\FfmpegCommands;
use App\Services\Encoding\RenditionCoordinator;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

/*
|--------------------------------------------------------------------------
| Storage reclaim — re-encoding one rendition more tightly
|--------------------------------------------------------------------------
|
| This reuses the whole encoding pipeline rather than duplicating it: the
| reclaim job only sets one video_encodings row up to be redone with slower
| settings. So what has to be proved is the set-up and the aftermath — that
| the live file keeps serving throughout, that the redo cannot jump the queue
| ahead of live uploads, and that a result is verified before anyone is
| offered the chance to delete the only other copy.
|
*/

// ── Setting the redo up ─────────────────────────────────────────────────────

test('the pipeline is set up to redo just that rendition', function () {
    fakeFfmpeg(duration: 60, height: 720, width: 1280);
    $video = encodedVideo();
    // Faked only now: the video above had to be encoded for real (through the
    // fake ffmpeg) before there was anything to reclaim.
    Queue::fake();

    $before = $video->encodings()->where('quality', '360p')->first();

    $reclaim = reclaims()->request($video, StorageReclaim::TARGET_RENDITION, '480p')['reclaim'];
    app(ReencodeRenditionJob::class, ['reclaimId' => $reclaim->id])
        ->handle(reclaims(), app(RenditionCoordinator::class));

    $target = $video->encodings()->where('quality', '480p')->first();

    expect($target->status)->toBe(VideoEncoding::PENDING)
        // The settings live on the ledger row, not here: plan() rebuilds this
        // row from scratch on its next pass and reads them back from there, so
        // a reclaim survives a re-plan and an ordinary one inherits nothing.
        ->and(reclaims()->overridesForEncoding($video)['480p']['preset'])->toBe('slow')
        // Cleared: a reclaim of the lowest rendition would otherwise inherit
        // its place at the front of the encoding queue.
        ->and($target->is_priority)->toBeFalse()
        // Fences off anything still in flight from the previous run.
        ->and($target->run_id)->not->toBe($reclaim->run_id)
        // Every other rendition is left completed and untouched.
        ->and($video->encodings()->where('quality', '360p')->first()->status)->toBe(VideoEncoding::COMPLETED)
        ->and($video->encodings()->where('quality', '360p')->first()->run_id)->toBe($before->run_id);
});

test('the old file keeps serving while the new one encodes', function () {
    // Hardlinked aside, not moved: the canonical path holds the old bytes for
    // the whole encode, and the closing rename() only swaps that entry.
    fakeFfmpeg(duration: 60, height: 720, width: 1280);
    $video = encodedVideo();
    // Faked only now: the video above had to be encoded for real (through the
    // fake ffmpeg) before there was anything to reclaim.
    Queue::fake();
    $live = dirname($video->video_path).'/processed/480p.mp4';

    $reclaim = reclaims()->request($video, StorageReclaim::TARGET_RENDITION, '480p')['reclaim'];
    app(ReencodeRenditionJob::class, ['reclaimId' => $reclaim->id])
        ->handle(reclaims(), app(RenditionCoordinator::class));

    $reclaim->refresh();

    expect(Storage::disk('public')->size($live))->toBe(500_000)
        ->and(Storage::disk('public')->size($reclaim->kept_path))->toBe(500_000)
        ->and($reclaim->before_bytes)->toBe(500_000)
        ->and($reclaim->status)->toBe(StorageReclaim::RUNNING);
});

test('the kept link still holds the old bytes after the encode replaces the file', function () {
    $fake = fakeFfmpeg(duration: 60, height: 720, width: 1280);
    $video = encodedVideo();
    $fake->outputBytes = 100_000;
    $live = dirname($video->video_path).'/processed/480p.mp4';

    $reclaim = reclaims()->requestAndStart($video, StorageReclaim::TARGET_RENDITION, '480p')['reclaim'];
    $reclaim->refresh();

    expect($reclaim->status)->toBe(StorageReclaim::AWAITING_REVIEW)
        ->and(Storage::disk('public')->size($live))->toBe(100_000)
        // The whole point of the hardlink.
        ->and(Storage::disk('public')->size($reclaim->kept_path))->toBe(500_000)
        ->and($reclaim->after_bytes)->toBe(100_000)
        ->and($reclaim->savedPercent())->toBe(80.0);
});

test('a reclaim never runs on the queue that live uploads depend on', function () {
    // video-priority is drained first by the encoding supervisor. The row's
    // overrides route it away from there even though the coordinator's own
    // dispatch logic is shared with normal encoding.
    fakeFfmpeg(duration: 60, height: 720, width: 1280);
    $video = encodedVideo();
    // Faked only now: the video above had to be encoded for real (through the
    // fake ffmpeg) before there was anything to reclaim.
    Queue::fake();

    $reclaim = reclaims()->request($video, StorageReclaim::TARGET_RENDITION, '360p')['reclaim'];
    app(ReencodeRenditionJob::class, ['reclaimId' => $reclaim->id])
        ->handle(reclaims(), app(RenditionCoordinator::class));

    Queue::assertPushed(ProcessVideoJob::class, fn ($job) => $job->queue === 'storage-reclaim');

    // And the chunk jobs, which the coordinator routes by the overrides that
    // plan() writes onto the row — stood in for here, because the
    // ProcessVideoJob that would have run plan() is faked.
    $encoding = $video->encodings()->where('quality', '360p')->first();
    $encoding->forceFill(['settings_overrides' => reclaims()->overridesForEncoding($video)['360p']])->save();
    app(RenditionCoordinator::class)->dispatchRendition($encoding->fresh());

    Queue::assertPushed(EncodeVideoChunkJob::class, fn ($job) => $job->queue === 'storage-reclaim');
    Queue::assertNotPushed(EncodeVideoChunkJob::class, fn ($job) => $job->queue === 'video-priority');
});

test('the overrides reach the ffmpeg command line', function () {
    $fake = fakeFfmpeg(duration: 60, height: 720, width: 1280);
    $video = encodedVideo();
    $fake->outputBytes = 100_000;
    $fake->commands = [];

    reclaims()->requestAndStart($video, StorageReclaim::TARGET_RENDITION, '480p');

    $encodes = collect($fake->commands)->filter(fn ($c) => str_contains($c, 'libx264'));

    expect($encodes)->not->toBeEmpty()
        ->and($encodes->first())->toContain('slow')
        // CRF 22 default + the reclaim delta of 2.
        ->and($encodes->first())->toContain('-crf 24')
        // Load-bearing for renditions: HLS segments and chunk-boundary stream
        // copies both need keyframes on the fixed grid.
        ->and($encodes->first())->toContain('-force_key_frames');
});

test('the original re-compression settings drop the keyframe grid and copy audio', function () {
    // The original is neither chunked nor HLS-packaged, so forcing a keyframe
    // every two seconds is pure waste there; and re-encoding already-lossy
    // AAC costs quality for negligible bytes.
    $commands = (new FfmpegCommands(Setting::getAll()))
        ->withOverrides(['preset' => 'slow', 'crf' => 24, 'keyframes' => false, 'copy_audio' => true]);

    expect($commands->videoArgs())->not->toContain('-force_key_frames')
        ->and($commands->videoArgs())->toContain('slow')
        ->and($commands->videoArgs())->toContain('-crf 24')
        ->and($commands->audioArgs())->toBe('-c:a copy');
});

test('a normal re-plan cannot inherit a reclaim preset or its queue', function () {
    // plan() resets settings_overrides, so "encode missing renditions" after a
    // reclaim goes back to the site defaults and the normal queue.
    $fake = fakeFfmpeg(duration: 60, height: 720, width: 1280);
    $video = encodedVideo();
    $fake->outputBytes = 100_000;

    reclaims()->requestAndStart($video, StorageReclaim::TARGET_RENDITION, '480p');

    expect($video->encodings()->where('quality', '480p')->first()->settings_overrides)->not->toBeNull();

    // The reclaim is settled now, so nothing is "running" for plan() to read
    // settings from. Losing the file is what makes plan() redo this rendition.
    Storage::disk('public')->delete(dirname($video->video_path).'/processed/480p.mp4');
    ProcessVideoJob::dispatchSync($video->fresh());

    $encoding = $video->encodings()->where('quality', '480p')->first();

    expect($encoding->settings_overrides)->toBeNull()
        ->and($encoding->status)->toBe(VideoEncoding::COMPLETED);
});

// ── Verifying the result ────────────────────────────────────────────────────

test('a result that saves too little is discarded and the old file restored', function () {
    Setting::set('reclaim_min_saving_percent', 40, 'general', 'integer');
    $fake = fakeFfmpeg(duration: 60, height: 720, width: 1280);
    $video = encodedVideo();
    // Only a 10% saving.
    $fake->outputBytes = 450_000;
    $live = dirname($video->video_path).'/processed/480p.mp4';

    $reclaim = reclaims()->requestAndStart($video, StorageReclaim::TARGET_RENDITION, '480p')['reclaim'];
    $reclaim->refresh();

    expect($reclaim->status)->toBe(StorageReclaim::SKIPPED)
        ->and($reclaim->error)->toContain('threshold')
        // Exactly the file the site was serving before any of this.
        ->and(Storage::disk('public')->size($live))->toBe(500_000)
        ->and(Storage::disk('public')->get($live))->toStartWith('o');
});

test('a result that came out bigger is discarded', function () {
    $fake = fakeFfmpeg(duration: 60, height: 720, width: 1280);
    $video = encodedVideo();
    $fake->outputBytes = 900_000;
    $live = dirname($video->video_path).'/processed/480p.mp4';

    $reclaim = reclaims()->requestAndStart($video, StorageReclaim::TARGET_RENDITION, '480p')['reclaim'];

    expect($reclaim->fresh()->status)->toBe(StorageReclaim::SKIPPED)
        ->and(Storage::disk('public')->size($live))->toBe(500_000);
});

test('a result whose length drifted is discarded', function () {
    // videos.duration drives the scrubber VTT cue times, and prepare()
    // rewrites that column on the next encode — so drift here would
    // desynchronise the scrubber later, far from any obvious cause.
    $fake = fakeFfmpeg(duration: 60, height: 720, width: 1280);
    $video = encodedVideo();
    $fake->outputBytes = 100_000;

    // The probe starts reporting a shorter file once encoding begins.
    $fake->onRun = function (string $command) use ($fake) {
        if (str_contains($command, 'libx264')) {
            $fake->duration = 57.0;
        }
    };

    $live = dirname($video->video_path).'/processed/480p.mp4';
    $reclaim = reclaims()->requestAndStart($video, StorageReclaim::TARGET_RENDITION, '480p')['reclaim'];

    expect($reclaim->fresh()->status)->toBe(StorageReclaim::SKIPPED)
        ->and($reclaim->fresh()->error)->toContain('drifted')
        ->and(Storage::disk('public')->size($live))->toBe(500_000);
});

// ── Accepting ───────────────────────────────────────────────────────────────

test('accepting keeps the smaller file and refreshes the size columns', function () {
    $fake = fakeFfmpeg(duration: 60, height: 720, width: 1280);
    $video = encodedVideo();
    $fake->outputBytes = 100_000;

    $reclaim = reclaims()->requestAndStart($video, StorageReclaim::TARGET_RENDITION, '480p')['reclaim'];
    $kept = $reclaim->fresh()->kept_path;

    expect(reclaims()->accept($reclaim->fresh()))->toBeTrue()
        ->and(Storage::disk('public')->exists($kept))->toBeFalse()
        ->and(Storage::disk('public')->size(dirname($video->video_path).'/processed/480p.mp4'))->toBe(100_000)
        ->and($video->encodings()->where('quality', '480p')->first()->size)->toBe(100_000);
});

test('the video is not put back through approval or notified again', function () {
    // The reclaim reuses the pipeline, which is only safe because complete()
    // routes an already-processed video through updateRenditions().
    $fake = fakeFfmpeg(duration: 60, height: 720, width: 1280);
    $video = encodedVideo();
    $fake->outputBytes = 100_000;

    // Both counted after the first encode, so this is strictly about the redo.
    $notifications = Notification::where('user_id', $video->user_id)->count();
    $approved = $video->is_approved;

    reclaims()->requestAndStart($video, StorageReclaim::TARGET_RENDITION, '480p');

    $video->refresh();

    expect($video->status)->toBe('processed')
        ->and($video->is_approved)->toBe($approved)
        ->and(Notification::where('user_id', $video->user_id)->count())->toBe($notifications);
});

test('reverting restores the old rendition and tells caches to refetch', function () {
    $fake = fakeFfmpeg(duration: 60, height: 720, width: 1280);
    $video = encodedVideo();
    $fake->outputBytes = 100_000;
    $live = dirname($video->video_path).'/processed/480p.mp4';

    $reclaim = reclaims()->requestAndStart($video, StorageReclaim::TARGET_RENDITION, '480p')['reclaim']->fresh();
    $versionAfterEncode = $video->fresh()->media_version;

    expect(reclaims()->revert($reclaim))->toBeTrue()
        ->and(Storage::disk('public')->size($live))->toBe(500_000)
        ->and($video->fresh()->media_version)->toBeGreaterThan($versionAfterEncode);
});

// ── Set-up failures ─────────────────────────────────────────────────────────

test('a rendition deleted between request and run stops the reclaim', function () {
    fakeFfmpeg(duration: 60, height: 720, width: 1280);
    $video = encodedVideo();
    // Faked only now: the video above had to be encoded for real (through the
    // fake ffmpeg) before there was anything to reclaim.
    Queue::fake();

    $reclaim = reclaims()->request($video, StorageReclaim::TARGET_RENDITION, '480p')['reclaim'];
    Storage::disk('public')->delete(dirname($video->video_path).'/processed/480p.mp4');

    app(ReencodeRenditionJob::class, ['reclaimId' => $reclaim->id])
        ->handle(reclaims(), app(RenditionCoordinator::class));

    expect($reclaim->fresh()->status)->toBe(StorageReclaim::SKIPPED)
        ->and($reclaim->fresh()->error)->toContain('missing from disk');
});

test('whether the old file was linked or copied is recorded', function () {
    // A copy costs the file's size again until review; a link costs nothing.
    // "Space held pending review" means different things in each case.
    fakeFfmpeg(duration: 60, height: 720, width: 1280);
    $video = encodedVideo();
    // Faked only now: the video above had to be encoded for real (through the
    // fake ffmpeg) before there was anything to reclaim.
    Queue::fake();

    $reclaim = reclaims()->request($video, StorageReclaim::TARGET_RENDITION, '480p')['reclaim'];
    app(ReencodeRenditionJob::class, ['reclaimId' => $reclaim->id])
        ->handle(reclaims(), app(RenditionCoordinator::class));

    expect($reclaim->fresh()->kept_is_hardlink)->toBeBool();
});

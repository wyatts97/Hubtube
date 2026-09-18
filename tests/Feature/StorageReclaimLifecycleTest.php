<?php

use App\Filament\Pages\MediaLibrary;
use App\Filament\Pages\StorageReclaims;
use App\Jobs\IndexMediaDirectoryJob;
use App\Jobs\RecompressOriginalJob;
use App\Models\StorageReclaim;
use App\Models\Video;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;

/*
|--------------------------------------------------------------------------
| Storage reclaim — review, accept, discard
|--------------------------------------------------------------------------
|
| A re-compression produces a smaller file and stops. Every test here asks the
| same question a different way: what is the site serving right now? Until
| someone accepts, the answer must be "exactly the upload it was serving
| before", whatever happened to the candidate.
|
*/

/** A re-compression waiting on review, with both files on disk. */
function awaitingReview(?Video $video = null): StorageReclaim
{
    $video ??= processedVideo();
    $new = dirname($video->video_path).'/recompressed.mp4';

    Storage::disk('public')->put($new, str_repeat('n', 400_000));

    return StorageReclaim::create([
        'video_id' => $video->id,
        'target' => StorageReclaim::TARGET_ORIGINAL,
        'status' => StorageReclaim::AWAITING_REVIEW,
        'run_id' => (string) Str::uuid(),
        'source_path' => $video->video_path,
        'new_path' => $new,
        // The kept file *is* the live one: video_path is not repointed until
        // accept, so nothing changes for viewers until then.
        'kept_path' => $video->video_path,
        'before_bytes' => 900_000,
        'after_bytes' => 400_000,
    ]);
}

// ── Accepting ───────────────────────────────────────────────────────────────

test('accepting repoints the video and deletes the upload', function () {
    $reclaim = awaitingReview();
    $old = $reclaim->kept_path;

    expect(reclaims()->accept($reclaim))->toBeTrue();

    $reclaim->refresh();
    $video = $reclaim->video->fresh();

    expect($video->video_path)->toBe($reclaim->new_path)
        ->and(Storage::disk('public')->exists($old))->toBeFalse()
        ->and(Storage::disk('public')->exists($video->video_path))->toBeTrue()
        // The size columns follow the disk, or the saving is invisible
        // everywhere except this ledger.
        ->and($video->size)->toBe(400_000)
        ->and($reclaim->status)->toBe(StorageReclaim::ACCEPTED)
        ->and($reclaim->kept_path)->toBeNull()
        ->and($reclaim->finished_at)->not->toBeNull();
});

test('only a row awaiting review can be accepted', function () {
    $reclaim = awaitingReview();
    $reclaim->forceFill(['status' => StorageReclaim::RUNNING])->save();

    expect(reclaims()->accept($reclaim))->toBeFalse()
        ->and($reclaim->fresh()->status)->toBe(StorageReclaim::RUNNING);
});

test('a candidate that has vanished fails instead of clearing the column', function () {
    $reclaim = awaitingReview();
    $live = $reclaim->video->video_path;
    Storage::disk('public')->delete($reclaim->new_path);

    expect(reclaims()->accept($reclaim))->toBeFalse()
        ->and($reclaim->fresh()->status)->toBe(StorageReclaim::FAILED)
        // The live file and the column are exactly as they were.
        ->and($reclaim->video->fresh()->video_path)->toBe($live)
        ->and(Storage::disk('public')->exists($live))->toBeTrue();
});

test('accepting re-indexes the folder', function () {
    // Every write is saveQuietly(), which skips the observer that keeps
    // media_files.is_referenced honest — so this has to be explicit, or the
    // file the site now serves reads as "Unused" and is one click from
    // deletion.
    Queue::fake();
    $reclaim = awaitingReview();

    reclaims()->accept($reclaim);

    Queue::assertPushed(IndexMediaDirectoryJob::class);
});

// ── Discarding ──────────────────────────────────────────────────────────────

test('discarding throws the candidate away and keeps the upload', function () {
    $reclaim = awaitingReview();
    $live = $reclaim->video->video_path;

    expect(reclaims()->revert($reclaim))->toBeTrue()
        // video_path was never repointed, so there is nothing to restore.
        ->and($reclaim->video->fresh()->video_path)->toBe($live)
        ->and(Storage::disk('public')->size($live))->toBe(900_000)
        ->and(Storage::disk('public')->exists($reclaim->new_path))->toBeFalse()
        ->and($reclaim->fresh()->status)->toBe(StorageReclaim::REVERTED);
});

test('discarding a candidate that is already gone still settles the row', function () {
    $reclaim = awaitingReview();
    Storage::disk('public')->delete($reclaim->new_path);

    expect(reclaims()->revert($reclaim))->toBeTrue()
        ->and($reclaim->fresh()->status)->toBe(StorageReclaim::REVERTED);
});

// ── Nothing happens on its own ──────────────────────────────────────────────

test('there is no scheduled acceptance', function () {
    // The feature shipped with a nightly sweep that auto-accepted anything
    // unreviewed. Accepting deletes an upload permanently with no backup, so
    // it now only ever happens because a person pressed the button.
    $schedule = app(Schedule::class);

    $commands = collect($schedule->events())->map(fn ($event) => $event->command ?? '')->implode(' ');

    expect($commands)->not->toContain('reclaim')
        ->and(class_exists('App\\Console\\Commands\\SweepStorageReclaims'))->toBeFalse();
});

test('a row left alone stays awaiting review indefinitely', function () {
    $reclaim = awaitingReview();

    $this->travel(400)->days();

    expect($reclaim->fresh()->status)->toBe(StorageReclaim::AWAITING_REVIEW)
        ->and(Storage::disk('public')->exists($reclaim->kept_path))->toBeTrue();
});

// ── Interaction with the encoder ────────────────────────────────────────────

test('a reclaim awaiting review does not make the video look like it is encoding', function () {
    // The reason this is not tracked on video_encodings: a non-terminal row
    // there would make isEncoding() true forever, permanently hiding "encode
    // missing renditions".
    $video = awaitingReview()->video->fresh();

    expect($video->isEncoding())->toBeFalse()
        ->and($video->canReencode())->toBeTrue();
});

test('the encoder carries no reclaim-specific state any more', function () {
    // The rendition path is gone, so the columns and the queue it needed
    // should be too — a stale override column would silently change how a
    // normal rendition is encoded.
    expect(Schema::hasColumn('video_encodings', 'settings_overrides'))->toBeFalse()
        ->and(Schema::hasColumn('videos', 'media_version'))->toBeFalse()
        ->and(defined('App\\Services\\Encoding\\RenditionCoordinator::RECLAIM_QUEUE'))->toBeFalse();
});

// ── The review page ─────────────────────────────────────────────────────────

test('the review page separates space freed from space merely held', function () {
    asAdmin();

    awaitingReview();
    $freed = awaitingReview(processedVideo());
    reclaims()->accept($freed);

    $totals = Livewire::test(StorageReclaims::class)->instance()->getTotalsProperty();

    expect($totals['reclaimed'])->toBe(500_000)
        // Not saved: both copies are still on disk while it waits.
        ->and($totals['awaiting'])->toBe(500_000)
        ->and($totals['awaiting_count'])->toBe(1);
});

test('the review page offers a decision only on waiting rows', function () {
    asAdmin();
    $reclaim = awaitingReview();

    Livewire::test(StorageReclaims::class)
        ->assertCanSeeTableRecords([$reclaim])
        ->assertTableActionVisible('accept', $reclaim)
        ->assertTableActionVisible('revert', $reclaim);

    reclaims()->accept($reclaim);

    Livewire::test(StorageReclaims::class)
        ->assertTableActionHidden('accept', $reclaim->fresh())
        ->assertTableActionHidden('revert', $reclaim->fresh());
});

test('accepting from the review page frees the bytes', function () {
    asAdmin();
    $reclaim = awaitingReview();
    $old = $reclaim->kept_path;

    Livewire::test(StorageReclaims::class)->callTableAction('accept', $reclaim);

    expect($reclaim->fresh()->status)->toBe(StorageReclaim::ACCEPTED)
        ->and(Storage::disk('public')->exists($old))->toBeFalse();
});

test('the navigation badge counts what is waiting on a human', function () {
    expect(StorageReclaims::getNavigationBadge())->toBeNull();

    awaitingReview();

    expect(StorageReclaims::getNavigationBadge())->toBe('1');
});

// ── Requesting from the Media Library ───────────────────────────────────────

test('selecting an upload queues one re-compression', function () {
    Queue::fake();
    asAdmin();
    $video = processedVideo();
    $this->artisan('media:index --full');

    $component = Livewire::test(MediaLibrary::class)->call('startReclaim', [$video->video_path]);

    expect($component->get('reclaimPlan'))->toHaveCount(1);

    $component->call('confirmReclaim');

    expect(StorageReclaim::count())->toBe(1);
    Queue::assertPushed(RecompressOriginalJob::class, fn ($job) => $job->queue === 'storage-reclaim');
});

test('selecting the files the player streams offers nothing', function () {
    // The guard against the mistake this feature was narrowed to avoid.
    asAdmin();
    $video = processedVideo();
    $dir = dirname($video->video_path);
    $this->artisan('media:index --full');

    $component = Livewire::test(MediaLibrary::class)->call('startReclaim', [
        $dir.'/processed/720p.mp4',
        $dir.'/processed/hls/720p/segment_000.ts',
    ]);

    expect($component->get('showReclaimModal'))->toBeFalse()
        ->and(StorageReclaim::count())->toBe(0);
});

test('the library explains why an upload cannot be re-compressed', function () {
    asAdmin();
    $video = processedVideo(['storage_disk' => 's3']);
    $this->artisan('media:index --full');

    $component = Livewire::test(MediaLibrary::class)->call('startReclaim', [$video->video_path]);

    expect($component->get('reclaimPlan'))->toBe([])
        ->and($component->get('reclaimRefusals')[0]['reason'])->toContain('local storage')
        ->and($component->get('showReclaimModal'))->toBeTrue();
});

test('selecting files that belong to no video queues nothing', function () {
    asAdmin();
    Storage::disk('public')->put('media/promo.jpg', 'x');
    $this->artisan('media:index --full');

    $component = Livewire::test(MediaLibrary::class)->call('startReclaim', ['media/promo.jpg']);

    expect($component->get('showReclaimModal'))->toBeFalse();
});

// ── Fanning out from the console ────────────────────────────────────────────

test('the command queues the biggest uploads first', function () {
    Queue::fake();

    $small = processedVideo();
    $small->forceFill(['size' => 1_000])->saveQuietly();
    $big = processedVideo();
    $big->forceFill(['size' => 9_000_000])->saveQuietly();

    $this->artisan('storage:reclaim --limit=1')
        ->expectsOutputToContain('Queued 1 re-compression')
        ->assertSuccessful();

    expect(StorageReclaim::count())->toBe(1)
        ->and(StorageReclaim::first()->video_id)->toBe($big->id);
});

test('the command can rehearse without queueing anything', function () {
    Queue::fake();
    processedVideo();

    $this->artisan('storage:reclaim --dry-run')
        ->expectsOutputToContain('would be queued')
        ->assertSuccessful();

    expect(StorageReclaim::count())->toBe(0);
    Queue::assertNothingPushed();
});

test('the command reports each refusal rather than failing silently', function () {
    Queue::fake();
    $video = processedVideo(['storage_disk' => 's3']);

    $this->artisan('storage:reclaim --video='.$video->id)
        ->expectsOutputToContain('skipped')
        ->assertSuccessful();

    expect(StorageReclaim::count())->toBe(0);
});

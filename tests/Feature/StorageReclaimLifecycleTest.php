<?php

use App\Filament\Pages\StorageReclaims;
use App\Jobs\IndexMediaDirectoryJob;
use App\Models\Setting;
use App\Models\StorageReclaim;
use App\Models\Video;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;

/*
|--------------------------------------------------------------------------
| Storage reclaim — accept, revert, expire
|--------------------------------------------------------------------------
|
| A reclaim produces a smaller file and then stops, holding the old one until
| someone accepts. These cover what the two answers actually do to the disk,
| and the invariant the whole feature rests on: the file at the canonical path
| is always the current good one, so the worst outcome of a failed revert is a
| smaller file than the admin wanted — never a missing one.
|
*/

/** A rendition reclaim with its new file in place and the old one held aside. */
function renditionAwaitingReview(?Video $video = null): StorageReclaim
{
    $video ??= processedVideo();
    $dir = dirname($video->video_path);
    $live = $dir.'/processed/720p.mp4';
    $kept = $dir.'/processed/720p.reclaim-kept.mp4';

    // The live path already holds the new, smaller file; the kept path holds
    // the bytes it replaced.
    Storage::disk('public')->put($kept, str_repeat('o', 200_000));
    Storage::disk('public')->put($live, str_repeat('n', 120_000));

    return StorageReclaim::create([
        'video_id' => $video->id,
        'target' => StorageReclaim::TARGET_RENDITION,
        'quality' => '720p',
        'status' => StorageReclaim::AWAITING_REVIEW,
        'run_id' => (string) Str::uuid(),
        'source_path' => $live,
        'new_path' => $live,
        'kept_path' => $kept,
        'before_bytes' => 200_000,
        'after_bytes' => 120_000,
        'keep_until' => now()->addDays(14),
    ]);
}

/** An original reclaim: the new file is beside the old, which is still live. */
function originalAwaitingReview(?Video $video = null): StorageReclaim
{
    $video ??= processedVideo();
    $new = dirname($video->video_path).'/original_r1.mp4';

    Storage::disk('public')->put($new, str_repeat('n', 400_000));

    return StorageReclaim::create([
        'video_id' => $video->id,
        'target' => StorageReclaim::TARGET_ORIGINAL,
        'status' => StorageReclaim::AWAITING_REVIEW,
        'run_id' => (string) Str::uuid(),
        'source_path' => $video->video_path,
        'new_path' => $new,
        // For an original the kept file *is* the live one: video_path is not
        // repointed until accept, so nothing changes for viewers until then.
        'kept_path' => $video->video_path,
        'before_bytes' => 900_000,
        'after_bytes' => 400_000,
        'keep_until' => now()->addDays(14),
    ]);
}

// ── Accepting ───────────────────────────────────────────────────────────────

test('accepting a rendition releases the old bytes and busts the cache', function () {
    $reclaim = renditionAwaitingReview();
    $video = $reclaim->video;

    expect(reclaims()->accept($reclaim))->toBeTrue();

    $reclaim->refresh();
    $video->refresh();

    expect(Storage::disk('public')->exists($reclaim->source_path))->toBeTrue()
        // The kept copy is what gets freed.
        ->and(Storage::disk('public')->exists('videos/'.basename(dirname($video->video_path)).'/processed/720p.reclaim-kept.mp4'))->toBeFalse()
        ->and($reclaim->status)->toBe(StorageReclaim::ACCEPTED)
        ->and($reclaim->kept_path)->toBeNull()
        ->and($reclaim->finished_at)->not->toBeNull()
        // nginx caches mp4 for 30 days with no Cloudflare purge, so a file
        // replaced under its own name has to be requested afresh.
        ->and($video->media_version)->toBe(1)
        // The size columns follow the disk, or the saving is invisible
        // everywhere except this ledger.
        ->and($video->encodings()->where('quality', '720p')->value('size'))->toBe(120_000);
});

test('the rendition URL carries the new version and the original does not', function () {
    $reclaim = renditionAwaitingReview();
    reclaims()->accept($reclaim);

    $urls = $reclaim->video->fresh()->quality_urls;

    expect($urls['720p'])->toContain('?v=1')
        // A re-compressed original lands at a new filename, so it needs none.
        ->and($urls['original'])->not->toContain('?v=');
});

test('a cloud-hosted video never gets a version query string', function () {
    // It would break the signature on a pre-signed URL.
    $reclaim = renditionAwaitingReview();
    reclaims()->accept($reclaim);

    $video = $reclaim->video->fresh();
    $video->forceFill(['storage_disk' => 's3'])->saveQuietly();

    foreach ($video->fresh()->quality_urls as $url) {
        expect($url)->not->toContain('v=1');
    }
});

test('accepting an original repoints the video and deletes the old file', function () {
    $reclaim = originalAwaitingReview();
    $old = $reclaim->kept_path;

    expect(reclaims()->accept($reclaim))->toBeTrue();

    $video = $reclaim->video->fresh();

    expect($video->video_path)->toBe($reclaim->new_path)
        ->and(Storage::disk('public')->exists($old))->toBeFalse()
        ->and(Storage::disk('public')->exists($video->video_path))->toBeTrue()
        ->and($video->size)->toBe(400_000)
        // A new filename is its own cache-buster.
        ->and($video->media_version)->toBe(0);
});

test('accepting is idempotent when the kept file has already gone', function () {
    // Otherwise a second sweep pass over its own success would fail and stall
    // every row behind it.
    $reclaim = renditionAwaitingReview();
    Storage::disk('public')->delete($reclaim->kept_path);

    expect(reclaims()->accept($reclaim))->toBeTrue()
        ->and($reclaim->fresh()->status)->toBe(StorageReclaim::ACCEPTED);
});

test('only a row awaiting review can be accepted', function () {
    $reclaim = renditionAwaitingReview();
    $reclaim->forceFill(['status' => StorageReclaim::RUNNING])->save();

    expect(reclaims()->accept($reclaim))->toBeFalse()
        ->and($reclaim->fresh()->status)->toBe(StorageReclaim::RUNNING);
});

test('an original whose re-encode has vanished fails instead of clearing the column', function () {
    $reclaim = originalAwaitingReview();
    $path = $reclaim->video->video_path;
    Storage::disk('public')->delete($reclaim->new_path);

    expect(reclaims()->accept($reclaim))->toBeFalse()
        ->and($reclaim->fresh()->status)->toBe(StorageReclaim::FAILED)
        // The live file and the column are exactly as they were.
        ->and($reclaim->video->fresh()->video_path)->toBe($path)
        ->and(Storage::disk('public')->exists($path))->toBeTrue();
});

test('accepting re-indexes the folder and clears the storage report', function () {
    // Every write is saveQuietly(), which skips the observer that keeps
    // media_files.is_referenced honest — so this has to be explicit, or the
    // file the site now serves reads as "Unused" and is one click from
    // deletion.
    Queue::fake();
    $reclaim = renditionAwaitingReview();

    reclaims()->accept($reclaim);

    Queue::assertPushed(IndexMediaDirectoryJob::class);
});

// ── Reverting ───────────────────────────────────────────────────────────────

test('reverting a rendition puts the old bytes back', function () {
    $reclaim = renditionAwaitingReview();

    expect(reclaims()->revert($reclaim))->toBeTrue();

    $reclaim->refresh();

    expect(Storage::disk('public')->get($reclaim->source_path))->toStartWith('o')
        ->and(Storage::disk('public')->size($reclaim->source_path))->toBe(200_000)
        ->and(Storage::disk('public')->exists($reclaim->kept_path))->toBeFalse()
        ->and($reclaim->status)->toBe(StorageReclaim::REVERTED)
        // Viewers holding the smaller file must be told to refetch.
        ->and($reclaim->video->fresh()->media_version)->toBe(1);
});

test('reverting an original only discards the candidate', function () {
    $reclaim = originalAwaitingReview();
    $live = $reclaim->video->video_path;

    expect(reclaims()->revert($reclaim))->toBeTrue()
        // video_path was never repointed, so there is nothing to undo.
        ->and($reclaim->video->fresh()->video_path)->toBe($live)
        ->and(Storage::disk('public')->exists($live))->toBeTrue()
        ->and(Storage::disk('public')->exists($reclaim->new_path))->toBeFalse();
});

test('a revert with no kept file leaves the live file exactly as it was', function () {
    // The core data-safety guarantee. There are no backups of media, so a
    // failed revert must never be able to lose what is playing.
    $reclaim = renditionAwaitingReview();
    Storage::disk('public')->delete($reclaim->kept_path);

    $before = Storage::disk('public')->get($reclaim->source_path);

    expect(reclaims()->revert($reclaim))->toBeFalse();

    $reclaim->refresh();

    expect($reclaim->status)->toBe(StorageReclaim::REVERT_FAILED)
        ->and($reclaim->error)->toContain('no longer on disk')
        ->and(Storage::disk('public')->get($reclaim->source_path))->toBe($before);
});

test('an HLS revert restores the whole tree', function () {
    $video = processedVideo();
    $dir = dirname($video->video_path);

    // The tree was renamed aside rather than deleted — instant, and zero copy.
    Storage::disk('public')->move($dir.'/processed/hls', $dir.'/processed/hls.reclaim-kept');
    Storage::disk('public')->delete($dir.'/processed/master.m3u8');

    $reclaim = StorageReclaim::create([
        'video_id' => $video->id,
        'target' => StorageReclaim::TARGET_HLS,
        'status' => StorageReclaim::AWAITING_REVIEW,
        'run_id' => (string) Str::uuid(),
        'source_path' => $dir.'/processed/hls',
        'kept_path' => $dir.'/processed/hls.reclaim-kept',
        'before_bytes' => 150_000,
        'after_bytes' => 0,
        'keep_until' => now()->addDays(14),
    ]);

    expect(reclaims()->revert($reclaim))->toBeTrue()
        ->and(Storage::disk('public')->exists($dir.'/processed/hls/720p/segment_000.ts'))->toBeTrue()
        ->and(Storage::disk('public')->directoryExists($dir.'/processed/hls.reclaim-kept'))->toBeFalse();
});

test('accepting an HLS reclaim deletes the tree that was moved aside', function () {
    $video = processedVideo();
    $dir = dirname($video->video_path);
    Storage::disk('public')->move($dir.'/processed/hls', $dir.'/processed/hls.reclaim-kept');

    $reclaim = StorageReclaim::create([
        'video_id' => $video->id,
        'target' => StorageReclaim::TARGET_HLS,
        'status' => StorageReclaim::AWAITING_REVIEW,
        'run_id' => (string) Str::uuid(),
        'source_path' => $dir.'/processed/hls',
        'kept_path' => $dir.'/processed/hls.reclaim-kept',
        'before_bytes' => 150_000,
        'after_bytes' => 0,
        'keep_until' => now()->addDays(14),
    ]);

    expect(reclaims()->accept($reclaim))->toBeTrue()
        ->and(Storage::disk('public')->directoryExists($dir.'/processed/hls.reclaim-kept'))->toBeFalse();
});

// ── The review window ───────────────────────────────────────────────────────

test('the sweep accepts only rows whose window has passed', function () {
    $due = renditionAwaitingReview();
    $due->forceFill(['keep_until' => now()->subDay()])->save();

    $waiting = renditionAwaitingReview(processedVideo());

    expect(reclaims()->sweep())->toBe(1)
        // Auto-accepted, which frees the bytes just as much as a manual yes —
        // and is recorded distinctly so it is auditable.
        ->and($due->fresh()->status)->toBe(StorageReclaim::EXPIRED)
        ->and($waiting->fresh()->status)->toBe(StorageReclaim::AWAITING_REVIEW)
        ->and(Storage::disk('public')->exists($due->kept_path))->toBeFalse();
});

test('the sweep command reports what it freed and can be rehearsed', function () {
    $due = renditionAwaitingReview();
    $due->forceFill(['keep_until' => now()->subDay()])->save();

    $this->artisan('storage:reclaim-sweep --dry-run')
        ->expectsOutputToContain('would be accepted')
        ->assertSuccessful();

    // A rehearsal must change nothing.
    expect($due->fresh()->status)->toBe(StorageReclaim::AWAITING_REVIEW);

    $this->artisan('storage:reclaim-sweep')
        ->expectsOutputToContain('Accepted 1 reclaim')
        ->assertSuccessful();

    expect($due->fresh()->status)->toBe(StorageReclaim::EXPIRED);
});

test('the sweep says so when there is nothing to do', function () {
    $this->artisan('storage:reclaim-sweep')
        ->expectsOutputToContain('No storage reclaims are due')
        ->assertSuccessful();
});

test('the review window follows the configured retention', function () {
    Setting::set('reclaim_keep_days', 30, 'general', 'integer');

    expect(reclaims()->keepUntil()->diffInDays(now()->addDays(30)))->toBeLessThan(1);
});

// ── Interaction with the encoder ────────────────────────────────────────────

test('a reclaim awaiting review does not make the video look like it is encoding', function () {
    // The reason this is not tracked on video_encodings: a non-terminal row
    // there would make isEncoding() true forever, permanently hiding "encode
    // missing renditions" and blocking every future reclaim.
    $reclaim = renditionAwaitingReview();
    $video = $reclaim->video->fresh();

    expect($video->isEncoding())->toBeFalse()
        ->and($video->canReencode())->toBeTrue();
});

// ── The review page ─────────────────────────────────────────────────────────

test('the review page separates space freed from space merely held', function () {
    asAdmin();

    $held = renditionAwaitingReview();
    $freed = renditionAwaitingReview(processedVideo());
    reclaims()->accept($freed);

    $totals = Livewire::test(StorageReclaims::class)->instance()->getTotalsProperty();

    expect($totals['reclaimed'])->toBe(80_000)
        // Not saved: both copies are still on disk while it waits.
        ->and($totals['awaiting'])->toBe(80_000)
        ->and($totals['awaiting_count'])->toBe(1);
});

test('the review page lists reclaims and offers a decision on the waiting ones', function () {
    asAdmin();
    $reclaim = renditionAwaitingReview();

    Livewire::test(StorageReclaims::class)
        ->assertCanSeeTableRecords([$reclaim])
        ->assertTableActionVisible('accept', $reclaim)
        ->assertTableActionVisible('revert', $reclaim);
});

test('accepting from the review page frees the bytes', function () {
    asAdmin();
    $reclaim = renditionAwaitingReview();

    Livewire::test(StorageReclaims::class)->callTableAction('accept', $reclaim);

    expect($reclaim->fresh()->status)->toBe(StorageReclaim::ACCEPTED)
        ->and(Storage::disk('public')->exists($reclaim->kept_path))->toBeFalse();
});

test('a settled reclaim offers no decision', function () {
    asAdmin();
    $reclaim = renditionAwaitingReview();
    reclaims()->accept($reclaim);

    Livewire::test(StorageReclaims::class)
        ->assertTableActionHidden('accept', $reclaim->fresh())
        ->assertTableActionHidden('revert', $reclaim->fresh());
});

test('the navigation badge counts what is waiting on a human', function () {
    expect(StorageReclaims::getNavigationBadge())->toBeNull();

    renditionAwaitingReview();

    expect(StorageReclaims::getNavigationBadge())->toBe('1');
});

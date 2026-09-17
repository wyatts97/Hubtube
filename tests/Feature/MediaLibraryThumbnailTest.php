<?php

use App\Filament\Pages\MediaLibrary;
use App\Jobs\GenerateMediaThumbnailJob;
use App\Models\MediaFile;
use App\Services\FileManagerThumbnailService;
use App\Services\Media\MediaIndexService;
use App\Services\Media\MediaThumbnailDispatcher;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

/*
|--------------------------------------------------------------------------
| Media Library thumbnails
|--------------------------------------------------------------------------
|
| Generation used to happen while the page was rendering, guarded by a cache
| entry that recorded the answer from *before* generation and never corrected
| it — so every render re-decoded and re-encoded every thumbnail on the page
| for five minutes at a time. It is now a state machine on the row plus a
| queued job, and these cover the transitions.
|
| Note that QUEUE_CONNECTION is sync in the suite, so any test that does not
| want the job to really run GD has to fake the queue first.
|
*/

beforeEach(function () {
    asAdmin();
});

/** A tiny real PNG, built with GD so the same library can read it back. */
function thumbSourceImage(): string
{
    $image = imagecreatetruecolor(8, 8);

    ob_start();
    imagepng($image);
    $data = (string) ob_get_clean();

    imagedestroy($image);

    return $data;
}

function indexThumbFixtures(): void
{
    app(MediaIndexService::class)->indexAll();
}

// ── Dispatch policy ─────────────────────────────────────────────────────────

test('a newly indexed file starts pending and is not queued by the indexer', function () {
    Queue::fake();

    Storage::disk('public')->put('media/photo.png', thumbSourceImage());
    indexThumbFixtures();

    expect(MediaFile::where('path', 'media/photo.png')->firstOrFail()->thumbnail_state)
        ->toBe(MediaFile::THUMB_PENDING);

    // A full index of a large library must not fan out a job per file.
    Queue::assertNothingPushed();
});

test('rendering a page queues thumbnails for that page only', function () {
    Queue::fake();

    $perPage = (int) config('hubtube.media_library.per_page', 50);

    for ($i = 1; $i <= $perPage + 15; $i++) {
        Storage::disk('public')->put(sprintf('media/file-%03d.png', $i), thumbSourceImage());
    }

    indexThumbFixtures();

    Livewire::test(MediaLibrary::class)->instance()->getFilesProperty();

    Queue::assertPushed(GenerateMediaThumbnailJob::class, $perPage);
});

test('thumbnail jobs go on their own queue', function () {
    Queue::fake();

    Storage::disk('public')->put('media/photo.png', thumbSourceImage());
    indexThumbFixtures();

    Livewire::test(MediaLibrary::class)->instance()->getFilesProperty();

    Queue::assertPushedOn('media-thumbnails', GenerateMediaThumbnailJob::class);
});

test('a second render queues nothing more', function () {
    // The direct regression test for the false-cache bug: a page that has
    // already asked for its thumbnails must not ask again.
    Queue::fake();

    Storage::disk('public')->put('media/photo.png', thumbSourceImage());
    indexThumbFixtures();

    Livewire::test(MediaLibrary::class)->instance()->getFilesProperty();
    Queue::assertPushed(GenerateMediaThumbnailJob::class, 1);

    Livewire::test(MediaLibrary::class)->instance()->getFilesProperty();
    Queue::assertPushed(GenerateMediaThumbnailJob::class, 1);
});

// ── Generation ──────────────────────────────────────────────────────────────

test('running the job produces a thumbnail and records its dimensions', function () {
    Storage::disk('public')->put('media/photo.png', thumbSourceImage());
    indexThumbFixtures();

    $file = MediaFile::where('path', 'media/photo.png')->firstOrFail();

    app(GenerateMediaThumbnailJob::class, ['mediaFileId' => $file->id])
        ->handle(app(FileManagerThumbnailService::class));

    $file->refresh();

    expect($file->thumbnail_state)->toBe(MediaFile::THUMB_READY)
        ->and($file->thumbnail_path)->not->toBeNull()
        ->and(Storage::disk('public')->exists($file->thumbnail_path))->toBeTrue()
        ->and($file->width)->toBe(8)
        ->and($file->height)->toBe(8)
        ->and($file->thumbnail_generated_at)->not->toBeNull();
});

test('a ready thumbnail is what the grid renders', function () {
    Storage::disk('public')->put('media/photo.png', thumbSourceImage());
    indexThumbFixtures();

    $file = MediaFile::where('path', 'media/photo.png')->firstOrFail();
    app(GenerateMediaThumbnailJob::class, ['mediaFileId' => $file->id])
        ->handle(app(FileManagerThumbnailService::class));

    $row = collect(Livewire::test(MediaLibrary::class)->instance()->getFilesProperty()->items())->first();

    expect($row['thumbnail_pending'])->toBeFalse()
        ->and($row['thumbnail'])->toContain('.webp');
});

test('a pending file shows a placeholder rather than a broken image', function () {
    Queue::fake();

    Storage::disk('public')->put('media/photo.png', thumbSourceImage());
    indexThumbFixtures();

    $row = collect(Livewire::test(MediaLibrary::class)->instance()->getFilesProperty()->items())->first();

    expect($row['thumbnail_pending'])->toBeTrue()
        // The type icon, not a URL pointing at a file that does not exist yet.
        ->and($row['thumbnail'])->toStartWith('data:image/svg+xml');
});

// ── Formats ─────────────────────────────────────────────────────────────────

test('an svg is its own thumbnail and never decoded', function () {
    Storage::disk('public')->put('media/logo.svg', '<svg xmlns="http://www.w3.org/2000/svg"/>');
    indexThumbFixtures();

    $file = MediaFile::where('path', 'media/logo.svg')->firstOrFail();
    app(GenerateMediaThumbnailJob::class, ['mediaFileId' => $file->id])
        ->handle(app(FileManagerThumbnailService::class));

    $file->refresh();

    expect($file->thumbnail_state)->toBe(MediaFile::THUMB_READY)
        ->and($file->thumbnail_path)->toBeNull();

    $row = collect(Livewire::test(MediaLibrary::class)->instance()->getFilesProperty()->items())->first();
    expect($row['thumbnail'])->toContain('media/logo.svg');
});

test('formats GD cannot read are marked unsupported and never queued', function () {
    Queue::fake();

    Storage::disk('public')->put('media/old.bmp', 'x');
    Storage::disk('public')->put('media/fav.ico', 'x');
    Storage::disk('public')->put('media/notes.pdf', 'x');
    indexThumbFixtures();

    Livewire::test(MediaLibrary::class)->instance()->getFilesProperty();

    // Settled without spending a worker on finding out.
    expect(MediaFile::where('path', 'media/old.bmp')->firstOrFail()->thumbnail_state)
        ->toBe(MediaFile::THUMB_UNSUPPORTED)
        ->and(MediaFile::where('path', 'media/notes.pdf')->firstOrFail()->thumbnail_state)
        ->toBe(MediaFile::THUMB_UNSUPPORTED);

    Queue::assertNothingPushed();
});

test('a corrupt image fails once and is not retried on the next render', function () {
    Queue::fake();

    Storage::disk('public')->put('media/broken.png', 'this is not a png');
    indexThumbFixtures();

    $file = MediaFile::where('path', 'media/broken.png')->firstOrFail();
    app(GenerateMediaThumbnailJob::class, ['mediaFileId' => $file->id])
        ->handle(app(FileManagerThumbnailService::class));

    $file->refresh();

    expect($file->thumbnail_state)->toBe(MediaFile::THUMB_FAILED)
        ->and($file->thumbnail_attempts)->toBe(1)
        ->and($file->thumbnail_error)->not->toBeNull();

    // Rendering again must not keep hammering it.
    Livewire::test(MediaLibrary::class)->instance()->getFilesProperty();
    Queue::assertNothingPushed();

    expect($file->refresh()->thumbnail_attempts)->toBe(1);
});

test('a missing ffmpeg is unavailable, not failed, so it retries later', function () {
    // unavailable is about the box, not the file — it must stay retryable.
    Storage::disk('public')->put('media/clip.mp4', 'x');
    indexThumbFixtures();

    $file = MediaFile::where('path', 'media/clip.mp4')->firstOrFail();
    $file->update(['thumbnail_state' => MediaFile::THUMB_UNAVAILABLE]);

    expect($file->refresh()->thumbnailPending())->toBeTrue();

    Queue::fake();
    app(MediaThumbnailDispatcher::class)->dispatchFor([$file]);
    Queue::assertPushed(GenerateMediaThumbnailJob::class, 1);
});

// ── Cleanup ─────────────────────────────────────────────────────────────────

test('deleting a file removes its thumbnail', function () {
    Storage::disk('public')->put('media/photo.png', thumbSourceImage());
    indexThumbFixtures();

    $file = MediaFile::where('path', 'media/photo.png')->firstOrFail();
    app(GenerateMediaThumbnailJob::class, ['mediaFileId' => $file->id])
        ->handle(app(FileManagerThumbnailService::class));

    $thumbPath = $file->refresh()->thumbnail_path;
    expect(Storage::disk('public')->exists($thumbPath))->toBeTrue();

    Livewire::test(MediaLibrary::class)
        ->call('confirmDelete', 'media/photo.png')
        ->call('deleteFile');

    expect(Storage::disk('public')->exists($thumbPath))->toBeFalse()
        ->and(MediaFile::where('path', 'media/photo.png')->exists())->toBeFalse();
});

test('pruning removes thumbnails nothing claims and keeps the rest', function () {
    Storage::disk('public')->put('media/photo.png', thumbSourceImage());
    indexThumbFixtures();

    $file = MediaFile::where('path', 'media/photo.png')->firstOrFail();
    app(GenerateMediaThumbnailJob::class, ['mediaFileId' => $file->id])
        ->handle(app(FileManagerThumbnailService::class));

    $owned = $file->refresh()->thumbnail_path;
    $orphan = app(FileManagerThumbnailService::class)->thumbnailPathFor('media/long-gone.png');
    Storage::disk('public')->put($orphan, 'stale');

    $this->artisan('media:thumbnails --prune')->assertSuccessful();

    expect(Storage::disk('public')->exists($orphan))->toBeFalse()
        ->and(Storage::disk('public')->exists($owned))->toBeTrue();
});

test('regenerating discards the old thumbnail and queues a fresh one', function () {
    Storage::disk('public')->put('media/photo.png', thumbSourceImage());
    indexThumbFixtures();

    $file = MediaFile::where('path', 'media/photo.png')->firstOrFail();
    app(GenerateMediaThumbnailJob::class, ['mediaFileId' => $file->id])
        ->handle(app(FileManagerThumbnailService::class));

    $thumbPath = $file->refresh()->thumbnail_path;

    Queue::fake();
    Livewire::test(MediaLibrary::class)->call('regenerateThumbnail', 'media/photo.png');

    expect(Storage::disk('public')->exists($thumbPath))->toBeFalse()
        ->and($file->refresh()->thumbnail_state)->toBe(MediaFile::THUMB_QUEUED);

    Queue::assertPushed(GenerateMediaThumbnailJob::class, 1);
});

// ── Backfill command ────────────────────────────────────────────────────────

test('the backfill command queues pending files up to its limit', function () {
    Queue::fake();

    for ($i = 1; $i <= 5; $i++) {
        Storage::disk('public')->put("media/file-{$i}.png", thumbSourceImage());
    }

    indexThumbFixtures();

    $this->artisan('media:thumbnails --limit=3')->assertSuccessful();

    Queue::assertPushed(GenerateMediaThumbnailJob::class, 3);
});

test('retry-failed puts failed files back in the queue', function () {
    Queue::fake();

    Storage::disk('public')->put('media/broken.png', 'not a png');
    indexThumbFixtures();

    MediaFile::where('path', 'media/broken.png')->update([
        'thumbnail_state' => MediaFile::THUMB_FAILED,
        'thumbnail_error' => 'Unable to decode input',
    ]);

    // Without the flag, a failed file is left alone.
    $this->artisan('media:thumbnails')->assertSuccessful();
    Queue::assertNothingPushed();

    $this->artisan('media:thumbnails --retry-failed')->assertSuccessful();
    Queue::assertPushed(GenerateMediaThumbnailJob::class, 1);

    expect(MediaFile::where('path', 'media/broken.png')->firstOrFail()->thumbnail_error)->toBeNull();
});

test('a job whose file has gone does nothing', function () {
    Storage::disk('public')->put('media/photo.png', thumbSourceImage());
    indexThumbFixtures();

    $id = MediaFile::where('path', 'media/photo.png')->firstOrFail()->id;
    MediaFile::whereKey($id)->delete();

    app(GenerateMediaThumbnailJob::class, ['mediaFileId' => $id])
        ->handle(app(FileManagerThumbnailService::class));
})->throwsNoExceptions();

test('the grid polls only while something is pending', function () {
    Queue::fake();

    Storage::disk('public')->put('media/photo.png', thumbSourceImage());
    indexThumbFixtures();

    $component = Livewire::test(MediaLibrary::class);
    $component->instance()->getFilesProperty();

    expect($component->instance()->getHasPendingThumbnailsProperty())->toBeTrue();

    MediaFile::where('path', 'media/photo.png')->update([
        'thumbnail_state' => MediaFile::THUMB_READY,
        'thumbnail_path' => 'thumbnails/.filemanager/aa/x.webp',
    ]);

    expect($component->instance()->getHasPendingThumbnailsProperty())->toBeFalse();
});

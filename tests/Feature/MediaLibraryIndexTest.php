<?php

use App\Models\MediaFile;
use App\Models\MediaFolder;
use App\Models\Video;
use App\Services\Media\MediaGuard;
use App\Services\Media\MediaIndexService;
use App\Services\Media\MediaPathGuard;
use App\Services\Media\MediaReferenceResolver;
use Illuminate\Support\Facades\Storage;

/*
|--------------------------------------------------------------------------
| Media Library index
|--------------------------------------------------------------------------
|
| The index makes browsing indexed SQL instead of a filesystem walk, but the
| disk stays the source of truth — so what matters is that the two cannot
| silently disagree. These cover the sync contract, the folder rollups, and
| the rule that a stale index can never authorise a delete.
|
*/

// ── Indexing ────────────────────────────────────────────────────────────────

test('indexing records the columns the listing sorts and filters on', function () {
    Storage::disk('public')->put('media/promos/Clip One.MP4', str_repeat('x', 2048));

    $this->artisan('media:index')->assertSuccessful();

    $file = MediaFile::where('path', 'media/promos/Clip One.MP4')->firstOrFail();

    expect($file->directory)->toBe('media/promos')
        ->and($file->directory_hash)->toBe(md5('media/promos'))
        ->and($file->root)->toBe('media')
        ->and($file->depth)->toBe(2)
        ->and($file->name)->toBe('Clip One.MP4')
        // Folded, because SQLite sorts case-sensitively and MySQL does not.
        ->and($file->name_lower)->toBe('clip one.mp4')
        ->and($file->extension)->toBe('mp4')
        ->and($file->type)->toBe('video')
        ->and($file->size)->toBe(2048)
        ->and($file->modified_at)->not->toBeNull()
        ->and($file->thumbnail_state)->toBe(MediaFile::THUMB_PENDING);
});

test('a file written outside the app appears once the index is refreshed', function () {
    $index = app(MediaIndexService::class);

    Storage::disk('public')->put('media/first.jpg', 'x');
    $index->indexAll();

    expect(MediaFile::count())->toBe(1);

    // Something else writes to the disk — the encoder, an import, a shell.
    Storage::disk('public')->put('media/second.jpg', 'x');

    expect(MediaFile::count())->toBe(1);

    $index->indexAll();

    expect(MediaFile::pluck('path')->sort()->values()->all())
        ->toBe(['media/first.jpg', 'media/second.jpg']);
});

test('a file deleted outside the app is dropped from the index', function () {
    $index = app(MediaIndexService::class);

    Storage::disk('public')->put('media/gone.jpg', 'x');
    $index->indexAll();

    Storage::disk('public')->delete('media/gone.jpg');
    $index->indexAll();

    expect(MediaFile::where('path', 'media/gone.jpg')->exists())->toBeFalse();
});

test('a whole directory that disappears is pruned', function () {
    $index = app(MediaIndexService::class);

    Storage::disk('public')->put('media/batch/one.jpg', 'x');
    $index->indexAll();

    expect(MediaFolder::where('path', 'media/batch')->exists())->toBeTrue();

    Storage::disk('public')->deleteDirectory('media/batch');
    $index->indexAll(prune: true);

    expect(MediaFile::where('path', 'media/batch/one.jpg')->exists())->toBeFalse()
        ->and(MediaFolder::where('path', 'media/batch')->exists())->toBeFalse();
});

test('re-indexing is idempotent and leaves untouched rows alone', function () {
    $index = app(MediaIndexService::class);

    Storage::disk('public')->put('media/stable.jpg', 'x');
    $index->indexAll();

    $before = MediaFile::where('path', 'media/stable.jpg')->firstOrFail();
    $firstUpdatedAt = $before->updated_at;

    $this->travel(2)->minutes();
    $index->indexAll();

    $after = MediaFile::where('path', 'media/stable.jpg')->firstOrFail();

    expect(MediaFile::count())->toBe(1)
        ->and($after->updated_at->timestamp)->toBe($firstUpdatedAt->timestamp);
});

test('replaced content resets the thumbnail so it is regenerated', function () {
    $index = app(MediaIndexService::class);

    Storage::disk('public')->put('media/photo.jpg', 'x');
    $index->indexAll();

    MediaFile::where('path', 'media/photo.jpg')->update([
        'thumbnail_state' => MediaFile::THUMB_READY,
        'thumbnail_path' => 'thumbnails/.filemanager/aa/whatever.webp',
        'duration_seconds' => 12,
    ]);

    $this->travel(2)->minutes();
    Storage::disk('public')->put('media/photo.jpg', str_repeat('y', 500));
    $index->indexAll();

    $file = MediaFile::where('path', 'media/photo.jpg')->firstOrFail();

    expect($file->thumbnail_state)->toBe(MediaFile::THUMB_PENDING)
        ->and($file->duration_seconds)->toBeNull()
        ->and($file->size)->toBe(500);
});

test('excluded paths are never indexed', function () {
    Storage::disk('public')->put('media/real.jpg', 'x');
    Storage::disk('public')->put('media/temp/scratch.jpg', 'x');
    Storage::disk('public')->put('thumbnails/.filemanager/aa/cached.webp', 'x');

    config(['hubtube.media_library.excluded_paths' => ['media/temp']]);

    app(MediaIndexService::class)->indexAll();

    expect(MediaFile::pluck('path')->all())->toBe(['media/real.jpg'])
        ->and(MediaFolder::where('path', 'media/temp')->exists())->toBeFalse();
});

test('a single file can be indexed on its own', function () {
    Storage::disk('public')->put('media/one-off.png', 'x');

    $file = app(MediaIndexService::class)->indexPath('media/one-off.png');

    expect($file)->not->toBeNull()
        ->and($file->type)->toBe('image')
        ->and(MediaFolder::where('path', 'media')->firstOrFail()->direct_file_count)->toBe(1);
});

test('a path outside the allowed roots is never indexed', function () {
    Storage::disk('public')->put('secret.txt', 'x');

    expect(app(MediaIndexService::class)->indexPath('secret.txt'))->toBeNull()
        ->and(app(MediaIndexService::class)->indexPath('../secret.txt'))->toBeNull()
        ->and(MediaFile::count())->toBe(0);
});

// ── Folder rollups ──────────────────────────────────────────────────────────

test('folder counts and sizes roll up through every ancestor', function () {
    Storage::disk('public')->put('media/a.jpg', str_repeat('x', 100));
    Storage::disk('public')->put('media/2026/b.jpg', str_repeat('x', 200));
    Storage::disk('public')->put('media/2026/01/c.jpg', str_repeat('x', 400));

    app(MediaIndexService::class)->indexAll();

    $root = MediaFolder::where('path', 'media')->firstOrFail();
    $year = MediaFolder::where('path', 'media/2026')->firstOrFail();
    $month = MediaFolder::where('path', 'media/2026/01')->firstOrFail();

    expect($month->direct_file_count)->toBe(1)
        ->and($month->total_file_count)->toBe(1)
        ->and($month->total_size)->toBe(400);

    expect($year->direct_file_count)->toBe(1)
        ->and($year->total_file_count)->toBe(2)
        ->and($year->total_size)->toBe(600)
        ->and($year->child_folder_count)->toBe(1);

    expect($root->direct_file_count)->toBe(1)
        ->and($root->total_file_count)->toBe(3)
        ->and($root->total_size)->toBe(700)
        ->and($root->child_folder_count)->toBe(1);
});

test('adding a file deep in the tree updates its ancestors immediately', function () {
    $index = app(MediaIndexService::class);

    Storage::disk('public')->put('media/2026/01/a.jpg', str_repeat('x', 100));
    $index->indexAll();

    // Not a full scan — the single-file path has to roll up too.
    Storage::disk('public')->put('media/2026/01/b.jpg', str_repeat('x', 100));
    $index->indexPath('media/2026/01/b.jpg');

    expect(MediaFolder::where('path', 'media')->firstOrFail()->total_file_count)->toBe(2)
        ->and(MediaFolder::where('path', 'media')->firstOrFail()->total_size)->toBe(200);
});

test('the tree is one query', function () {
    Storage::disk('public')->put('media/2026/01/a.jpg', 'x');
    Storage::disk('public')->put('videos/clip/b.mp4', 'x');
    app(MediaIndexService::class)->indexAll();

    $this->expectsDatabaseQueryCount(1);

    MediaFolder::query()->orderBy('depth')->orderBy('name_lower')->get();
});

// ── References ──────────────────────────────────────────────────────────────

test('references are found across every path column and batched', function () {
    Storage::disk('public')->put('videos/clip/video.mp4', 'x');
    Storage::disk('public')->put('videos/clip/poster.jpg', 'x');
    Storage::disk('public')->put('media/unused.jpg', 'x');

    Video::factory()->create([
        'video_path' => 'videos/clip/video.mp4',
        'thumbnail' => 'videos/clip/poster.jpg',
    ]);

    app(MediaIndexService::class)->indexAll();

    expect(MediaFile::where('path', 'videos/clip/video.mp4')->firstOrFail()->is_referenced)->toBeTrue()
        ->and(MediaFile::where('path', 'videos/clip/poster.jpg')->firstOrFail()->references)
        ->toBe([['model' => 'Video', 'id' => 1, 'field' => 'thumbnail']])
        ->and(MediaFile::where('path', 'media/unused.jpg')->firstOrFail()->is_referenced)->toBeFalse();
});

test('resolving references for many paths costs a constant number of queries', function () {
    $paths = [];

    for ($i = 1; $i <= 50; $i++) {
        $paths[] = "media/file-{$i}.jpg";
    }

    // Two SELECTs: one for videos, one for images — not two per path.
    $this->expectsDatabaseQueryCount(2);

    app(MediaReferenceResolver::class)->forPaths($paths);
});

test('a soft-deleted video still protects its files', function () {
    // findByFilePath() had no withTrashed(), so a soft-deleted video's files
    // looked unreferenced and were deletable — silently breaking restore.
    Storage::disk('public')->put('videos/clip/video.mp4', 'x');
    $video = Video::factory()->create(['video_path' => 'videos/clip/video.mp4']);
    $video->delete();

    expect(app(MediaReferenceResolver::class)->forPath('videos/clip/video.mp4'))->not->toBeEmpty()
        ->and(app(MediaGuard::class)->isDeletable('videos/clip/video.mp4'))->toBeFalse();
});

test('the guard checks the database, not the indexed flag', function () {
    Storage::disk('public')->put('media/in-use.mp4', 'x');
    Video::factory()->create(['video_path' => 'media/in-use.mp4']);

    app(MediaIndexService::class)->indexAll();

    // Corrupt the denormalised flag in both directions. The guard must ignore
    // it entirely: it is a badge, not a permission.
    MediaFile::where('path', 'media/in-use.mp4')->update(['is_referenced' => false]);

    expect(app(MediaGuard::class)->isDeletable('media/in-use.mp4'))->toBeFalse();

    Storage::disk('public')->put('media/free.jpg', 'x');
    app(MediaIndexService::class)->indexPath('media/free.jpg');
    MediaFile::where('path', 'media/free.jpg')->update(['is_referenced' => true]);

    expect(app(MediaGuard::class)->isDeletable('media/free.jpg'))->toBeTrue();
});

test('a reference removed since the last sync clears the flag', function () {
    Storage::disk('public')->put('media/clip.mp4', 'x');
    $video = Video::factory()->create(['video_path' => 'media/clip.mp4']);

    $index = app(MediaIndexService::class);
    $index->indexAll();

    expect(MediaFile::where('path', 'media/clip.mp4')->firstOrFail()->is_referenced)->toBeTrue();

    $video->forceDelete();
    app(MediaReferenceResolver::class)->syncForPaths(['media/clip.mp4']);

    $file = MediaFile::where('path', 'media/clip.mp4')->firstOrFail();

    expect($file->is_referenced)->toBeFalse()
        ->and($file->references)->toBeNull();
});

// ── Staleness ───────────────────────────────────────────────────────────────

test('a directory changed on disk is reported stale', function () {
    $index = app(MediaIndexService::class);

    Storage::disk('public')->put('media/a.jpg', 'x');
    $index->indexAll();

    expect($index->isStale('media'))->toBeFalse()
        ->and($index->isIndexed('media'))->toBeTrue();

    // Wind the *record* of when we indexed back rather than travelling the
    // clock: the comparison is against the directory's real filesystem mtime,
    // which Carbon's test clock does not move.
    MediaFolder::where('path', 'media')->update(['indexed_at' => now()->subHour()]);
    Storage::disk('public')->put('media/b.jpg', 'x');

    expect($index->isStale('media'))->toBeTrue();
});

test('a directory that was never indexed counts as stale', function () {
    Storage::disk('public')->put('media/a.jpg', 'x');

    expect(app(MediaIndexService::class)->isStale('media'))->toBeTrue()
        ->and(app(MediaIndexService::class)->isIndexed('media'))->toBeFalse();
});

test('an incremental pass skips unchanged directories but keeps their rows', function () {
    $index = app(MediaIndexService::class);

    Storage::disk('public')->put('media/a.jpg', 'x');
    Storage::disk('public')->put('media/nested/b.jpg', 'x');
    $index->indexAll();

    // Nothing changed, so no directory is re-read...
    $result = $index->indexAll(prune: true, full: false);

    expect($result['directories'])->toBe(0)
        // ...and nothing is mistaken for a vanished file.
        ->and(MediaFile::count())->toBe(2);
});

// ── Path guard ──────────────────────────────────────────────────────────────

test('the path guard refuses everything outside the library', function () {
    $guard = app(MediaPathGuard::class);

    foreach (['', '..', '../storage', 'media/../../.env', '/etc/passwd', 'medialibrary'] as $path) {
        expect($guard->isAllowed($path))->toBeFalse("{$path} should be refused");
    }

    foreach (['media', 'media/promos', 'videos/clip-slug'] as $path) {
        expect($guard->isAllowed($path))->toBeTrue("{$path} should be allowed");
    }
});

test('the path guard normalises without hiding traversal', function () {
    $guard = app(MediaPathGuard::class);

    expect($guard->sanitize('/media//promos/'))->toBe('media/promos')
        ->and($guard->sanitize('media\\promos'))->toBe('media/promos')
        ->and($guard->sanitize('media/./promos'))->toBe('media/promos')
        // ".." survives sanitising so isAllowed() can reject it, rather than
        // being quietly rewritten into a path that looks legitimate.
        ->and($guard->sanitize('media/../../etc'))->toBe('media/../../etc')
        ->and($guard->isAllowed($guard->sanitize('media/../../etc')))->toBeFalse();
});

test('paths a record locates by convention are marked protected', function () {
    $guard = app(MediaPathGuard::class);

    expect($guard->isProtected('videos/my-slug'))->toBeTrue()
        ->and($guard->isProtected('videos/my-slug/720p.mp4'))->toBeTrue()
        ->and($guard->isProtected('images/01hx/original.jpg'))->toBeTrue()
        ->and($guard->isProtected('media/free.jpg'))->toBeFalse();
});

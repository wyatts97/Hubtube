<?php

use App\Filament\Pages\MediaLibrary;
use App\Models\MediaFile;
use App\Services\Media\MediaFolderRollup;
use App\Services\Media\MediaIndexService;
use App\Services\Media\MediaStorageReport;
use App\Support\Bytes;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

/*
|--------------------------------------------------------------------------
| Media Library — flat storage view and storage report
|--------------------------------------------------------------------------
|
| Browsing was folder-first, which is no help at all in answering "what is
| eating my disk". Flat mode is the whole library in one sortable table, and
| the storage strip says where the bytes went before you touch anything.
|
*/

beforeEach(function () {
    asAdmin();
});

/** Write files with explicit sizes and index them. */
function seedSized(array $pathsAndSizes): void
{
    foreach ($pathsAndSizes as $path => $bytes) {
        Storage::disk('public')->put($path, str_repeat('x', $bytes));
    }

    app(MediaIndexService::class)->indexAll();
    MediaStorageReport::forget();
}

function flatNames($component): array
{
    return collect($component->instance()->getFilesProperty()->items())->pluck('name')->all();
}

// ── Flat mode ───────────────────────────────────────────────────────────────

test('entering flat mode widens the scope to the whole library', function () {
    seedSized([
        'media/a.jpg' => 100,
        'videos/clip/b.mp4' => 200,
        'images/abc/c.jpg' => 300,
    ]);

    $component = Livewire::test(MediaLibrary::class)->call('setViewMode', 'flat');

    expect($component->get('searchScope'))->toBe('library')
        ->and(flatNames($component))->toHaveCount(3);
});

test('leaving flat mode returns to the folder you were browsing', function () {
    seedSized(['media/promos/a.jpg' => 100, 'videos/clip/b.mp4' => 200]);

    $component = Livewire::test(MediaLibrary::class)
        ->call('openDirectory', 'media/promos')
        ->call('setViewMode', 'flat');

    expect(flatNames($component))->toHaveCount(2);

    $component->call('setViewMode', 'grid');

    expect($component->get('searchScope'))->toBe('folder')
        // currentDirectory was kept, so you land back where you were.
        ->and($component->get('currentDirectory'))->toBe('media/promos')
        ->and(flatNames($component))->toBe(['a.jpg']);
});

test('flat mode hides the folder tree and the details pane', function () {
    seedSized(['media/a.jpg' => 100]);

    $grid = Livewire::test(MediaLibrary::class);
    expect($grid->html())->toContain('ht-ml-sidebar')->toContain('ht-ml-details');

    $flat = Livewire::test(MediaLibrary::class)->call('setViewMode', 'flat');
    expect($flat->html())->not->toContain('ht-ml-sidebar')
        ->and($flat->html())->not->toContain('ht-ml-details ht-ml-panel');
});

test('an unknown view mode is ignored', function () {
    $component = Livewire::test(MediaLibrary::class)->call('setViewMode', 'carousel');

    expect($component->get('viewMode'))->toBe('grid');
});

// ── Sorting from the headers ────────────────────────────────────────────────

test('files across every root sort by size, biggest first', function () {
    seedSized([
        'media/small.jpg' => 100,
        'videos/clip/huge.mp4' => 5000,
        'images/abc/medium.jpg' => 1000,
    ]);

    $component = Livewire::test(MediaLibrary::class)
        ->call('setViewMode', 'flat')
        ->call('sortByColumn', 'size');

    expect($component->get('sortBy'))->toBe('size')
        // Size and age default to descending: you look for the biggest first.
        ->and($component->get('sortDirection'))->toBe('desc')
        ->and(flatNames($component))->toBe(['huge.mp4', 'medium.jpg', 'small.jpg']);
});

test('clicking the active column only flips the direction', function () {
    seedSized(['media/a.jpg' => 100, 'media/b.jpg' => 5000]);

    $component = Livewire::test(MediaLibrary::class)
        ->call('setViewMode', 'flat')
        ->call('sortByColumn', 'size');

    expect(flatNames($component))->toBe(['b.jpg', 'a.jpg']);

    $component->call('sortByColumn', 'size');

    expect($component->get('sortBy'))->toBe('size')
        ->and($component->get('sortDirection'))->toBe('asc')
        ->and(flatNames($component))->toBe(['a.jpg', 'b.jpg']);
});

test('name and type sorts start ascending', function () {
    seedSized(['media/a.jpg' => 100]);

    $component = Livewire::test(MediaLibrary::class)->call('sortByColumn', 'name');
    expect($component->get('sortDirection'))->toBe('asc');

    $component->call('sortByColumn', 'type');
    expect($component->get('sortDirection'))->toBe('asc');
});

test('files can be sorted by which root they live in', function () {
    seedSized([
        'videos/clip/v.mp4' => 100,
        'images/abc/i.jpg' => 100,
        'media/m.jpg' => 100,
    ]);

    $component = Livewire::test(MediaLibrary::class)
        ->call('setViewMode', 'flat')
        ->call('sortByColumn', 'root');

    expect(collect($component->instance()->getFilesProperty()->items())->pluck('root')->all())
        ->toBe(['images', 'media', 'videos']);
});

test('an unknown sort column is refused and the current sort is kept', function () {
    seedSized(['media/a.jpg' => 100]);

    $component = Livewire::test(MediaLibrary::class)
        ->call('sortByColumn', 'size')
        ->call('sortByColumn', 'extension');

    expect($component->get('sortBy'))->toBe('size');
});

test('a sort value from the URL that the page does not support falls back', function () {
    seedSized(['media/a.jpg' => 100]);

    $component = Livewire::withQueryParams(['sortBy' => 'nonsense'])->test(MediaLibrary::class);

    expect($component->instance()->sortKey())->toBe('modified')
        ->and($component->instance()->getFilesProperty()->items())->toHaveCount(1);
});

// ── Page size ───────────────────────────────────────────────────────────────

test('the page size can be changed and resets to the first page', function () {
    $paths = [];
    for ($i = 1; $i <= 30; $i++) {
        $paths[sprintf('media/file-%02d.jpg', $i)] = 10;
    }
    seedSized($paths);

    $component = Livewire::test(MediaLibrary::class)
        ->call('gotoPage', 2)
        ->set('perPage', 25);

    expect($component->instance()->getFilesProperty()->items())->toHaveCount(25)
        ->and($component->instance()->getPage())->toBe(1);
});

test('a page size from the URL is clamped to the offered options', function () {
    seedSized(['media/a.jpg' => 100]);

    // An arbitrary value would let a link ask for the whole table at once.
    $component = Livewire::withQueryParams(['per' => 99999])->test(MediaLibrary::class);

    expect($component->instance()->getFilesProperty()->perPage())
        ->toBe((int) config('hubtube.media_library.per_page', 50));
});

test('the largest page size never exceeds the bulk action cap', function () {
    // "Select page → Delete" must never act on fewer files than are shown.
    expect(max(Livewire::test(MediaLibrary::class)->instance()->perPageOptions()))->toBeLessThanOrEqual(200);
});

// ── URL and session state ───────────────────────────────────────────────────

test('the view mode is shareable in the URL', function () {
    seedSized(['media/a.jpg' => 100, 'videos/clip/b.mp4' => 200]);

    $component = Livewire::withQueryParams(['view' => 'flat', 'in' => 'library'])->test(MediaLibrary::class);

    expect($component->get('viewMode'))->toBe('flat')
        ->and(flatNames($component))->toHaveCount(2);
});

test('a link wins over the remembered layout', function () {
    // #[Session] supplies the default and #[Url] overrides it — the attribute
    // order on the property is what makes that true, so it is worth pinning.
    seedSized(['media/a.jpg' => 100]);

    session()->put(['viewMode' => 'list']);

    expect(Livewire::withQueryParams(['view' => 'flat'])->test(MediaLibrary::class)->get('viewMode'))
        ->toBe('flat');
});

// ── Indexes ─────────────────────────────────────────────────────────────────

test('the flat view has indexes behind every sort it offers', function () {
    // Without these a library-wide ORDER BY filesorts the whole table, which
    // is exactly what the media index was built to avoid. Asserted on the
    // schema so a later migration cannot quietly drop one.
    $indexes = collect(Schema::getIndexes('media_files'))->pluck('columns');

    expect($indexes)->toContain(['size'])
        ->toContain(['modified_at'])
        ->toContain(['type', 'size'])
        ->toContain(['root', 'depth', 'type', 'size']);
});

// ── Storage report ──────────────────────────────────────────────────────────

test('the report totals each root from the maintained rollups', function () {
    seedSized([
        'media/a.jpg' => 1000,
        'media/nested/b.jpg' => 2000,
        'videos/clip/c.mp4' => 5000,
    ]);

    $report = app(MediaStorageReport::class)->all();
    $roots = collect($report['roots'])->keyBy('path');

    expect($roots['videos']['bytes'])->toBe(5000)
        ->and($roots['media']['bytes'])->toBe(3000)
        ->and($roots['media']['files'])->toBe(2)
        ->and($report['total_bytes'])->toBe(8000)
        ->and($report['total_files'])->toBe(3)
        // Biggest root first, because that is the one you act on.
        ->and($report['roots'][0]['path'])->toBe('videos');
});

test('reclaimable HLS counts the duplicate segment tree and nothing else', function () {
    seedSized([
        'videos/clip/original.mp4' => 9000,
        'videos/clip/processed/720p.mp4' => 3000,
        'videos/clip/processed/hls/720p/segment_000.ts' => 2500,
        'videos/clip/processed/hls/720p/playlist.m3u8' => 100,
        // Decoys at the wrong depth and in the wrong root.
        'videos/clip/hls/stray.ts' => 7777,
        'media/hls/unrelated.ts' => 8888,
    ]);

    expect(app(MediaStorageReport::class)->reclaimableHls())->toBe(2600);
});

test('the videos breakdown separates originals from renditions and HLS', function () {
    seedSized([
        'videos/clip/original.mp4' => 9000,
        'videos/clip/clip_thumb_0.webp' => 500,
        'videos/clip/processed/720p.mp4' => 3000,
        'videos/clip/processed/hls/720p/segment_000.ts' => 2000,
    ]);

    $videos = app(MediaStorageReport::class)->videoBreakdown();

    expect($videos['originals'])->toBe(9000)
        // processed total (5000) minus the HLS copy (2000).
        ->and($videos['renditions'])->toBe(3000)
        ->and($videos['hls'])->toBe(2000)
        ->and($videos['artwork'])->toBe(500)
        ->and($videos['total'])->toBe(14500);
});

test('the report is cached and a rescan invalidates it', function () {
    seedSized(['media/a.jpg' => 1000]);

    expect(app(MediaStorageReport::class)->all()['total_bytes'])->toBe(1000);

    Storage::disk('public')->put('media/b.jpg', str_repeat('x', 4000));
    app(MediaIndexService::class)->indexAll();

    // Still the cached figure.
    expect(app(MediaStorageReport::class)->all()['total_bytes'])->toBe(1000);

    Livewire::test(MediaLibrary::class)->call('rescanCurrentDirectory');

    expect(app(MediaStorageReport::class)->all()['total_bytes'])->toBe(5000);
});

test('the biggest files list is ordered and can be narrowed to videos', function () {
    seedSized([
        'media/small.jpg' => 100,
        'videos/clip/huge.mp4' => 9000,
        'media/big.jpg' => 5000,
    ]);

    $report = app(MediaStorageReport::class);

    expect(collect($report->biggestFiles(2))->pluck('name')->all())->toBe(['huge.mp4', 'big.jpg'])
        ->and(collect($report->biggestFiles(5, 'video'))->pluck('name')->all())->toBe(['huge.mp4']);
});

test('showing the biggest files drops you into the flat view sorted by size', function () {
    seedSized(['media/a.jpg' => 100, 'videos/clip/b.mp4' => 9000]);

    $component = Livewire::test(MediaLibrary::class)->call('showBiggest', 'video');

    expect($component->get('viewMode'))->toBe('flat')
        ->and($component->get('searchScope'))->toBe('library')
        ->and($component->get('typeFilter'))->toBe('video')
        ->and($component->get('sortBy'))->toBe('size')
        ->and($component->get('sortDirection'))->toBe('desc')
        ->and(flatNames($component))->toBe(['b.mp4']);
});

// ── Byte formatting ─────────────────────────────────────────────────────────

test('byte sizes format consistently, terabytes included', function () {
    // There were seven divergent copies of this; MediaLibrary's had no TB
    // branch, so a multi-terabyte root rendered as "3,481.22 GB".
    expect(Bytes::format(0))->toBe('0 B')
        ->and(Bytes::format(512))->toBe('512 B')
        ->and(Bytes::format(1024))->toBe('1.00 KB')
        ->and(Bytes::format(1048576))->toBe('1.00 MB')
        ->and(Bytes::format(1073741824))->toBe('1.00 GB')
        ->and(Bytes::format(1099511627776))->toBe('1.00 TB')
        ->and(Bytes::format(3_738_000_000_000))->toContain('TB')
        ->and(Bytes::format(1536, 1))->toBe('1.5 KB')
        ->and(Bytes::format(null))->toBe('0 B');
});

test('a saving reads as bytes and a percentage', function () {
    expect(Bytes::saving(1000, 400))->toBe('600 B (60%)')
        ->and(Bytes::saving(0, 0))->toBe('0 B');
});

test('the library reports terabytes now that it shares the formatter', function () {
    Storage::disk('public')->put('media/a.jpg', 'x');
    app(MediaIndexService::class)->indexAll();
    MediaFile::query()->update(['size' => 3_738_000_000_000]);
    app(MediaFolderRollup::class)->recomputeAll();
    MediaStorageReport::forget();

    expect(app(MediaStorageReport::class)->all()['roots'][0]['formatted'])->toContain('TB');
});

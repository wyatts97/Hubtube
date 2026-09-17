<?php

use App\Filament\Pages\MediaLibrary;
use App\Models\Video;
use App\Services\Media\MediaIndexService;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

/*
|--------------------------------------------------------------------------
| Admin Media Library
|--------------------------------------------------------------------------
|
| The page had no test coverage beyond three canAccess() assertions, which is
| how a completely broken paginator survived. These cover the read path, the
| thumbnail cache and the path guards.
|
*/

beforeEach(function () {
    asAdmin();
});

/**
 * Put $count files into a directory on the faked public disk, and index them.
 *
 * The listing reads media_files, not the filesystem, so a fixture that only
 * writes to disk is invisible by design — MediaLibraryIndexTest covers that
 * staleness contract on its own.
 */
function seedMedia(string $directory, int $count, string $extension = 'jpg'): void
{
    for ($i = 1; $i <= $count; $i++) {
        Storage::disk('public')->put(
            sprintf('%s/file-%03d.%s', $directory, $i, $extension),
            'x'
        );
    }

    indexMedia();
}

/** Bring the index in line with whatever is currently on the faked disk. */
function indexMedia(): void
{
    app(MediaIndexService::class)->indexAll();
}

// ── Listing ─────────────────────────────────────────────────────────────────

test('the page renders', function () {
    seedMedia('media', 3);

    Livewire::test(MediaLibrary::class)
        ->assertOk()
        ->assertSee('file-001.jpg');
});

test('pagination actually paginates', function () {
    // The regression this page shipped with: $files->links() rendered plain
    // anchors, the browser navigated, the component remounted at page 1, and
    // clicking "2" showed page 1 again.
    $perPage = (int) config('hubtube.media_library.per_page', 50);
    seedMedia('media', $perPage + 10);

    $component = Livewire::test(MediaLibrary::class)
        ->set('sortBy', 'name')
        ->set('sortDirection', 'asc');

    expect($component->instance()->getFilesProperty()->items())->toHaveCount($perPage);
    $firstPageNames = collect($component->instance()->getFilesProperty()->items())->pluck('name');

    $component->call('gotoPage', 2);

    $secondPage = collect($component->instance()->getFilesProperty()->items());

    expect($secondPage)->toHaveCount(10)
        ->and($secondPage->pluck('name')->intersect($firstPageNames))->toBeEmpty();
});

test('only the current page is hydrated, so cost does not scale with folder size', function () {
    // Metadata used to be built for every file in the directory and sliced
    // afterwards. Referenced files are the observable proxy: getReferences()
    // runs two queries per hydrated row.
    $perPage = (int) config('hubtube.media_library.per_page', 50);
    seedMedia('media', $perPage + 25);

    $files = Livewire::test(MediaLibrary::class)->instance()->getFilesProperty();

    expect($files->items())->toHaveCount($perPage)
        ->and($files->total())->toBe($perPage + 25);

    // Every hydrated row carries the full shape the grid needs.
    expect($files->items()[0])->toHaveKeys([
        'path', 'name', 'url', 'thumbnail', 'type', 'extension',
        'size', 'size_formatted', 'modified', 'modified_formatted', 'references',
    ]);
});

test('search narrows the listing and resets to the first page', function () {
    seedMedia('media', 5);
    Storage::disk('public')->put('media/needle.png', 'x');
    indexMedia();

    $component = Livewire::test(MediaLibrary::class)
        ->call('gotoPage', 2)
        ->set('search', 'needle');

    $items = collect($component->instance()->getFilesProperty()->items());

    expect($items)->toHaveCount(1)
        ->and($items->first()['name'])->toBe('needle.png')
        ->and($component->instance()->getPage())->toBe(1);
});

test('files sort by name, size and type in both directions', function () {
    Storage::disk('public')->put('media/b.jpg', str_repeat('x', 10));
    Storage::disk('public')->put('media/a.jpg', str_repeat('x', 100));
    Storage::disk('public')->put('media/c.mp4', str_repeat('x', 50));
    indexMedia();

    $names = function ($component) {
        return collect($component->instance()->getFilesProperty()->items())->pluck('name')->all();
    };

    $component = Livewire::test(MediaLibrary::class)
        ->set('sortBy', 'name')
        ->set('sortDirection', 'asc');
    expect($names($component))->toBe(['a.jpg', 'b.jpg', 'c.mp4']);

    $component->set('sortDirection', 'desc');
    expect($names($component))->toBe(['c.mp4', 'b.jpg', 'a.jpg']);

    $component->set('sortBy', 'size')->set('sortDirection', 'desc');
    expect($names($component))->toBe(['a.jpg', 'c.mp4', 'b.jpg']);

    // Type sorts image before video, then by name within a type.
    $component->set('sortBy', 'type')->set('sortDirection', 'asc');
    expect($names($component))->toBe(['a.jpg', 'b.jpg', 'c.mp4']);
});

test('an unknown sort value falls back to modified instead of erroring', function () {
    seedMedia('media', 3);

    $component = Livewire::test(MediaLibrary::class)->set('sortBy', 'nonsense');

    expect($component->instance()->getFilesProperty()->items())->toHaveCount(3);
});

test('the details panel survives paging', function () {
    // It used to resolve the selected file by scanning the current page's
    // items, so it went blank the moment you turned the page.
    $perPage = (int) config('hubtube.media_library.per_page', 50);
    seedMedia('media', $perPage + 5);

    $component = Livewire::test(MediaLibrary::class)
        ->call('selectFile', 'media/file-001.jpg')
        ->call('gotoPage', 2);

    $details = $component->instance()->getSelectedFileDataProperty();

    expect($details)->not->toBeNull()
        ->and($details['name'])->toBe('file-001.jpg');
});

test('the details panel reports nothing for a file that is gone', function () {
    seedMedia('media', 1);

    $details = Livewire::test(MediaLibrary::class)
        ->call('selectFile', 'media/never-existed.jpg')
        ->instance()
        ->getSelectedFileDataProperty();

    expect($details)->toBeNull();
});

// ── Folder tree ─────────────────────────────────────────────────────────────

test('the folder tree is built from the index, with recursive counts', function () {
    Storage::disk('public')->put('media/a.jpg', str_repeat('x', 100));
    Storage::disk('public')->put('media/2026/b.jpg', str_repeat('x', 200));
    indexMedia();

    $tree = Livewire::test(MediaLibrary::class)->instance()->getFolderTree();
    $mediaRoot = collect($tree)->firstWhere('path', 'media');

    expect($mediaRoot['name'])->toBe('Media')
        // Recursive, as the old allFiles() count was.
        ->and($mediaRoot['count'])->toBe(2)
        ->and($mediaRoot['size'])->toBe('300 B')
        ->and($mediaRoot['children'])->toHaveCount(1)
        ->and($mediaRoot['children'][0]['path'])->toBe('media/2026')
        ->and($mediaRoot['children'][0]['count'])->toBe(1);
});

test('a folder that has never been indexed is reported as such', function () {
    Storage::disk('public')->put('media/orphan.jpg', 'x');

    $component = Livewire::test(MediaLibrary::class);

    expect($component->instance()->getDirectoryIndexedProperty())->toBeFalse()
        ->and($component->instance()->getDirectoryStaleProperty())->toBeTrue();

    $component->call('rescanCurrentDirectory');

    expect($component->instance()->getDirectoryIndexedProperty())->toBeTrue()
        ->and($component->instance()->getFilesProperty()->items())->toHaveCount(1);
});

test('rescanning a folder picks up what was written behind the app s back', function () {
    seedMedia('media', 2);

    Storage::disk('public')->put('media/arrived-later.jpg', 'x');

    $component = Livewire::test(MediaLibrary::class);
    expect($component->instance()->getFilesProperty()->total())->toBe(2);

    $component->call('rescanCurrentDirectory');
    expect($component->instance()->getFilesProperty()->total())->toBe(3);
});

test('page actions keep the index in step without a rescan', function () {
    seedMedia('media', 1);

    Livewire::test(MediaLibrary::class)
        ->call('confirmDelete', 'media/file-001.jpg')
        ->call('deleteFile');

    // No rescan, no stale banner — the page indexed its own work.
    $component = Livewire::test(MediaLibrary::class);

    expect($component->instance()->getFilesProperty()->total())->toBe(0);
});

// Thumbnails moved off the render path in favour of a queued job and a state
// machine on the row; MediaLibraryThumbnailTest covers all of it.

// ── Reference protection ────────────────────────────────────────────────────

test('a file a video record points at cannot be deleted', function () {
    Storage::disk('public')->put('media/in-use.mp4', 'x');
    Video::factory()->create(['video_path' => 'media/in-use.mp4']);
    indexMedia();

    Livewire::test(MediaLibrary::class)
        ->call('confirmDelete', 'media/in-use.mp4')
        ->call('deleteFile');

    expect(Storage::disk('public')->exists('media/in-use.mp4'))->toBeTrue();
});

// ── Folders ─────────────────────────────────────────────────────────────────

test('the new folder modal has its own visibility flag', function () {
    // It used to render under @if ($newFolderName), so clearing the pre-filled
    // name closed the modal mid-edit.
    $component = Livewire::test(MediaLibrary::class)
        ->call('openNewFolderModal')
        ->assertSet('showNewFolderModal', true)
        ->assertSet('newFolderName', '');

    $component->set('newFolderName', 'promos')
        ->call('createFolder')
        ->assertSet('showNewFolderModal', false);

    expect(Storage::disk('public')->exists('media/promos'))->toBeTrue();
});

test('the thumbnail cache directory is not browsable', function () {
    expect(config('hubtube.media_library.allowed_paths'))->not->toContain('thumbnails');

    Livewire::test(MediaLibrary::class)
        ->call('openDirectory', 'thumbnails')
        ->assertSet('currentDirectory', 'media');
});

test('navigating to a folder outside the allowed roots is refused', function () {
    foreach (['..', '../storage', 'media/../../.env', '', 'medialibrary'] as $path) {
        Livewire::test(MediaLibrary::class)
            ->call('openDirectory', $path)
            ->assertSet('currentDirectory', 'media');
    }
});

test('a file outside the allowed roots cannot be deleted', function () {
    Storage::disk('public')->put('secret.txt', 'x');

    Livewire::test(MediaLibrary::class)
        ->call('confirmDelete', '../secret.txt')
        ->call('deleteFile');

    expect(Storage::disk('public')->exists('secret.txt'))->toBeTrue();
});

test('a folder cannot be created outside the allowed roots', function () {
    Livewire::test(MediaLibrary::class)
        ->call('openNewFolderModal')
        ->set('newFolderName', '../escaped')
        ->call('createFolder');

    expect(Storage::disk('public')->exists('escaped'))->toBeFalse();
});

<?php

use App\Filament\Pages\MediaLibrary;
use App\Jobs\GenerateMediaThumbnailJob;
use App\Models\Video;
use App\Services\Media\MediaIndexService;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

/*
|--------------------------------------------------------------------------
| Admin Media Library
|--------------------------------------------------------------------------
|
| The read path, the details pane, the tree and the path guards.
|
*/

beforeEach(function () {
    asAdmin();
    // Rendering queues thumbnails, and the thumbnail service shells out to a
    // real ffmpeg; the thumbnail tests cover that path on their own.
    Queue::fake([GenerateMediaThumbnailJob::class]);
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
        Storage::disk('public')->put(sprintf('%s/file-%03d.%s', $directory, $i, $extension), 'x');
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
        ->assertSee('file-001.jpg')
        ->assertSee('All files');
});

test('pagination actually paginates', function () {
    // The regression this page once shipped with: plain anchors made the
    // browser navigate, the component remounted at page 1, and clicking "2"
    // showed page 1 again.
    seedMedia('media', MediaLibrary::PER_PAGE + 10);

    $component = Livewire::test(MediaLibrary::class);

    expect($component->instance()->getFilesProperty()->items())->toHaveCount(MediaLibrary::PER_PAGE);
    $firstPageNames = collect($component->instance()->getFilesProperty()->items())->pluck('name');

    $component->call('gotoPage', 2);

    $secondPage = collect($component->instance()->getFilesProperty()->items());

    expect($secondPage)->toHaveCount(10)
        ->and($secondPage->pluck('name')->intersect($firstPageNames))->toBeEmpty();
});

test('only the current page is hydrated, so cost does not scale with folder size', function () {
    seedMedia('media', MediaLibrary::PER_PAGE + 25);

    $files = Livewire::test(MediaLibrary::class)->instance()->getFilesProperty();

    expect($files->items())->toHaveCount(MediaLibrary::PER_PAGE)
        ->and($files->total())->toBe(MediaLibrary::PER_PAGE + 25)
        ->and($files->items()[0])->toHaveKeys([
            'path', 'name', 'url', 'thumbnail', 'type', 'extension', 'size', 'size_formatted', 'modified_formatted',
        ]);
});

test('search narrows the listing and resets to the first page', function () {
    seedMedia('media', MediaLibrary::PER_PAGE + 5);
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

    $names = fn ($component) => collect($component->instance()->getFilesProperty()->items())->pluck('name')->all();

    // Name ascending is the default, as in Explorer.
    $component = Livewire::test(MediaLibrary::class);
    expect($names($component))->toBe(['a.jpg', 'b.jpg', 'c.mp4']);

    $component->set('sortDirection', 'desc');
    expect($names($component))->toBe(['c.mp4', 'b.jpg', 'a.jpg']);

    $component->set('sortBy', 'size')->set('sortDirection', 'desc');
    expect($names($component))->toBe(['a.jpg', 'c.mp4', 'b.jpg']);

    // Type sorts image before video, then by name within a type.
    $component->set('sortBy', 'type')->set('sortDirection', 'asc');
    expect($names($component))->toBe(['a.jpg', 'b.jpg', 'c.mp4']);
});

test('an unknown sort value falls back to name instead of erroring', function () {
    seedMedia('media', 3);

    $component = Livewire::test(MediaLibrary::class)->set('sortBy', 'nonsense');

    expect($component->instance()->sortKey())->toBe('name')
        ->and($component->instance()->getFilesProperty()->items())->toHaveCount(3);
});

// ── Details pane ────────────────────────────────────────────────────────────

test('the details pane survives paging', function () {
    seedMedia('media', MediaLibrary::PER_PAGE + 5);

    $component = Livewire::test(MediaLibrary::class)
        ->call('selectFile', 'media/file-001.jpg')
        ->call('gotoPage', 2);

    $details = $component->instance()->getSelectedFileDataProperty();

    expect($details)->not->toBeNull()
        ->and($details['name'])->toBe('file-001.jpg')
        ->and($details['video'])->toBeNull()
        ->and($details['compressed_copies'])->toBe([]);
});

test('the details pane reports nothing for a file that is gone', function () {
    seedMedia('media', 1);

    $details = Livewire::test(MediaLibrary::class)
        ->call('selectFile', 'media/never-existed.jpg')
        ->instance()
        ->getSelectedFileDataProperty();

    expect($details)->toBeNull();
});

// ── Folder tree ─────────────────────────────────────────────────────────────

test('the folder tree is built from the index, with recursive sizes', function () {
    Storage::disk('public')->put('media/a.jpg', str_repeat('x', 100));
    Storage::disk('public')->put('media/2026/b.jpg', str_repeat('x', 200));
    indexMedia();

    $tree = Livewire::test(MediaLibrary::class)->instance()->getFolderTree();
    $mediaRoot = collect($tree)->firstWhere('path', 'media');

    expect($mediaRoot['name'])->toBe('Media')
        ->and($mediaRoot['count'])->toBe(2)
        ->and($mediaRoot['size'])->toBe('300 B')
        ->and($mediaRoot['children'])->toHaveCount(1)
        ->and($mediaRoot['children'][0]['path'])->toBe('media/2026')
        ->and($mediaRoot['children'][0]['count'])->toBe(1);
});

test('a folder that has never been indexed is reported as such', function () {
    Storage::disk('public')->put('media/orphan.jpg', 'x');

    $component = Livewire::test(MediaLibrary::class);

    expect($component->instance()->getDirectoryIndexedProperty())->toBeFalse();
    $component->assertSee('been indexed yet');

    $component->call('rescanCurrentDirectory');

    expect($component->instance()->getDirectoryIndexedProperty())->toBeTrue()
        ->and($component->instance()->getFilesProperty()->items())->toHaveCount(1);
});

test('refreshing a folder picks up what was written behind the app s back', function () {
    seedMedia('media', 2);

    Storage::disk('public')->put('media/arrived-later.jpg', 'x');

    $component = Livewire::test(MediaLibrary::class);
    expect($component->instance()->getFilesProperty()->total())->toBe(2);

    $component->call('rescanCurrentDirectory');
    expect($component->instance()->getFilesProperty()->total())->toBe(3);
});

test('page actions keep the index in step without a rescan', function () {
    seedMedia('media', 1);

    Livewire::test(MediaLibrary::class)->callAction('delete', arguments: ['paths' => ['media/file-001.jpg']]);

    expect(Livewire::test(MediaLibrary::class)->instance()->getFilesProperty()->total())->toBe(0);
});

// ── Reference protection ────────────────────────────────────────────────────

test('a file a video record points at cannot be deleted', function () {
    Storage::disk('public')->put('media/in-use.mp4', 'x');
    Video::factory()->create(['video_path' => 'media/in-use.mp4']);
    indexMedia();

    Livewire::test(MediaLibrary::class)->callAction('delete', arguments: ['paths' => ['media/in-use.mp4']]);

    expect(Storage::disk('public')->exists('media/in-use.mp4'))->toBeTrue();
});

// ── Folders and paths ───────────────────────────────────────────────────────

test('a new folder is created inside the current one', function () {
    Livewire::test(MediaLibrary::class)
        ->callAction('newFolder', data: ['name' => 'promos'])
        ->assertHasNoActionErrors();

    expect(Storage::disk('public')->directoryExists('media/promos'))->toBeTrue();
});

test('a new folder cannot be created at All files', function () {
    Livewire::test(MediaLibrary::class)
        ->call('openDirectory', '')
        ->callAction('newFolder', data: ['name' => 'loose']);

    expect(Storage::disk('public')->exists('loose'))->toBeFalse();
});

test('the thumbnail cache directory is not browsable', function () {
    expect(config('hubtube.media_library.allowed_paths'))->not->toContain('thumbnails');

    Livewire::test(MediaLibrary::class)
        ->call('openDirectory', 'thumbnails')
        ->assertSet('currentDirectory', 'media');
});

test('navigating to a folder outside the allowed roots is refused', function () {
    foreach (['..', '../storage', 'media/../../.env', 'medialibrary'] as $path) {
        Livewire::test(MediaLibrary::class)
            ->call('openDirectory', $path)
            ->assertSet('currentDirectory', 'media');
    }
});

test('a file outside the allowed roots cannot be deleted', function () {
    Storage::disk('public')->put('secret.txt', 'x');

    Livewire::test(MediaLibrary::class)->callAction('delete', arguments: ['paths' => ['../secret.txt', 'secret.txt']]);

    expect(Storage::disk('public')->exists('secret.txt'))->toBeTrue();
});

test('a folder cannot be created outside the allowed roots', function () {
    Livewire::test(MediaLibrary::class)->callAction('newFolder', data: ['name' => '../escaped']);

    expect(Storage::disk('public')->exists('escaped'))->toBeFalse()
        ->and(Storage::disk('public')->exists('media/escaped'))->toBeTrue();
});

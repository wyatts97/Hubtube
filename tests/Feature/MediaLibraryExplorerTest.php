<?php

use App\Filament\Pages\MediaLibrary;
use App\Jobs\GenerateMediaThumbnailJob;
use App\Services\Media\MediaIndexService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

/*
|--------------------------------------------------------------------------
| Media Library — explorer browsing and "find the largest files"
|--------------------------------------------------------------------------
|
| A plain folder view lists only that folder. Any filter searches the folder
| and everything below it, like Explorer's search box, and All files spans
| every root. Largest-first is those controls plus the Size column.
|
*/

beforeEach(function () {
    asAdmin();
    // Rendering queues thumbnails, and the thumbnail service shells out to a
    // real ffmpeg; the thumbnail tests cover that path on their own.
    Queue::fake([GenerateMediaThumbnailJob::class]);
});

function explorerSeed(array $files): void
{
    foreach ($files as $path => $bytes) {
        Storage::disk('public')->put($path, str_repeat('x', $bytes));
    }

    app(MediaIndexService::class)->indexAll();
}

/** Pretend a file is bigger than the fixture wrote, so size filters can be exercised. */
function explorerResize(string $path, int $bytes): void
{
    DB::table('media_files')->where('path_hash', md5($path))->update(['size' => $bytes]);
}

function explorerNames($component): array
{
    return collect($component->instance()->getFilesProperty()->items())->pluck('name')->all();
}

test('a folder lists only its own files, with its subfolders beside them', function () {
    explorerSeed(['media/top.jpg' => 1, 'media/nested/deep.jpg' => 1]);

    $component = Livewire::test(MediaLibrary::class);

    expect(explorerNames($component))->toBe(['top.jpg'])
        ->and(collect($component->instance()->getSubfoldersProperty())->pluck('path')->all())->toBe(['media/nested']);
});

test('include subfolders lists everything below, and hides the folder rows', function () {
    explorerSeed(['media/top.jpg' => 1, 'media/nested/deep.jpg' => 1, 'videos/clip/other.jpg' => 1]);

    $component = Livewire::test(MediaLibrary::class)->set('includeSubfolders', true);

    expect(explorerNames($component))->toBe(['deep.jpg', 'top.jpg'])
        ->and($component->instance()->getSubfoldersProperty())->toBe([]);
});

test('a search reaches the whole subtree of the current folder', function () {
    explorerSeed(['media/top.jpg' => 1, 'media/nested/deep.jpg' => 1, 'videos/clip/other.jpg' => 1]);

    $component = Livewire::test(MediaLibrary::class)->set('search', 'jpg');

    expect(explorerNames($component))->toBe(['deep.jpg', 'top.jpg']);
});

test('changing folder keeps the search term', function () {
    explorerSeed(['media/a-clip.jpg' => 1, 'media/nested/b-clip.jpg' => 1]);

    $component = Livewire::test(MediaLibrary::class)
        ->set('search', 'clip')
        ->call('openDirectory', 'media/nested');

    expect($component->get('search'))->toBe('clip')
        ->and(explorerNames($component))->toBe(['b-clip.jpg']);
});

test('All files spans every root and shows the roots as folders', function () {
    explorerSeed(['media/a.jpg' => 1, 'videos/clip/b.mp4' => 1]);

    $component = Livewire::test(MediaLibrary::class)->call('openDirectory', '');

    expect($component->get('currentDirectory'))->toBe('')
        ->and(explorerNames($component))->toBe(['a.jpg', 'b.mp4'])
        ->and(collect($component->instance()->getSubfoldersProperty())->pluck('path')->sort()->values()->all())
        ->toBe(['media', 'videos'])
        ->and($component->instance()->parentDirectory)->toBeNull();
});

test('the biggest files across the library come first', function () {
    explorerSeed(['media/small.jpg' => 1, 'media/mid.mp4' => 1, 'videos/clip/huge.mp4' => 1, 'videos/clip/big.mp4' => 1]);
    explorerResize('media/mid.mp4', 200 * 1024 * 1024);
    explorerResize('videos/clip/huge.mp4', 5 * 1024 * 1024 * 1024);
    explorerResize('videos/clip/big.mp4', 2 * 1024 * 1024 * 1024);

    $component = Livewire::test(MediaLibrary::class)
        ->call('openDirectory', '')
        ->set('minSize', '1gb')
        ->call('sortByColumn', 'size');

    expect($component->get('sortDirection'))->toBe('desc')
        ->and(explorerNames($component))->toBe(['huge.mp4', 'big.mp4']);

    $component->set('minSize', '100mb');
    expect(explorerNames($component))->toBe(['huge.mp4', 'big.mp4', 'mid.mp4']);
});

test('the type and size filters combine', function () {
    explorerSeed(['media/photo.jpg' => 1, 'media/clip.mp4' => 1]);
    explorerResize('media/photo.jpg', 300 * 1024 * 1024);
    explorerResize('media/clip.mp4', 300 * 1024 * 1024);

    $component = Livewire::test(MediaLibrary::class)->set('minSize', '100mb')->set('typeFilter', 'video');

    expect(explorerNames($component))->toBe(['clip.mp4']);
});

test('an unknown size filter is ignored', function () {
    explorerSeed(['media/a.jpg' => 1]);

    $component = Livewire::test(MediaLibrary::class)->set('minSize', 'huge');

    expect(explorerNames($component))->toBe(['a.jpg'])
        ->and($component->instance()->hasFilters())->toBeFalse();
});

test('clicking the active column only flips the direction', function () {
    $component = Livewire::test(MediaLibrary::class)->call('sortByColumn', 'size');

    expect($component->get('sortDirection'))->toBe('desc');

    $component->call('sortByColumn', 'size');
    expect($component->get('sortBy'))->toBe('size')->and($component->get('sortDirection'))->toBe('asc');

    // Names start A–Z.
    $component->call('sortByColumn', 'name');
    expect($component->get('sortDirection'))->toBe('asc');

    $component->call('sortByColumn', 'nonsense');
    expect($component->get('sortBy'))->toBe('name');
});

test('clearing filters returns to the plain folder view', function () {
    explorerSeed(['media/a.jpg' => 1, 'media/nested/b.jpg' => 1]);

    $component = Livewire::test(MediaLibrary::class)
        ->set('search', 'b')
        ->set('typeFilter', 'image')
        ->set('includeSubfolders', true)
        ->set('minSize', '1gb');

    expect($component->instance()->hasFilters())->toBeTrue();

    $component->call('clearFilters');

    expect($component->instance()->hasFilters())->toBeFalse()
        ->and(explorerNames($component))->toBe(['a.jpg']);
});

test('the Up button goes to the parent, then to All files', function () {
    explorerSeed(['media/nested/a.jpg' => 1]);

    $component = Livewire::test(MediaLibrary::class)->call('openDirectory', 'media/nested');

    expect($component->instance()->parentDirectory)->toBe('media');

    $component->call('openDirectory', 'media');
    expect($component->instance()->parentDirectory)->toBe('');
});

test('the view mode only accepts the two layouts', function () {
    $component = Livewire::test(MediaLibrary::class)->call('setViewMode', 'icons');
    expect($component->get('viewMode'))->toBe('icons');

    $component->call('setViewMode', 'flat');
    expect($component->get('viewMode'))->toBe('icons');
});

test('browsing state round-trips through the query string', function () {
    explorerSeed(['media/nested/a.jpg' => 1]);
    explorerResize('media/nested/a.jpg', 2 * 1024 * 1024 * 1024);

    $component = Livewire::withQueryParams([
        'path' => 'media/nested',
        'q' => 'a',
        'type' => 'image',
        'min' => '1gb',
        'deep' => '1',
        'sort' => 'size',
        'dir' => 'desc',
        'view' => 'icons',
    ])->test(MediaLibrary::class);

    expect($component->get('currentDirectory'))->toBe('media/nested')
        ->and($component->get('search'))->toBe('a')
        ->and($component->get('typeFilter'))->toBe('image')
        ->and($component->get('minSize'))->toBe('1gb')
        ->and($component->get('includeSubfolders'))->toBeTrue()
        ->and($component->get('sortBy'))->toBe('size')
        ->and($component->get('sortDirection'))->toBe('desc')
        ->and($component->get('viewMode'))->toBe('icons')
        ->and(explorerNames($component))->toBe(['a.jpg']);
});

test('both layouts render', function () {
    explorerSeed(['media/a.jpg' => 1, 'media/clip.mp4' => 1, 'media/nested/b.jpg' => 1]);

    Livewire::test(MediaLibrary::class)
        ->assertSee('a.jpg')->assertSee('nested')->assertSee('Date modified')
        ->call('setViewMode', 'icons')
        ->assertSee('a.jpg')->assertSee('nested')->assertDontSee('Date modified');
});

<?php

use App\Filament\Pages\MediaLibrary;
use App\Models\MediaFile;
use App\Models\MediaFolder;
use App\Models\Video;
use App\Services\Media\MediaIndexService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

/*
|--------------------------------------------------------------------------
| Media Library — search scope, filters, folders, move, uploads
|--------------------------------------------------------------------------
|
| Search used to die at the folder boundary, folders never appeared in the
| main pane, deleteFolder() had no button anywhere, and there was no way to
| move a file at all. These cover what was added.
|
*/

beforeEach(function () {
    asAdmin();
});

function seedFiles(array $paths): void
{
    foreach ($paths as $path => $contents) {
        Storage::disk('public')->put(is_int($path) ? $contents : $path, is_int($path) ? 'x' : $contents);
    }

    app(MediaIndexService::class)->indexAll();
}

function listedNames($component): array
{
    return collect($component->instance()->getFilesProperty()->items())->pluck('name')->sort()->values()->all();
}

// ── Search scope ────────────────────────────────────────────────────────────

test('search reaches only the current folder by default', function () {
    seedFiles(['media/top.jpg', 'media/nested/deep.jpg', 'videos/clip/other.jpg']);

    $component = Livewire::test(MediaLibrary::class)->set('search', 'jpg');

    expect(listedNames($component))->toBe(['top.jpg']);
});

test('search can reach the whole subtree', function () {
    seedFiles(['media/top.jpg', 'media/nested/deep.jpg', 'videos/clip/other.jpg']);

    $component = Livewire::test(MediaLibrary::class)
        ->set('search', 'jpg')
        ->set('searchScope', 'subtree');

    expect(listedNames($component))->toBe(['deep.jpg', 'top.jpg']);
});

test('search can reach the whole library', function () {
    seedFiles(['media/top.jpg', 'media/nested/deep.jpg', 'videos/clip/other.jpg']);

    $component = Livewire::test(MediaLibrary::class)
        ->set('search', 'jpg')
        ->set('searchScope', 'library');

    expect(listedNames($component))->toBe(['deep.jpg', 'other.jpg', 'top.jpg']);
});

test('changing folder keeps the search term', function () {
    // It used to be wiped silently, throwing the query away the moment you
    // clicked a folder to look for it there.
    seedFiles(['media/a-clip.jpg', 'media/nested/b-clip.jpg']);

    $component = Livewire::test(MediaLibrary::class)
        ->set('search', 'clip')
        ->call('openDirectory', 'media/nested');

    expect($component->get('search'))->toBe('clip')
        ->and(listedNames($component))->toBe(['b-clip.jpg']);
});

test('an unknown scope falls back to the current folder', function () {
    seedFiles(['media/top.jpg', 'media/nested/deep.jpg']);

    $component = Livewire::test(MediaLibrary::class)->set('searchScope', 'everywhere');

    expect(listedNames($component))->toBe(['top.jpg']);
});

// ── Type and usage filters ──────────────────────────────────────────────────

test('the type filter narrows by kind and reports counts', function () {
    seedFiles(['media/a.jpg', 'media/b.png', 'media/c.mp4', 'media/d.pdf']);

    $component = Livewire::test(MediaLibrary::class);

    expect($component->instance()->getTypeCountsProperty())
        ->toBe(['document' => 1, 'image' => 2, 'video' => 1]);

    $component->set('typeFilter', 'image');
    expect(listedNames($component))->toBe(['a.jpg', 'b.png']);
});

test('the in-use filter splits referenced from unreferenced files', function () {
    Storage::disk('public')->put('media/used.mp4', 'x');
    Storage::disk('public')->put('media/spare.mp4', 'x');
    Video::factory()->create(['video_path' => 'media/used.mp4']);
    app(MediaIndexService::class)->indexAll();

    $component = Livewire::test(MediaLibrary::class)->set('usageFilter', 'used');
    expect(listedNames($component))->toBe(['used.mp4']);

    $component->set('usageFilter', 'unused');
    expect(listedNames($component))->toBe(['spare.mp4']);
});

test('clearing filters restores the folder listing', function () {
    seedFiles(['media/a.jpg', 'media/nested/b.jpg']);

    $component = Livewire::test(MediaLibrary::class)
        ->set('search', 'b')
        ->set('searchScope', 'library')
        ->set('typeFilter', 'image');

    expect($component->instance()->getHasFiltersProperty())->toBeTrue();

    $component->call('clearFilters');

    expect($component->instance()->getHasFiltersProperty())->toBeFalse()
        ->and(listedNames($component))->toBe(['a.jpg']);
});

// ── Folders in the main pane ────────────────────────────────────────────────

test('subfolders appear in the main pane with recursive counts', function () {
    seedFiles(['media/a.jpg', 'media/promos/b.jpg', 'media/promos/deeper/c.jpg']);

    $subfolders = Livewire::test(MediaLibrary::class)->instance()->getSubfoldersProperty();

    expect($subfolders)->toHaveCount(1)
        ->and($subfolders[0]['path'])->toBe('media/promos')
        ->and($subfolders[0]['count'])->toBe(2);
});

test('subfolders are hidden while a search is running', function () {
    // A search is about files; folder tiles would be noise in the results.
    seedFiles(['media/a.jpg', 'media/promos/b.jpg']);

    $component = Livewire::test(MediaLibrary::class)->set('search', 'b');

    expect($component->instance()->getSubfoldersProperty())->toBe([]);
});

// ── Folder rename and delete ────────────────────────────────────────────────

test('a folder can be renamed and its files follow', function () {
    Storage::disk('public')->put('media/promos/clip.mp4', 'x');
    $video = Video::factory()->create(['video_path' => 'media/promos/clip.mp4']);
    app(MediaIndexService::class)->indexAll();

    Livewire::test(MediaLibrary::class)
        ->call('startFolderRename', 'media/promos')
        ->set('folderRenameNewName', 'campaigns')
        ->call('confirmFolderRename');

    expect(Storage::disk('public')->exists('media/campaigns/clip.mp4'))->toBeTrue()
        ->and(Storage::disk('public')->exists('media/promos/clip.mp4'))->toBeFalse()
        // The record follows the file.
        ->and($video->fresh()->video_path)->toBe('media/campaigns/clip.mp4')
        // And so does the index.
        ->and(MediaFile::where('path', 'media/campaigns/clip.mp4')->exists())->toBeTrue()
        ->and(MediaFolder::where('path', 'media/promos')->exists())->toBeFalse();
});

test('folders a record owns cannot be renamed', function () {
    // videos/{slug} and images/{ulid} are located by convention, so renaming
    // one orphans the record however carefully the paths are rewritten.
    Storage::disk('public')->put('videos/my-clip/720p.mp4', 'x');
    Storage::disk('public')->put('images/abc/original.jpg', 'x');
    app(MediaIndexService::class)->indexAll();

    $component = Livewire::test(MediaLibrary::class)
        ->call('startFolderRename', 'videos/my-clip')
        ->assertSet('folderRenameTarget', null);

    $component->call('startFolderRename', 'images/abc')
        ->assertSet('folderRenameTarget', null);
});

test('a top-level root cannot be renamed or deleted', function () {
    seedFiles(['media/a.jpg']);

    Livewire::test(MediaLibrary::class)
        ->call('startFolderRename', 'media')
        ->assertSet('folderRenameTarget', null)
        ->call('deleteFolder', 'media');

    expect(Storage::disk('public')->exists('media/a.jpg'))->toBeTrue();
});

test('an empty folder can be deleted, and the index follows', function () {
    Storage::disk('public')->put('media/scratch/junk.txt', 'x');
    app(MediaIndexService::class)->indexAll();

    Livewire::test(MediaLibrary::class)
        ->call('confirmFolderDelete', 'media/scratch')
        ->call('confirmFolderDeletion');

    expect(Storage::disk('public')->exists('media/scratch'))->toBeFalse()
        ->and(MediaFile::where('path', 'media/scratch/junk.txt')->exists())->toBeFalse()
        ->and(MediaFolder::where('path', 'media/scratch')->exists())->toBeFalse();
});

test('a folder holding a referenced file cannot be deleted', function () {
    Storage::disk('public')->put('media/live/clip.mp4', 'x');
    Video::factory()->create(['video_path' => 'media/live/clip.mp4']);
    app(MediaIndexService::class)->indexAll();

    Livewire::test(MediaLibrary::class)
        ->call('confirmFolderDelete', 'media/live')
        ->call('confirmFolderDeletion');

    expect(Storage::disk('public')->exists('media/live/clip.mp4'))->toBeTrue();
});

test('deleting the folder you are standing in moves you to its parent', function () {
    Storage::disk('public')->put('media/promos/junk.txt', 'x');
    app(MediaIndexService::class)->indexAll();

    $component = Livewire::test(MediaLibrary::class)
        ->call('openDirectory', 'media/promos')
        ->call('confirmFolderDelete', 'media/promos')
        ->call('confirmFolderDeletion');

    expect($component->get('currentDirectory'))->toBe('media');
});

// ── Move ────────────────────────────────────────────────────────────────────

test('files can be moved to another folder', function () {
    Storage::disk('public')->put('media/clip.mp4', 'x');
    Storage::disk('public')->makeDirectory('media/archive');
    $video = Video::factory()->create(['video_path' => 'media/clip.mp4']);
    app(MediaIndexService::class)->indexAll();

    Livewire::test(MediaLibrary::class)
        ->call('startMove', ['media/clip.mp4'])
        ->set('moveDestination', 'media/archive')
        ->call('confirmMove');

    expect(Storage::disk('public')->exists('media/archive/clip.mp4'))->toBeTrue()
        ->and(Storage::disk('public')->exists('media/clip.mp4'))->toBeFalse()
        ->and($video->fresh()->video_path)->toBe('media/archive/clip.mp4')
        ->and(MediaFile::where('path', 'media/archive/clip.mp4')->exists())->toBeTrue()
        ->and(MediaFile::where('path', 'media/clip.mp4')->exists())->toBeFalse();
});

test('a move that would overwrite something renames instead', function () {
    Storage::disk('public')->put('media/clip.mp4', 'first');
    Storage::disk('public')->put('media/archive/clip.mp4', 'already here');
    app(MediaIndexService::class)->indexAll();

    Livewire::test(MediaLibrary::class)
        ->call('startMove', ['media/clip.mp4'])
        ->set('moveDestination', 'media/archive')
        ->call('confirmMove');

    expect(Storage::disk('public')->get('media/archive/clip.mp4'))->toBe('already here')
        ->and(Storage::disk('public')->get('media/archive/clip-1.mp4'))->toBe('first');
});

test('files a record owns are not moved', function () {
    Storage::disk('public')->put('videos/my-clip/720p.mp4', 'x');
    Storage::disk('public')->makeDirectory('media/archive');
    app(MediaIndexService::class)->indexAll();

    Livewire::test(MediaLibrary::class)
        ->call('startMove', ['videos/my-clip/720p.mp4'])
        ->set('moveDestination', 'media/archive')
        ->call('confirmMove');

    expect(Storage::disk('public')->exists('videos/my-clip/720p.mp4'))->toBeTrue()
        ->and(Storage::disk('public')->exists('media/archive/720p.mp4'))->toBeFalse();
});

test('a move outside the library is refused', function () {
    Storage::disk('public')->put('media/clip.mp4', 'x');
    app(MediaIndexService::class)->indexAll();

    Livewire::test(MediaLibrary::class)
        ->call('startMove', ['media/clip.mp4'])
        ->set('moveDestination', '../escaped')
        ->call('confirmMove');

    expect(Storage::disk('public')->exists('media/clip.mp4'))->toBeTrue();
});

test('protected folders are not offered as move destinations', function () {
    Storage::disk('public')->put('media/a.jpg', 'x');
    Storage::disk('public')->put('videos/my-clip/b.mp4', 'x');
    Storage::disk('public')->put('images/abc/c.jpg', 'x');
    app(MediaIndexService::class)->indexAll();

    $destinations = Livewire::test(MediaLibrary::class)->instance()->getMoveDestinationsProperty();

    expect($destinations)->toContain('media')
        ->not->toContain('videos/my-clip')
        ->not->toContain('images/abc');
});

// ── Bulk actions come from the client, and are re-validated ─────────────────

test('a bulk delete only touches paths inside the library', function () {
    Storage::disk('public')->put('media/one.jpg', 'x');
    Storage::disk('public')->put('media/two.jpg', 'x');
    Storage::disk('public')->put('secret.txt', 'x');
    app(MediaIndexService::class)->indexAll();

    // The paths arrive from the browser, so a crafted one must be dropped.
    Livewire::test(MediaLibrary::class)
        ->call('deleteSelectedFiles', ['media/one.jpg', '../secret.txt', 'secret.txt']);

    expect(Storage::disk('public')->exists('media/one.jpg'))->toBeFalse()
        ->and(Storage::disk('public')->exists('media/two.jpg'))->toBeTrue()
        ->and(Storage::disk('public')->exists('secret.txt'))->toBeTrue();
});

test('a bulk delete skips referenced files and keeps the rest', function () {
    Storage::disk('public')->put('media/free.jpg', 'x');
    Storage::disk('public')->put('media/used.mp4', 'x');
    Video::factory()->create(['video_path' => 'media/used.mp4']);
    app(MediaIndexService::class)->indexAll();

    Livewire::test(MediaLibrary::class)
        ->call('deleteSelectedFiles', ['media/free.jpg', 'media/used.mp4']);

    expect(Storage::disk('public')->exists('media/free.jpg'))->toBeFalse()
        ->and(Storage::disk('public')->exists('media/used.mp4'))->toBeTrue();
});

// ── Uploads ─────────────────────────────────────────────────────────────────

test('an allowed file type uploads and is indexed at once', function () {
    Storage::disk('public')->makeDirectory('media');
    app(MediaIndexService::class)->indexAll();

    Livewire::test(MediaLibrary::class)
        ->set('uploadedFiles', [UploadedFile::fake()->image('photo.jpg')])
        ->call('uploadFiles');

    expect(MediaFile::where('root', 'media')->where('extension', 'jpg')->exists())->toBeTrue();
});

test('an executable file type is refused', function () {
    // storage/app/public is served directly by nginx, so this matters even
    // though only admins can reach the page.
    Storage::disk('public')->makeDirectory('media');

    Livewire::test(MediaLibrary::class)
        ->set('uploadedFiles', [UploadedFile::fake()->create('shell.phtml', 8, 'application/x-httpd-php')])
        ->call('uploadFiles')
        ->assertHasErrors('uploadedFiles.0');

    expect(MediaFile::where('extension', 'phtml')->exists())->toBeFalse();
});

// ── URL state ───────────────────────────────────────────────────────────────

test('browsing state round-trips through the query string', function () {
    seedFiles(['media/nested/a.jpg']);

    $component = Livewire::withQueryParams([
        'path' => 'media/nested',
        'q' => 'a',
        'in' => 'subtree',
        'type' => 'image',
        'sortBy' => 'name',
    ])->test(MediaLibrary::class);

    expect($component->get('currentDirectory'))->toBe('media/nested')
        ->and($component->get('search'))->toBe('a')
        ->and($component->get('searchScope'))->toBe('subtree')
        ->and($component->get('typeFilter'))->toBe('image')
        ->and($component->get('sortBy'))->toBe('name')
        ->and(listedNames($component))->toBe(['a.jpg']);
});

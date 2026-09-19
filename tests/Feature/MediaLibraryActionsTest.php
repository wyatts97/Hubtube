<?php

use App\Filament\Pages\MediaLibrary;
use App\Jobs\GenerateMediaThumbnailJob;
use App\Models\MediaFile;
use App\Models\MediaFolder;
use App\Models\Video;
use App\Services\Media\MediaIndexService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

/*
|--------------------------------------------------------------------------
| Media Library — rename, delete, move, upload
|--------------------------------------------------------------------------
|
| Every dialog is a Filament action that takes paths from the browser, so
| each test here also proves the action re-validates what it was handed.
|
*/

beforeEach(function () {
    asAdmin();
    // Rendering queues thumbnails, and the thumbnail service shells out to a
    // real ffmpeg; the thumbnail tests cover that path on their own.
    Queue::fake([GenerateMediaThumbnailJob::class]);
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

// ── Rename ──────────────────────────────────────────────────────────────────

test('a file can be renamed and its record follows', function () {
    Storage::disk('public')->put('media/clip.mp4', 'x');
    $video = Video::factory()->create(['video_path' => 'media/clip.mp4']);
    app(MediaIndexService::class)->indexAll();

    Livewire::test(MediaLibrary::class)
        ->callAction('rename', data: ['name' => 'trailer.mp4'], arguments: ['path' => 'media/clip.mp4'])
        ->assertHasNoActionErrors();

    expect(Storage::disk('public')->exists('media/trailer.mp4'))->toBeTrue()
        ->and($video->fresh()->video_path)->toBe('media/trailer.mp4')
        ->and(MediaFile::where('path', 'media/trailer.mp4')->exists())->toBeTrue();
});

test('a rename never overwrites an existing file', function () {
    seedFiles(['media/a.jpg' => 'a', 'media/b.jpg' => 'b']);

    Livewire::test(MediaLibrary::class)
        ->callAction('rename', data: ['name' => 'b.jpg'], arguments: ['path' => 'media/a.jpg']);

    expect(Storage::disk('public')->get('media/b.jpg'))->toBe('b')
        ->and(Storage::disk('public')->exists('media/a.jpg'))->toBeTrue();
});

test('a rename cannot change a file into a type uploads would refuse', function () {
    // storage/app/public is served directly by nginx.
    seedFiles(['media/photo.jpg']);

    Livewire::test(MediaLibrary::class)
        ->callAction('rename', data: ['name' => 'photo.php'], arguments: ['path' => 'media/photo.jpg']);

    expect(Storage::disk('public')->exists('media/photo.php'))->toBeFalse()
        ->and(Storage::disk('public')->exists('media/photo.jpg'))->toBeTrue();
});

test('a folder can be renamed and its files follow', function () {
    Storage::disk('public')->put('media/promos/clip.mp4', 'x');
    $video = Video::factory()->create(['video_path' => 'media/promos/clip.mp4']);
    app(MediaIndexService::class)->indexAll();

    Livewire::test(MediaLibrary::class)
        ->callAction('rename', data: ['name' => 'campaigns'], arguments: ['path' => 'media/promos']);

    expect(Storage::disk('public')->exists('media/campaigns/clip.mp4'))->toBeTrue()
        ->and(Storage::disk('public')->exists('media/promos/clip.mp4'))->toBeFalse()
        ->and($video->fresh()->video_path)->toBe('media/campaigns/clip.mp4')
        ->and(MediaFile::where('path', 'media/campaigns/clip.mp4')->exists())->toBeTrue()
        ->and(MediaFolder::where('path', 'media/promos')->exists())->toBeFalse();
});

test('renaming the folder you are in keeps you inside it', function () {
    seedFiles(['media/promos/a.jpg']);

    $component = Livewire::test(MediaLibrary::class)
        ->call('openDirectory', 'media/promos')
        ->callAction('rename', data: ['name' => 'campaigns'], arguments: ['path' => 'media/promos']);

    expect($component->get('currentDirectory'))->toBe('media/campaigns');
});

test('folders a record owns cannot be renamed', function () {
    // videos/{slug} and images/{ulid} are located by convention, so renaming
    // one orphans the record however carefully the paths are rewritten.
    seedFiles(['videos/my-clip/720p.mp4', 'images/abc/original.jpg']);

    Livewire::test(MediaLibrary::class)
        ->mountAction('rename', ['path' => 'videos/my-clip'])
        ->assertActionNotMounted('rename')
        ->mountAction('rename', ['path' => 'images/abc'])
        ->assertActionNotMounted('rename');

    // Called straight through, bypassing the mount check, it still refuses.
    Livewire::test(MediaLibrary::class)
        ->callAction('rename', data: ['name' => 'moved'], arguments: ['path' => 'videos/my-clip']);

    expect(Storage::disk('public')->exists('videos/my-clip/720p.mp4'))->toBeTrue();
});

test('a top-level root cannot be renamed or deleted', function () {
    seedFiles(['media/a.jpg']);

    Livewire::test(MediaLibrary::class)
        ->mountAction('rename', ['path' => 'media'])
        ->assertActionNotMounted('rename')
        ->callAction('delete', arguments: ['paths' => ['media']]);

    expect(Storage::disk('public')->exists('media/a.jpg'))->toBeTrue();
});

// ── Delete ──────────────────────────────────────────────────────────────────

test('a folder can be deleted, and the index follows', function () {
    seedFiles(['media/scratch/junk.txt']);

    Livewire::test(MediaLibrary::class)->callAction('delete', arguments: ['paths' => ['media/scratch']]);

    expect(Storage::disk('public')->exists('media/scratch'))->toBeFalse()
        ->and(MediaFile::where('path', 'media/scratch/junk.txt')->exists())->toBeFalse()
        ->and(MediaFolder::where('path', 'media/scratch')->exists())->toBeFalse();
});

test('a folder holding a referenced file cannot be deleted', function () {
    Storage::disk('public')->put('media/live/clip.mp4', 'x');
    Video::factory()->create(['video_path' => 'media/live/clip.mp4']);
    app(MediaIndexService::class)->indexAll();

    Livewire::test(MediaLibrary::class)->callAction('delete', arguments: ['paths' => ['media/live']]);

    expect(Storage::disk('public')->exists('media/live/clip.mp4'))->toBeTrue();
});

test('deleting the folder you are standing in moves you to its parent', function () {
    seedFiles(['media/promos/junk.txt']);

    $component = Livewire::test(MediaLibrary::class)
        ->call('openDirectory', 'media/promos')
        ->callAction('delete', arguments: ['paths' => ['media/promos']]);

    expect($component->get('currentDirectory'))->toBe('media');
});

test('a bulk delete only touches paths inside the library', function () {
    seedFiles(['media/one.jpg', 'media/two.jpg', 'secret.txt']);

    // The paths arrive from the browser, so a crafted one must be dropped.
    Livewire::test(MediaLibrary::class)
        ->callAction('delete', arguments: ['paths' => ['media/one.jpg', '../secret.txt', 'secret.txt']]);

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
        ->callAction('delete', arguments: ['paths' => ['media/free.jpg', 'media/used.mp4']]);

    expect(Storage::disk('public')->exists('media/free.jpg'))->toBeFalse()
        ->and(Storage::disk('public')->exists('media/used.mp4'))->toBeTrue();
});

// ── Move ────────────────────────────────────────────────────────────────────

test('files can be moved to another folder', function () {
    Storage::disk('public')->put('media/clip.mp4', 'x');
    Storage::disk('public')->makeDirectory('media/archive');
    $video = Video::factory()->create(['video_path' => 'media/clip.mp4']);
    app(MediaIndexService::class)->indexAll();

    Livewire::test(MediaLibrary::class)
        ->callAction('move', data: ['destination' => 'media/archive'], arguments: ['paths' => ['media/clip.mp4']])
        ->assertHasNoActionErrors();

    expect(Storage::disk('public')->exists('media/archive/clip.mp4'))->toBeTrue()
        ->and(Storage::disk('public')->exists('media/clip.mp4'))->toBeFalse()
        ->and($video->fresh()->video_path)->toBe('media/archive/clip.mp4')
        ->and(MediaFile::where('path', 'media/archive/clip.mp4')->exists())->toBeTrue()
        ->and(MediaFile::where('path', 'media/clip.mp4')->exists())->toBeFalse();
});

test('a move that would overwrite something renames instead', function () {
    seedFiles(['media/clip.mp4' => 'first', 'media/archive/clip.mp4' => 'already here']);

    Livewire::test(MediaLibrary::class)
        ->callAction('move', data: ['destination' => 'media/archive'], arguments: ['paths' => ['media/clip.mp4']]);

    expect(Storage::disk('public')->get('media/archive/clip.mp4'))->toBe('already here')
        ->and(Storage::disk('public')->get('media/archive/clip-1.mp4'))->toBe('first');
});

test('files a record owns are not moved', function () {
    Storage::disk('public')->put('videos/my-clip/720p.mp4', 'x');
    Storage::disk('public')->makeDirectory('media/archive');
    app(MediaIndexService::class)->indexAll();

    Livewire::test(MediaLibrary::class)
        ->callAction('move', data: ['destination' => 'media/archive'], arguments: ['paths' => ['videos/my-clip/720p.mp4']]);

    expect(Storage::disk('public')->exists('videos/my-clip/720p.mp4'))->toBeTrue()
        ->and(Storage::disk('public')->exists('media/archive/720p.mp4'))->toBeFalse();
});

test('a folder is never moved, so it cannot be moved into itself', function () {
    seedFiles(['media/a/clip.jpg', 'media/a/b/deeper.jpg']);

    Livewire::test(MediaLibrary::class)
        ->callAction('move', data: ['destination' => 'media/a/b'], arguments: ['paths' => ['media/a']]);

    expect(Storage::disk('public')->exists('media/a/clip.jpg'))->toBeTrue()
        ->and(Storage::disk('public')->exists('media/a/b/deeper.jpg'))->toBeTrue();
});

test('a move outside the library is refused', function () {
    seedFiles(['media/clip.mp4']);

    Livewire::test(MediaLibrary::class)
        ->callAction('move', data: ['destination' => '../escaped'], arguments: ['paths' => ['media/clip.mp4']]);

    expect(Storage::disk('public')->exists('media/clip.mp4'))->toBeTrue();
});

test('protected folders are not offered as move destinations', function () {
    seedFiles(['media/a.jpg', 'videos/my-clip/b.mp4', 'images/abc/c.jpg']);

    $destinations = Livewire::test(MediaLibrary::class)->instance()->getMoveDestinationsProperty();

    expect($destinations)->toContain('media')
        ->not->toContain('videos/my-clip')
        ->not->toContain('images/abc');
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

test('nothing uploads at All files, which is not a real folder', function () {
    Livewire::test(MediaLibrary::class)
        ->call('openDirectory', '')
        ->set('uploadedFiles', [UploadedFile::fake()->image('photo.jpg')])
        ->call('uploadFiles');

    expect(MediaFile::where('extension', 'jpg')->exists())->toBeFalse();
});

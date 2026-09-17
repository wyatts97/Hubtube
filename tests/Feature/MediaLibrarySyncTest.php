<?php

use App\Filament\Pages\MediaLibrary;
use App\Jobs\CompleteVideoProcessingJob;
use App\Jobs\IndexMediaDirectoryJob;
use App\Jobs\ReindexMediaLibraryJob;
use App\Models\Image;
use App\Models\MediaFile;
use App\Models\User;
use App\Models\Video;
use App\Services\Encoding\RenditionCoordinator;
use App\Services\Media\MediaIndexService;
use App\Services\Media\MediaReferenceResolver;
use Illuminate\Http\Testing\File;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

/*
|--------------------------------------------------------------------------
| Media Library — staying in step with the disk
|--------------------------------------------------------------------------
|
| Media arrives without going through the Media Library page: the encoder
| writes renditions, ImageService writes variants, the settings page writes
| avatars. The scheduled pass would eventually catch all of it, but "eventually"
| is a bad answer when you have just uploaded something — so the producers say
| so, and the reference flags are maintained by the models themselves.
|
*/

// ── Producers index their own output ────────────────────────────────────────

test('a finished video indexes its own folder', function () {
    Queue::fake();

    $video = Video::factory()->create(['slug' => 'my-clip']);

    (new CompleteVideoProcessingJob($video->id))->handle(app(RenditionCoordinator::class));

    Queue::assertPushed(
        IndexMediaDirectoryJob::class,
        fn (IndexMediaDirectoryJob $job) => $job->directory === 'videos/my-clip' && $job->recursive
    );
});

test('the directory index job is unique per directory', function () {
    // A burst of writes into one folder should collapse into a single scan.
    $first = new IndexMediaDirectoryJob('videos/my-clip');
    $second = new IndexMediaDirectoryJob('videos/my-clip');
    $other = new IndexMediaDirectoryJob('videos/other-clip');

    expect($first->uniqueId())->toBe($second->uniqueId())
        ->and($first->uniqueId())->not->toBe($other->uniqueId());
});

test('the directory index job brings a folder into the index', function () {
    Storage::disk('public')->put('videos/my-clip/720p.mp4', 'x');
    Storage::disk('public')->put('videos/my-clip/poster.jpg', 'x');

    expect(MediaFile::count())->toBe(0);

    (new IndexMediaDirectoryJob('videos/my-clip'))->handle(app(MediaIndexService::class));

    expect(MediaFile::pluck('path')->sort()->values()->all())
        ->toBe(['videos/my-clip/720p.mp4', 'videos/my-clip/poster.jpg']);
});

test('index jobs share the media-thumbnails queue so they never delay encoding', function () {
    Queue::fake();

    IndexMediaDirectoryJob::dispatch('media');
    ReindexMediaLibraryJob::dispatch();

    Queue::assertPushedOn('media-thumbnails', IndexMediaDirectoryJob::class);
    Queue::assertPushedOn('media-thumbnails', ReindexMediaLibraryJob::class);
});

test('a library rescan is queued, not run in the request', function () {
    Queue::fake();

    $admin = asAdmin();

    Livewire::test(MediaLibrary::class)->call('rescanLibrary');

    Queue::assertPushed(
        ReindexMediaLibraryJob::class,
        fn (ReindexMediaLibraryJob $job) => $job->notifyUserId === $admin->id && $job->prune
    );
});

test('a queued rescan indexes everything and notifies whoever asked', function () {
    $admin = User::factory()->admin()->create();

    Storage::disk('public')->put('media/a.jpg', 'x');
    Storage::disk('public')->put('videos/clip/b.mp4', 'x');

    (new ReindexMediaLibraryJob($admin->id))->handle(app(MediaIndexService::class));

    expect(MediaFile::count())->toBe(2);

    // Filament's own bell has a dedicated table, kept apart from the app's
    // pre-existing `notifications` schema.
    $this->assertDatabaseHas('filament_notifications', ['notifiable_id' => $admin->id]);
});

test('only one library rescan runs at a time', function () {
    expect((new ReindexMediaLibraryJob)->uniqueId())
        ->toBe((new ReindexMediaLibraryJob(5))->uniqueId());
});

// ── Reference flags follow the records ──────────────────────────────────────

test('pointing a video at a file marks it in use', function () {
    Storage::disk('public')->put('media/clip.mp4', 'x');
    app(MediaIndexService::class)->indexAll();

    expect(MediaFile::where('path', 'media/clip.mp4')->firstOrFail()->is_referenced)->toBeFalse();

    Video::factory()->create(['video_path' => 'media/clip.mp4']);

    expect(MediaFile::where('path', 'media/clip.mp4')->firstOrFail()->is_referenced)->toBeTrue();
});

test('moving a video to a different file frees the old one', function () {
    Storage::disk('public')->put('media/old.mp4', 'x');
    Storage::disk('public')->put('media/new.mp4', 'x');
    app(MediaIndexService::class)->indexAll();

    $video = Video::factory()->create(['video_path' => 'media/old.mp4']);
    expect(MediaFile::where('path', 'media/old.mp4')->firstOrFail()->is_referenced)->toBeTrue();

    $video->update(['video_path' => 'media/new.mp4']);

    expect(MediaFile::where('path', 'media/old.mp4')->firstOrFail()->is_referenced)->toBeFalse()
        ->and(MediaFile::where('path', 'media/new.mp4')->firstOrFail()->is_referenced)->toBeTrue();
});

test('a soft-deleted video keeps its files reserved, a force delete releases them', function () {
    Storage::disk('public')->put('media/clip.mp4', 'x');
    app(MediaIndexService::class)->indexAll();

    $video = Video::factory()->create(['video_path' => 'media/clip.mp4']);

    // Soft delete: the video can still be restored for 30 days, so its files
    // must stay protected.
    $video->delete();
    expect(MediaFile::where('path', 'media/clip.mp4')->firstOrFail()->is_referenced)->toBeTrue();

    $video->forceDelete();
    expect(MediaFile::where('path', 'media/clip.mp4')->firstOrFail()->is_referenced)->toBeFalse();
});

test('deleting an image releases its files', function () {
    Storage::disk('public')->put('images/abc/original.jpg', 'x');
    app(MediaIndexService::class)->indexAll();

    $image = Image::factory()->create(['file_path' => 'images/abc/original.jpg']);
    expect(MediaFile::where('path', 'images/abc/original.jpg')->firstOrFail()->is_referenced)->toBeTrue();

    $image->delete();
    expect(MediaFile::where('path', 'images/abc/original.jpg')->firstOrFail()->is_referenced)->toBeFalse();
});

test('saving a record costs nothing when the library has never been indexed', function () {
    // The observers run on every save, so the guard that skips unindexed paths
    // is what keeps them off the hot path of ordinary video work.
    Storage::disk('public')->put('media/clip.mp4', 'x');

    // One SELECT to find no indexed rows, and nothing else.
    $this->expectsDatabaseQueryCount(1, connection: null);

    app(MediaReferenceResolver::class)->syncForPaths(['media/clip.mp4']);
});

// ── Ghost rows ──────────────────────────────────────────────────────────────

test('acting on a file that has vanished drops its row instead of failing', function () {
    asAdmin();

    Storage::disk('public')->put('media/ghost.jpg', 'x');
    app(MediaIndexService::class)->indexAll();

    // Something else removes it — a shell, a cleanup command, another admin.
    Storage::disk('public')->delete('media/ghost.jpg');

    Livewire::test(MediaLibrary::class)
        ->call('confirmDelete', 'media/ghost.jpg')
        ->call('deleteFile');

    expect(MediaFile::where('path', 'media/ghost.jpg')->exists())->toBeFalse();
});

// ── Avatars and banners ─────────────────────────────────────────────────────

test('channel banners are browsable, and the dead channel-covers path is gone', function () {
    // 'banners' is where SettingsController actually writes; 'channel-covers'
    // was listed but appears nowhere else in the codebase.
    $roots = config('hubtube.media_library.allowed_paths');

    expect($roots)->toContain('banners')
        ->and($roots)->not->toContain('channel-covers');
});

test('an uploaded avatar is indexed straight away', function () {
    $user = asUser();

    $this->post('/settings/avatar', [
        'avatar' => File::image('me.jpg', 200, 200),
    ])->assertRedirect();

    expect(MediaFile::where('root', 'avatars')->exists())->toBeTrue();
});

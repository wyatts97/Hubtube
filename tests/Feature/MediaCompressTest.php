<?php

use App\Filament\Pages\MediaLibrary;
use App\Jobs\CompressMediaFileJob;
use App\Models\MediaFile;
use App\Models\Setting;
use App\Services\Encoding\FfmpegRunner;
use App\Services\Media\MediaCompressService;
use App\Services\Media\MediaIndexService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Support\FakeFfmpegRunner;

/*
|--------------------------------------------------------------------------
| Media Library — compress, and replacing a video's original upload
|--------------------------------------------------------------------------
|
| Every encode writes a new file beside its source and is verified before it
| is kept. The only path that deletes anything is "Replace original", which
| repoints the video first and removes the old upload after.
|
*/

beforeEach(function () {
    $this->admin = asAdmin();
    // Page renders queue thumbnail jobs, which run inline on the sync queue.
    fakeFfmpeg();
    // Pretend ffmpeg was built with every encoder; the fake answers nothing.
    Cache::put('media-compress:encoders', ['libx265', 'libvpx-vp9', 'libaom-av1'], 600);
});

function compress(): MediaCompressService
{
    return app(MediaCompressService::class);
}

function compressFixture(string $path = 'media/clip.mp4', int $bytes = 90_000): string
{
    Storage::disk('public')->put($path, str_repeat('x', $bytes));
    app(MediaIndexService::class)->indexAll();

    return $path;
}

// ── Service ─────────────────────────────────────────────────────────────────

test('targets split into videos and readable refusals', function () {
    compressFixture();
    Storage::disk('public')->put('media/photo.jpg', 'x');

    [$ok, $refused] = compress()->splitTargets(['media/clip.mp4', 'media/photo.jpg', 'media/gone.mp4']);

    expect($ok)->toBe(['media/clip.mp4'])
        ->and(collect($refused)->pluck('reason')->all())->toBe(['Not a video file.', 'No longer on disk.']);
});

test('output lands beside the source under a new name, never over another file', function () {
    compressFixture();

    expect(compress()->targetPathFor('media/clip.mp4', 'av1'))->toBe('media/clip.av1.mp4')
        ->and(compress()->targetPathFor('media/clip.mp4', 'vp9'))->toBe('media/clip.vp9.webm');

    Storage::disk('public')->put('media/clip.av1.mp4', 'x');
    expect(compress()->targetPathFor('media/clip.mp4', 'av1'))->toBe('media/clip.av1-1.mp4');
});

test('each codec builds the command it names, with progress reporting', function () {
    expect(compress()->buildCommand('/in.mp4', '/out.mp4', 'h265', 'balanced'))
        ->toContain('libx265')->toContain('-c:a copy')->toContain('-tag:v hvc1')->toContain('-progress pipe:1')
        ->and(compress()->buildCommand('/in.mp4', '/out.webm', 'vp9', 'small'))
        ->toContain('libvpx-vp9')->toContain('libopus')
        ->and(compress()->buildCommand('/in.mp4', '/out.mp4', 'av1', 'smallest'))
        ->toContain('libaom-av1');
});

test('every codec forces a keyframe interval, so the result can be seeked', function () {
    // Without this, ffmpeg uses the encoder default — libaom's is effectively
    // one keyframe at the start, which makes seeking stall the player.
    foreach (MediaCompressService::CODECS as $codec) {
        expect(compress()->buildCommand('/in.mp4', '/out.mp4', $codec, 'balanced'))
            ->toContain('-force_key_frames')
            ->toContain('n_forced*'.MediaCompressService::KEYFRAME_SECONDS);
    }
});

test('audio is only copied from an MP4 source, whose audio an MP4 can hold', function () {
    expect(compress()->buildCommand('/in.mp4', '/out.mp4', 'av1', 'balanced'))->toContain('-c:a copy')
        // PCM in .mov, WMA in .wmv, Vorbis in .mkv would fail a stream copy.
        ->and(compress()->buildCommand('/in.mov', '/out.mp4', 'av1', 'balanced'))->toContain('-c:a aac')
        ->and(compress()->buildCommand('/in.wmv', '/out.mp4', 'h265', 'balanced'))->toContain('-c:a aac');
});

test('AV1 prefers the much faster SVT-AV1 whenever it is installed', function () {
    Cache::put('media-compress:encoders', ['libsvtav1', 'libaom-av1'], 600);

    expect(compress()->available()['av1'])->toBeTrue()
        ->and(compress()->buildCommand('/in.mp4', '/out.mp4', 'av1', 'balanced'))->toContain('libsvtav1');
});

test('a codec the server cannot encode is reported unavailable', function () {
    Cache::put('media-compress:encoders', ['libx265'], 600);

    expect(compress()->available())->toBe(['h265' => true, 'vp9' => false, 'av1' => false])
        ->and(compress()->defaultCodec())->toBe('h265');
});

test('the dialog starts on the codec browsers play most widely', function () {
    expect(compress()->defaultCodec())->toBe('av1');

    Cache::put('media-compress:encoders', ['libx265', 'libvpx-vp9'], 600);
    expect(compress()->defaultCodec())->toBe('vp9');
});

test('the encoder list is parsed from ffmpeg -encoders', function () {
    Cache::forget('media-compress:encoders');
    app()->instance(FfmpegRunner::class, new class extends FakeFfmpegRunner
    {
        public function run(string $command, int $timeoutSeconds, ?callable $onProgress = null): array
        {
            return [0, "Encoders:\n V..... = Video\n ------\n V....D libx264              libx264 H.264\n V....D libsvtav1            SVT-AV1\n A....D aac                  AAC\n"];
        }
    });

    expect(compress()->available())->toBe(['h265' => false, 'vp9' => false, 'av1' => true]);
});

// ── Job ─────────────────────────────────────────────────────────────────────

test('compressing writes a new file, keeps the source, and reports the saving', function () {
    compressFixture();

    CompressMediaFileJob::dispatchSync('media/clip.mp4', 'av1', 'balanced', $this->admin->id);

    expect(Storage::disk('public')->size('media/clip.mp4'))->toBe(90_000)
        ->and(Storage::disk('public')->exists('media/clip.av1.mp4'))->toBeTrue()
        ->and(MediaFile::where('path', 'media/clip.av1.mp4')->exists())->toBeTrue()
        ->and(compress()->status('media/clip.mp4'))->toMatchArray([
            'state' => 'done', 'target' => 'media/clip.av1.mp4', 'before' => 90_000, 'after' => 20_480,
        ])
        ->and($this->admin->notifications()->count())->toBe(1);
});

test('an encode that is not smaller is discarded', function () {
    fakeFfmpeg()->outputBytes = 120_000;
    compressFixture();

    CompressMediaFileJob::dispatchSync('media/clip.mp4', 'h265', 'balanced', $this->admin->id);

    expect(Storage::disk('public')->exists('media/clip.h265.mp4'))->toBeFalse()
        ->and(compress()->status('media/clip.mp4'))->toMatchArray(['state' => 'failed', 'error' => 'It came out no smaller than the original.']);
});

test('a truncated encode is discarded', function () {
    fakeFfmpeg()->outputBytes = 100;
    compressFixture();

    CompressMediaFileJob::dispatchSync('media/clip.mp4', 'h265', 'balanced');

    expect(Storage::disk('public')->exists('media/clip.h265.mp4'))->toBeFalse()
        ->and(compress()->status('media/clip.mp4')['error'])->toBe('The compressed file is too small to be real.');
});

test('an ffmpeg failure cleans up and tells whoever asked', function () {
    $fake = fakeFfmpeg();
    $fake->failWhen = fn (string $command) => str_contains($command, 'libx265');
    compressFixture();

    CompressMediaFileJob::dispatchSync('media/clip.mp4', 'h265', 'balanced', $this->admin->id);

    expect(Storage::disk('public')->exists('media/clip.h265.mp4'))->toBeFalse()
        ->and(compress()->status('media/clip.mp4')['state'])->toBe('failed')
        ->and($this->admin->notifications()->first()->data['title'])->toContain('Could not compress');
});

test('compress jobs run on their own queue, one per file and codec', function () {
    $job = new CompressMediaFileJob('media/clip.mp4', 'av1');

    expect($job->queue)->toBe('media-compress')
        ->and($job->uniqueId())->not->toBe((new CompressMediaFileJob('media/clip.mp4', 'vp9'))->uniqueId())
        // Without a supervisor for the queue, the jobs would never run.
        ->and(config('horizon.environments.production.media-compress.queue'))->toBe(['media-compress'])
        ->and(config('horizon.environments.local.media-compress.queue'))->toBe(['media-compress']);
});

// ── Page action ─────────────────────────────────────────────────────────────

test('the compress action queues the chosen codec for each video', function () {
    Queue::fake();
    compressFixture();
    compressFixture('media/other.mov');

    Livewire::test(MediaLibrary::class)
        ->callAction('compress', data: ['codec' => 'av1', 'quality' => 'small'], arguments: ['paths' => ['media/clip.mp4', 'media/other.mov', 'media/../../.env']])
        ->assertHasNoActionErrors();

    Queue::assertPushed(CompressMediaFileJob::class, 2);
    Queue::assertPushed(CompressMediaFileJob::class, fn ($job) => $job->sourcePath === 'media/clip.mp4' && $job->codec === 'av1' && $job->quality === 'small');

    expect(compress()->status('media/clip.mp4')['state'])->toBe('queued');
});

test('the compress action does not open for a selection with no videos', function () {
    Storage::disk('public')->put('media/photo.jpg', 'x');

    Livewire::test(MediaLibrary::class)
        ->mountAction('compress', ['paths' => ['media/photo.jpg']])
        ->assertActionNotMounted('compress')
        ->assertNotified('Nothing here can be compressed');
});

test('a codec the server lacks is refused even if the form is tampered with', function () {
    Queue::fake();
    Cache::put('media-compress:encoders', ['libx265'], 600);
    compressFixture();

    Livewire::test(MediaLibrary::class)
        ->callAction('compress', data: ['codec' => 'av1', 'quality' => 'balanced'], arguments: ['paths' => ['media/clip.mp4']]);

    Queue::assertNotPushed(CompressMediaFileJob::class);
});

test('progress shows on the row while a compress is running', function () {
    compressFixture();
    compress()->setStatus('media/clip.mp4', ['state' => 'running', 'codec' => 'av1', 'percent' => 42]);

    Livewire::test(MediaLibrary::class)
        ->assertSee('Compressing 42%')
        ->assertSeeHtml('wire:poll.5s');
});

// ── Replace original ────────────────────────────────────────────────────────

/** A processed video plus a smaller AV1 copy of its original, indexed. */
function videoWithCompressedCopy(array $attributes = []): array
{
    $video = processedVideo($attributes);
    $copy = dirname($video->video_path).'/original.av1.mp4';
    Storage::disk('public')->put($copy, str_repeat('x', 30_000));
    app(MediaIndexService::class)->indexAll();

    return [$video, $copy];
}

test('the details pane offers a video original its compressed copies', function () {
    [$video, $copy] = videoWithCompressedCopy();

    $details = Livewire::test(MediaLibrary::class)
        ->call('openDirectory', dirname($video->video_path))
        ->call('selectFile', $video->video_path)
        ->assertSee('Replace original')
        ->instance()
        ->getSelectedFileDataProperty();

    expect($details['video']['id'])->toBe($video->id)
        ->and(collect($details['compressed_copies'])->pluck('path')->all())->toBe([$copy]);
});

test('replacing the original repoints the video and deletes the old upload', function () {
    [$video, $copy] = videoWithCompressedCopy();
    $old = $video->video_path;

    Livewire::test(MediaLibrary::class)
        ->callAction('replaceOriginal', arguments: ['path' => $old, 'replacement' => $copy]);

    $video->refresh();

    expect($video->video_path)->toBe($copy)
        ->and((int) $video->size)->toBe(30_000)
        ->and(Storage::disk('public')->exists($old))->toBeFalse()
        ->and(Storage::disk('public')->exists($copy))->toBeTrue()
        // No row left behind for the deleted upload.
        ->and(MediaFile::where('path', $old)->exists())->toBeFalse()
        // The file now being served must read as in use, or it is one click
        // from deletion.
        ->and((bool) MediaFile::where('path', $copy)->value('is_referenced'))->toBeTrue();
});

test('only a compressed copy of that same file can replace it', function () {
    [$video] = videoWithCompressedCopy();
    Storage::disk('public')->put('media/unrelated.mp4', str_repeat('x', 30_000));
    app(MediaIndexService::class)->indexAll();

    Livewire::test(MediaLibrary::class)
        ->callAction('replaceOriginal', arguments: ['path' => $video->video_path, 'replacement' => 'media/unrelated.mp4']);

    expect($video->fresh()->video_path)->toBe($video->video_path)
        ->and(Storage::disk('public')->exists($video->video_path))->toBeTrue();
});

test('a copy that has vanished leaves the video untouched', function () {
    [$video, $copy] = videoWithCompressedCopy();
    Storage::disk('public')->delete($copy);

    expect(compress()->replaceOriginal($video, $copy))->toBe('The compressed file could not be read.')
        ->and($video->fresh()->video_path)->toBe($video->video_path)
        ->and(Storage::disk('public')->exists($video->video_path))->toBeTrue();
});

test('replacing is refused when it would break playback or lose work', function (array $attributes, ?Closure $setup, string $replacement, string $reason) {
    [$video, $copy] = videoWithCompressedCopy($attributes);
    $setup?->call($this, $video);

    $target = $replacement === 'copy' ? $copy : dirname($video->video_path).'/'.$replacement;

    expect(compress()->replaceBlockedReason($video->fresh(), $target))->toContain($reason);
})->with([
    'stored in the cloud' => [['storage_disk' => 's3'], null, 'copy', 'not locally'],
    'embedded' => [['is_embedded' => true], null, 'copy', 'Embedded'],
    'still encoding' => [['status' => 'processing'], null, 'copy', 'still encoding'],
    'a WebM copy' => [[], null, 'original.vp9.webm', 'only an H.265 or AV1 copy'],
    'watermark not yet drawn' => [[], fn () => Setting::set('watermark_enabled', true), 'copy', 'watermark'],
]);

test('a soft-deleted video is not ours to touch', function () {
    [$video] = videoWithCompressedCopy();
    $video->delete();

    expect(compress()->videoOriginalFor($video->video_path))->toBeNull();
});

test('renditions and HLS segments are never offered for replacement', function () {
    [$video] = videoWithCompressedCopy();
    $dir = dirname($video->video_path);

    expect(compress()->videoOriginalFor($dir.'/processed/720p.mp4'))->toBeNull()
        ->and(compress()->videoOriginalFor($dir.'/processed/hls/720p/segment_000.ts'))->toBeNull();
});

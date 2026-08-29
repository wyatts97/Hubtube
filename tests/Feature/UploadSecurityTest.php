<?php

use App\Models\Category;
use App\Models\User;
use App\Models\Video;
use App\Services\VideoService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/*
|--------------------------------------------------------------------------
| Upload Security — regression tests
|--------------------------------------------------------------------------
|
| These cover a previously exploitable chain: the chunked-upload finalizer
| trusted the client-supplied original filename, and VideoService derived the
| stored extension from it. Because videos land on the public disk, that let
| any authenticated uploader write an executable file into the webroot.
|
*/

/**
 * Invoke VideoService::handleVideoUpload (protected) for a given uploaded file
 * and return the resulting stored path.
 */
function storeVideoVia(UploadedFile $file, User $user): string
{
    $video = app(VideoService::class)->create([
        'title' => 'Regression Fixture',
        'description' => 'A description long enough to satisfy validation.',
        'category_id' => Category::factory()->create()->id,
        'tags' => ['alpha', 'bravo', 'charlie'],
        'video_file' => $file,
    ], $user);

    return $video->fresh()->video_path;
}

test('stored video extension is never taken from the client filename', function () {
    Storage::fake('public');
    $user = User::factory()->create();

    // A .php original name must not produce a .php file on the public disk.
    $file = UploadedFile::fake()->create('payload.php', 64, 'video/mp4');

    $path = storeVideoVia($file, $user);

    expect($path)->not->toEndWith('.php');
    expect(strtolower(pathinfo($path, PATHINFO_EXTENSION)))
        ->toBeIn(config('hubtube.video.allowed_extensions'));
});

test('disallowed extensions fall back to mp4 rather than being stored verbatim', function () {
    Storage::fake('public');
    $user = User::factory()->create();

    foreach (['shell.phtml', 'x.phar', 'y.html', 'z.svg'] as $name) {
        $path = storeVideoVia(UploadedFile::fake()->create($name, 32, 'video/mp4'), $user);

        expect(strtolower(pathinfo($path, PATHINFO_EXTENSION)))
            ->toBeIn(config('hubtube.video.allowed_extensions'));
    }
});

test('finalize rejects an extension outside the video allowlist', function () {
    $user = asUser();
    $category = Category::factory()->create();

    $this->postJson('/upload/finalize', [
        'upload_id' => 'abc123',
        'extension' => 'php',
        'original_filename' => 'payload.php',
        'title' => 'Some Title',
        'description' => 'A description long enough to satisfy validation.',
        'category_id' => $category->id,
        'tags' => ['alpha', 'bravo', 'charlie'],
    ])->assertStatus(422)->assertJsonValidationErrors('extension');
});

test('chunk uploads from different users do not share a storage key', function () {
    $controller = app(App\Http\Controllers\VideoController::class);

    $method = new ReflectionMethod($controller, 'scopedUploadId');
    $method->setAccessible(true);

    $sameIdUserA = $method->invoke($controller, 1, 'shared-upload-id');
    $sameIdUserB = $method->invoke($controller, 2, 'shared-upload-id');

    expect($sameIdUserA)->not->toBe($sameIdUserB);

    // Deterministic for the same user, so resumable uploads still work.
    expect($method->invoke($controller, 1, 'shared-upload-id'))->toBe($sameIdUserA);
});

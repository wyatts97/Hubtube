<?php

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/*
|--------------------------------------------------------------------------
| Admin Video Stream Route
|--------------------------------------------------------------------------
*/

function createAdminStreamFixture(string $contents = '0123456789'): array
{
    $relativePath = 'videos/test-stream-' . Str::uuid() . '/sample.mp4';
    $absolutePath = storage_path('app/public/' . $relativePath);

    File::ensureDirectoryExists(dirname($absolutePath));
    file_put_contents($absolutePath, $contents);

    return [$relativePath, $absolutePath, dirname($absolutePath)];
}

test('guest cannot access admin video stream route', function () {
    $this->get('/admin/video-stream?path=videos/test/sample.mp4')->assertStatus(403);
});

test('regular user cannot access admin video stream route', function () {
    asUser();

    $this->get('/admin/video-stream?path=videos/test/sample.mp4')->assertStatus(403);
});

test('admin can stream video via query path', function () {
    asAdmin();
    [$relativePath, $absolutePath, $directoryPath] = createAdminStreamFixture(str_repeat('A', 16));

    try {
        $response = $this->withHeaders([
            'Range' => 'bytes=0-3',
        ])->get('/admin/video-stream?path=' . rawurlencode($relativePath));

        $response->assertStatus(206);
        $response->assertHeader('Accept-Ranges', 'bytes');
        $response->assertHeader('Content-Type', 'video/mp4');
        $response->assertHeader('Content-Range', 'bytes 0-3/16');
        // The route returns a streamed response, so getContent() is false.
        expect($response->streamedContent())->toBe('AAAA');
    } finally {
        File::delete($absolutePath);
        File::deleteDirectory($directoryPath);
    }
});

test('admin can stream video via legacy path segment', function () {
    asAdmin();
    [$relativePath, $absolutePath, $directoryPath] = createAdminStreamFixture();

    try {
        $response = $this->get('/admin/video-stream/' . $relativePath);

        $response->assertStatus(200);
        $response->assertHeader('Accept-Ranges', 'bytes');
        $response->assertHeader('Content-Type', 'video/mp4');
    } finally {
        File::delete($absolutePath);
        File::deleteDirectory($directoryPath);
    }
});

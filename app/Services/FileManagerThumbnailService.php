<?php

namespace App\Services;

use Throwable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver as GdDriver;

class FileManagerThumbnailService
{
    protected ImageManager $manager;

    protected string $thumbnailDir = 'thumbnails/.filemanager';

    public function __construct()
    {
        $this->manager = new ImageManager(new GdDriver());
    }

    /**
     * Get a thumbnail URL for a file on the public disk.
     * Returns a generic icon URL for unsupported file types.
     */
    public function thumbnailUrl(string $path): string
    {
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        if ($this->isImage($extension)) {
            return $this->imageThumbnailUrl($path);
        }

        if ($this->isVideo($extension)) {
            return $this->videoThumbnailUrl($path);
        }

        return $this->fallbackIconUrl($extension);
    }

    /**
     * Generate or return a cached image thumbnail.
     */
    public function imageThumbnailUrl(string $path): string
    {
        $thumbPath = $this->thumbnailPath($path);

        $cacheKey = 'filemanager_thumb:' . md5($thumbPath);
        $cacheTtl = (int) config('hubtube.media_library.cache_ttl', 300);

        $exists = Cache::remember($cacheKey, $cacheTtl, function () use ($thumbPath) {
            return Storage::disk('public')->exists($thumbPath);
        });

        if (!$exists) {
            $this->generateImageThumbnail($path, $thumbPath);
        }

        return Storage::disk('public')->url($thumbPath);
    }

    /**
     * Generate or return a cached video poster thumbnail.
     */
    public function videoThumbnailUrl(string $path): string
    {
        $thumbPath = $this->thumbnailPath($path);

        $cacheKey = 'filemanager_thumb:' . md5($thumbPath);
        $cacheTtl = (int) config('hubtube.media_library.cache_ttl', 300);

        $exists = Cache::remember($cacheKey, $cacheTtl, function () use ($thumbPath) {
            return Storage::disk('public')->exists($thumbPath);
        });

        if (!$exists) {
            $this->generateVideoThumbnail($path, $thumbPath);
        }

        return Storage::disk('public')->url($thumbPath);
    }

    /**
     * Return a generic icon URL based on file extension.
     */
    public function fallbackIconUrl(string $extension): string
    {
        // Return a generic file SVG as a data URI so it works as an <img> src without extra assets.
        return "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%239ca3af' stroke-width='1.5'%3E%3Cpath d='M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z'%3E%3C/path%3E%3Cpolyline points='14 2 14 8 20 8'%3E%3C/polyline%3E%3C/svg%3E";
    }

    /**
     * Determine the thumbnail storage path for a given source file path.
     */
    protected function thumbnailPath(string $sourcePath): string
    {
        $hash = md5($sourcePath);
        $directory = substr($hash, 0, 2);

        return "{$this->thumbnailDir}/{$directory}/{$hash}.webp";
    }

    /**
     * Generate an image thumbnail using Intervention Image v3.
     */
    protected function generateImageThumbnail(string $sourcePath, string $thumbPath): void
    {
        try {
            $absolutePath = Storage::disk('public')->path($sourcePath);
            if (!file_exists($absolutePath)) {
                return;
            }

            $width = (int) config('hubtube.media_library.thumbnail_width', 300);
            $height = (int) config('hubtube.media_library.thumbnail_height', 200);

            $image = $this->manager->read($absolutePath);
            $image->cover($width, $height);
            $encoded = $image->toWebp(85);

            Storage::disk('public')->makeDirectory(dirname($thumbPath));
            Storage::disk('public')->put($thumbPath, (string) $encoded, 'public');
        } catch (Throwable $e) {
            Log::warning('FileManagerThumbnailService: failed to generate image thumbnail', [
                'path' => $sourcePath,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Generate a video poster thumbnail using FFmpeg.
     */
    protected function generateVideoThumbnail(string $sourcePath, string $thumbPath): void
    {
        try {
            if (!FfmpegService::isAvailable()) {
                return;
            }

            $absolutePath = Storage::disk('public')->path($sourcePath);
            if (!file_exists($absolutePath)) {
                return;
            }

            $width = (int) config('hubtube.media_library.thumbnail_width', 300);
            $height = (int) config('hubtube.media_library.thumbnail_height', 200);
            $ffmpeg = FfmpegService::ffmpegPath();
            $tempDir = storage_path('app/temp');
            if (!is_dir($tempDir)) {
                mkdir($tempDir, 0755, true);
            }
            $tempOutput = $tempDir . '/' . Str::random(16) . '.webp';

            $cmd = sprintf(
                '%s -y -ss 00:00:01 -i %s -vframes 1 -vf "scale=%d:%d:force_original_aspect_ratio=decrease:flags=lanczos,pad=%d:%d:(ow-iw)/2:(oh-ih)/2:black" -c:v libwebp -lossless 0 -q:v 85 %s 2>&1',
                $ffmpeg,
                escapeshellarg($absolutePath),
                $width,
                $height,
                $width,
                $height,
                escapeshellarg($tempOutput)
            );

            shell_exec($cmd);

            if (file_exists($tempOutput)) {
                Storage::disk('public')->makeDirectory(dirname($thumbPath));
                Storage::disk('public')->put($thumbPath, file_get_contents($tempOutput), 'public');
                unlink($tempOutput);
            }
        } catch (Throwable $e) {
            Log::warning('FileManagerThumbnailService: failed to generate video thumbnail', [
                'path' => $sourcePath,
                'error' => $e->getMessage(),
            ]);
        }
    }

    protected function isImage(string $extension): bool
    {
        return in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'bmp', 'ico']);
    }

    protected function isVideo(string $extension): bool
    {
        return in_array($extension, ['mp4', 'mov', 'webm', 'mkv', 'avi', 'flv', 'wmv']);
    }
}

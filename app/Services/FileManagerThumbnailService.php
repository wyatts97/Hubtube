<?php

namespace App\Services;

use App\Models\MediaFile;
use App\Services\Media\MediaType;
use App\Services\Media\ThumbnailResult;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Intervention\Image\ImageManager;
use Throwable;

/**
 * Generates the thumbnails the admin Media Library shows.
 *
 * Generation only. This used to be asked for a URL while a page was rendering
 * and would generate on the spot if the file was missing, guarded by a cache
 * entry that recorded the answer from *before* generation and never corrected
 * it — so every render re-decoded, re-resized and re-encoded every thumbnail on
 * the page, and re-spawned ffmpeg for every video, for five minutes at a time.
 *
 * Thumbnail state now lives on the media_files row and generation happens in
 * GenerateMediaThumbnailJob, so nothing here is ever called while rendering.
 */
class FileManagerThumbnailService
{
    protected ImageManager $manager;

    public function __construct()
    {
        $this->manager = new ImageManager(new GdDriver);
    }

    /**
     * Where generated thumbnails live.
     *
     * Still under `thumbnails/`, which is no longer a browsable path (see
     * media_library.excluded_paths). Deliberately left where it is rather than
     * moved somewhere tidier: moving it would orphan every thumbnail already on
     * disk with nothing to clean them up.
     */
    public function directory(): string
    {
        return trim((string) config('hubtube.media_library.thumbnail_dir', 'thumbnails/.filemanager'), '/');
    }

    /**
     * The thumbnail path for a source file.
     *
     * Content-addressed on the source path and sharded two hex characters deep,
     * so no directory holds more than a few thousand entries.
     */
    public function thumbnailPathFor(string $sourcePath): string
    {
        $hash = md5($sourcePath);

        return sprintf('%s/%s/%s.webp', $this->directory(), substr($hash, 0, 2), $hash);
    }

    /** Whether this file is one a thumbnail can be produced for at all. */
    public function supports(string $extension): bool
    {
        return MediaType::isRasterisable($extension)
            || MediaType::isVideo($extension)
            || strtolower($extension) === 'svg';
    }

    /**
     * Produce the thumbnail for one indexed file.
     *
     * Never throws: every outcome is expressed in the returned result so the
     * caller can record it and decide whether a retry is worth anything.
     */
    public function generate(MediaFile $file): ThumbnailResult
    {
        $extension = strtolower((string) $file->extension);

        if (! Storage::disk('public')->exists($file->path)) {
            return ThumbnailResult::failed('The file is no longer on disk.');
        }

        // An SVG is its own thumbnail: it scales losslessly, GD cannot decode
        // it, and in an <img> context it executes nothing. The grid renders the
        // source file directly.
        if ($extension === 'svg') {
            return ThumbnailResult::notNeeded();
        }

        if (MediaType::isRasterisable($extension)) {
            return $this->generateImageThumbnail($file);
        }

        if (MediaType::isVideo($extension)) {
            return $this->generateVideoThumbnail($file);
        }

        // bmp, ico, tiff, heic and everything non-visual. These used to be
        // handed to GD, which threw and logged a warning per file per render.
        return ThumbnailResult::unsupported();
    }

    /**
     * Delete a source file's generated thumbnail.
     *
     * Nothing used to do this, so the cache only ever grew: every file ever
     * deleted or renamed left its thumbnail behind for good.
     */
    public function deleteFor(string $sourcePath): void
    {
        $thumbPath = $this->thumbnailPathFor($sourcePath);

        try {
            if (Storage::disk('public')->exists($thumbPath)) {
                Storage::disk('public')->delete($thumbPath);
            }
        } catch (Throwable) {
            // A thumbnail we cannot remove is not worth failing a delete over.
        }
    }

    /**
     * A generic file icon, as a data URI so it needs no asset pipeline.
     */
    public function fallbackIconUrl(string $extension): string
    {
        return "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%239ca3af' stroke-width='1.5'%3E%3Cpath d='M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z'%3E%3C/path%3E%3Cpolyline points='14 2 14 8 20 8'%3E%3C/polyline%3E%3C/svg%3E";
    }

    protected function generateImageThumbnail(MediaFile $file): ThumbnailResult
    {
        $thumbPath = $this->thumbnailPathFor($file->path);

        try {
            $absolutePath = Storage::disk('public')->path($file->path);

            [$width, $height] = $this->dimensions($absolutePath);

            $image = $this->manager->read($absolutePath);
            $image->cover($this->width(), $this->height());

            Storage::disk('public')->makeDirectory(dirname($thumbPath));
            Storage::disk('public')->put($thumbPath, (string) $image->toWebp(85), 'public');

            return ThumbnailResult::ready($thumbPath, $width, $height);
        } catch (Throwable $e) {
            return ThumbnailResult::failed($e->getMessage());
        }
    }

    protected function generateVideoThumbnail(MediaFile $file): ThumbnailResult
    {
        $thumbPath = $this->thumbnailPathFor($file->path);

        if (! FfmpegService::isAvailable()) {
            // Not a failure of this file — retryable once ffmpeg is installed.
            return ThumbnailResult::unavailable('No ffmpeg binary was found.');
        }

        $tempOutput = null;

        try {
            $absolutePath = Storage::disk('public')->path($file->path);
            $tempDir = storage_path('app/temp');

            if (! is_dir($tempDir)) {
                mkdir($tempDir, 0755, true);
            }

            $tempOutput = $tempDir.'/'.Str::random(16).'.webp';

            $command = sprintf(
                '%s -y -ss 00:00:01 -i %s -vframes 1 -vf "scale=%d:%d:force_original_aspect_ratio=decrease:flags=lanczos,pad=%d:%d:(ow-iw)/2:(oh-ih)/2:black" -c:v libwebp -lossless 0 -q:v 85 %s',
                FfmpegService::ffmpegPath(),
                escapeshellarg($absolutePath),
                $this->width(),
                $this->height(),
                $this->width(),
                $this->height(),
                escapeshellarg($tempOutput),
            );

            shell_exec($command);

            if (! file_exists($tempOutput)) {
                return ThumbnailResult::failed('ffmpeg produced no frame.');
            }

            Storage::disk('public')->makeDirectory(dirname($thumbPath));
            Storage::disk('public')->put($thumbPath, file_get_contents($tempOutput), 'public');

            return ThumbnailResult::ready($thumbPath, null, null, $this->probeDuration($absolutePath));
        } catch (Throwable $e) {
            return ThumbnailResult::failed($e->getMessage());
        } finally {
            if ($tempOutput !== null && file_exists($tempOutput)) {
                @unlink($tempOutput);
            }
        }
    }

    /**
     * A video's length in seconds.
     *
     * Read here because the file is already open and the process already spawned
     * — the page used to run its own ffprobe per video per render.
     */
    protected function probeDuration(string $absolutePath): ?int
    {
        try {
            $output = shell_exec(sprintf(
                '%s -v error -show_entries format=duration -of default=noprint_wrappers=1:nokey=1 %s',
                FfmpegService::ffprobePath(),
                escapeshellarg($absolutePath),
            ));

            $seconds = (int) round((float) trim((string) $output));

            return $seconds > 0 ? $seconds : null;
        } catch (Throwable) {
            return null;
        }
    }

    /** An image's real pixel dimensions, for the details panel. */
    protected function dimensions(string $absolutePath): array
    {
        $size = @getimagesize($absolutePath);

        if (! is_array($size)) {
            return [null, null];
        }

        // Anything past the smallint columns is not worth recording.
        $width = $size[0] ?? null;
        $height = $size[1] ?? null;

        return [
            $width !== null && $width <= 65535 ? (int) $width : null,
            $height !== null && $height <= 65535 ? (int) $height : null,
        ];
    }

    protected function width(): int
    {
        return (int) config('hubtube.media_library.thumbnail_width', 300);
    }

    protected function height(): int
    {
        return (int) config('hubtube.media_library.thumbnail_height', 200);
    }
}

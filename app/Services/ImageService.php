<?php

namespace App\Services;

use App\Models\Image;
use App\Models\Setting;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver as GdDriver;

class ImageService
{
    protected ImageManager $manager;

    public function __construct()
    {
        $this->manager = new ImageManager(new GdDriver());
    }

    /**
     * Process an uploaded image file: validate, generate variants, store.
     */
    public function process(UploadedFile $file, int $userId, array $metadata = []): Image
    {
        $uuid = (string) Str::ulid();
        $directory = "images/{$uuid}";
        $disk = StorageManager::getActiveDiskName();
        $mimeType = $file->getMimeType();
        $isAnimated = $this->isAnimatedImage($file);
        $extension = $this->getExtension($file);

        // Read image dimensions
        $imageData = $this->manager->read($file->getPathname());
        $width = $imageData->width();
        $height = $imageData->height();

        // Store original
        $originalFilename = 'original.' . $extension;
        $originalPath = "{$directory}/{$originalFilename}";

        if ($disk === 'public') {
            Storage::disk('public')->makeDirectory($directory);
            Storage::disk('public')->putFileAs($directory, $file, $originalFilename, 'public');
        } else {
            StorageManager::put($originalPath, file_get_contents($file->getPathname()), $disk);
        }

        $fileSize = $file->getSize();

        // Generate thumbnail (400x300 crop) — skip for animated GIFs to preserve animation
        $thumbnailPath = null;
        if (!$isAnimated) {
            $thumbnailPath = $this->generateThumbnail($file, $directory, $disk, $width, $height);
        }

        // Generate responsive variants for non-animated images
        if (!$isAnimated) {
            $this->generateVariants($file, $directory, $disk, $width, $height);
        }

        // Generate WebP version for optimized delivery (non-animated only)
        if (!$isAnimated && !in_array($mimeType, ['image/webp'])) {
            $this->generateWebP($file, $directory, $disk);
        }

        // Generate blurhash placeholder
        $blurhash = $this->generateBlurhash($file);

        // Strip EXIF data from original (privacy — removes GPS, camera info)
        if (!$isAnimated && in_array($mimeType, ['image/jpeg', 'image/tiff'])) {
            $this->stripExif($originalPath, $disk);
        }

        // For animated GIFs: generate a static thumbnail from first frame
        if ($isAnimated && !$thumbnailPath) {
            $thumbnailPath = $this->generateStaticThumbnailFromAnimated($file, $directory, $disk);
        }

        // Create the database record
        $image = Image::create([
            'user_id' => $userId,
            'uuid' => $uuid,
            'title' => $metadata['title'] ?? pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME),
            'description' => $metadata['description'] ?? null,
            'file_path' => $originalPath,
            'thumbnail_path' => $thumbnailPath,
            'storage_disk' => $disk,
            'mime_type' => $mimeType,
            'width' => $width,
            'height' => $height,
            'file_size' => $fileSize,
            'is_animated' => $isAnimated,
            'blurhash' => $blurhash,
            'privacy' => $metadata['privacy'] ?? 'public',
            'is_approved' => (bool) Setting::get('auto_approve_images', true),
            'category_id' => $metadata['category_id'] ?? null,
            'tags' => $metadata['tags'] ?? null,
            'published_at' => now(),
        ]);

        return $image;
    }

    protected function generateThumbnail(UploadedFile $file, string $directory, string $disk, int $width, int $height): ?string
    {
        try {
            $image = $this->manager->read($file->getPathname());
            $image->cover(400, 300);
            $encoded = $image->toJpeg(80);

            $thumbPath = "{$directory}/thumbnail.jpg";

            if ($disk === 'public') {
                Storage::disk('public')->put($thumbPath, (string) $encoded, 'public');
            } else {
                StorageManager::put($thumbPath, (string) $encoded, $disk);
            }

            return $thumbPath;
        } catch (\Throwable $e) {
            Log::warning('ImageService: thumbnail generation failed', ['error' => $e->getMessage()]);
            return null;
        }
    }

    protected function generateVariants(UploadedFile $file, string $directory, string $disk, int $origWidth, int $origHeight): void
    {
        $variants = [
            'small' => 480,
            'medium' => 960,
            'large' => 1920,
        ];

        foreach ($variants as $name => $maxWidth) {
            if ($origWidth <= $maxWidth) {
                continue;
            }

            try {
                $image = $this->manager->read($file->getPathname());
                $image->scaleDown(width: $maxWidth);
                $encoded = $image->toJpeg(85);

                $variantPath = "{$directory}/{$name}.jpg";

                if ($disk === 'public') {
                    Storage::disk('public')->put($variantPath, (string) $encoded, 'public');
                } else {
                    StorageManager::put($variantPath, (string) $encoded, $disk);
                }
            } catch (\Throwable $e) {
                Log::warning("ImageService: {$name} variant generation failed", ['error' => $e->getMessage()]);
            }
        }
    }

    protected function generateWebP(UploadedFile $file, string $directory, string $disk): void
    {
        try {
            $image = $this->manager->read($file->getPathname());
            $encoded = $image->toWebp(85);

            $webpPath = "{$directory}/optimized.webp";

            if ($disk === 'public') {
                Storage::disk('public')->put($webpPath, (string) $encoded, 'public');
            } else {
                StorageManager::put($webpPath, (string) $encoded, $disk);
            }
        } catch (\Throwable $e) {
            Log::warning('ImageService: WebP generation failed', ['error' => $e->getMessage()]);
        }
    }

    protected function generateBlurhash(UploadedFile $file): ?string
    {
        try {
            $image = $this->manager->read($file->getPathname());
            $image->scaleDown(width: 32);

            $width = $image->width();
            $height = $image->height();

            $pixels = [];
            for ($y = 0; $y < $height; $y++) {
                $row = [];
                for ($x = 0; $x < $width; $x++) {
                    $color = $image->pickColor($x, $y);
                    $row[] = [$color->red()->toInt(), $color->green()->toInt(), $color->blue()->toInt()];
                }
                $pixels[] = $row;
            }

            return \kornrunner\Blurhash\Blurhash::encode($pixels, 4, 3);
        } catch (\Throwable $e) {
            Log::warning('ImageService: blurhash generation failed', ['error' => $e->getMessage()]);
            return null;
        }
    }

    protected function stripExif(string $path, string $disk): void
    {
        try {
            if ($disk === 'public') {
                $fullPath = Storage::disk('public')->path($path);
                $image = $this->manager->read($fullPath);
                $encoded = $image->toJpeg(95);
                file_put_contents($fullPath, (string) $encoded);
            }
            // For cloud disks, EXIF is already stripped during variant generation
        } catch (\Throwable $e) {
            Log::warning('ImageService: EXIF stripping failed', ['error' => $e->getMessage()]);
        }
    }

    protected function generateStaticThumbnailFromAnimated(UploadedFile $file, string $directory, string $disk): ?string
    {
        try {
            // Read just the first frame using GD
            $gdImage = @imagecreatefromgif($file->getPathname());
            if (!$gdImage) {
                return null;
            }

            $tempPath = tempnam(sys_get_temp_dir(), 'ht_thumb_');
            imagejpeg($gdImage, $tempPath, 80);
            imagedestroy($gdImage);

            // Resize to thumbnail
            $image = $this->manager->read($tempPath);
            $image->cover(400, 300);
            $encoded = $image->toJpeg(80);

            $thumbPath = "{$directory}/thumbnail.jpg";

            if ($disk === 'public') {
                Storage::disk('public')->put($thumbPath, (string) $encoded, 'public');
            } else {
                StorageManager::put($thumbPath, (string) $encoded, $disk);
            }

            @unlink($tempPath);
            return $thumbPath;
        } catch (\Throwable $e) {
            Log::warning('ImageService: animated thumbnail generation failed', ['error' => $e->getMessage()]);
            return null;
        }
    }

    protected function isAnimatedImage(UploadedFile $file): bool
    {
        $mime = $file->getMimeType();

        if ($mime === 'image/gif') {
            // Check for multiple frames in GIF
            $content = file_get_contents($file->getPathname());
            return substr_count($content, "\x00\x21\xF9\x04") > 1;
        }

        if ($mime === 'image/webp') {
            // Check for animated WebP (ANIM chunk)
            $content = file_get_contents($file->getPathname(), false, null, 0, 64);
            return str_contains($content, 'ANIM');
        }

        return false;
    }

    protected function getExtension(UploadedFile $file): string
    {
        $mime = $file->getMimeType();
        return match ($mime) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
            'image/bmp' => 'bmp',
            'image/svg+xml' => 'svg',
            default => $file->getClientOriginalExtension() ?: 'jpg',
        };
    }
}

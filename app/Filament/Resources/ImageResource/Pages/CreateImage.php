<?php

namespace App\Filament\Resources\ImageResource\Pages;

use App\Filament\Resources\ImageResource;
use App\Models\Image;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Intervention\Image\ImageManager;
use Symfony\Component\Uid\Ulid;
use Throwable;

class CreateImage extends CreateRecord
{
    protected static string $resource = ImageResource::class;

    protected ImageManager $manager;

    /**
     * Lazily build the Intervention image manager so we don't construct it
     * during Livewire component instantiation (which has its own constructor).
     */
    protected function imageManager(): ImageManager
    {
        return $this->manager ??= new ImageManager(new GdDriver);
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['uuid'] = (string) Ulid::generate();
        $data['slug'] = $this->generateUniqueSlug($data['title'] ?? 'image');
        $data['is_approved'] = $data['is_approved'] ?? true;
        $data['privacy'] = $data['privacy'] ?? 'public';

        // Handle the uploaded image file
        if (! empty($data['image_file'])) {
            $tempPath = $data['image_file'];
            $slug = $data['slug'];
            $directory = "images/{$slug}";
            $extension = pathinfo($tempPath, PATHINFO_EXTENSION) ?: 'jpg';
            $filename = Str::slug($data['title'] ?? 'image', '_').'.'.$extension;
            $newPath = "{$directory}/{$filename}";

            // Move from Filament's temp upload location to the correct directory
            if (Storage::disk('public')->exists($tempPath)) {
                Storage::disk('public')->makeDirectory($directory);
                Storage::disk('public')->move($tempPath, $newPath);

                // Clean up empty admin-uploads directory
                $tempDir = dirname($tempPath);
                if (Storage::disk('public')->exists($tempDir) && empty(Storage::disk('public')->files($tempDir))) {
                    Storage::disk('public')->deleteDirectory($tempDir);
                }

                $data['file_path'] = $newPath;
                $data['storage_disk'] = 'public';
                $data['file_size'] = Storage::disk('public')->size($newPath);

                // Populate required image metadata from the moved file
                $fullPath = Storage::disk('public')->path($newPath);
                $data['mime_type'] = mime_content_type($fullPath) ?: 'image/jpeg';
                $data['is_animated'] = $this->isAnimatedImage($fullPath, $data['mime_type']);

                try {
                    $imageData = $this->imageManager()->read($fullPath);
                    $data['width'] = $imageData->width();
                    $data['height'] = $imageData->height();
                } catch (Throwable $e) {
                    // Roll back the moved file so we don't leave an orphan on disk
                    Storage::disk('public')->delete($newPath);
                    Storage::disk('public')->deleteDirectory($directory);
                    throw $e;
                }
            }
        }

        // Mark the image as published when it is approved/visible
        if (! empty($data['is_approved']) && empty($data['published_at'])) {
            $data['published_at'] = now();
        }

        unset($data['image_file']);

        return $data;
    }

    protected function generateUniqueSlug(string $title): string
    {
        $baseSlug = Str::slug($title) ?: 'image';
        $slug = $baseSlug;
        $suffix = 2;
        while (Image::where('slug', $slug)->exists()) {
            $slug = $baseSlug.'-'.$suffix;
            $suffix++;
        }

        return $slug;
    }

    /**
     * Detect animated GIF/WebP from a local file path.
     * Mirrors ImageService::isAnimatedImage() but works on a moved file path
     * rather than an UploadedFile instance.
     */
    protected function isAnimatedImage(string $path, string $mime): bool
    {
        if ($mime === 'image/gif') {
            $content = @file_get_contents($path);

            return $content !== false && substr_count($content, "\x00\x21\xF9\x04") > 1;
        }

        if ($mime === 'image/webp') {
            $content = @file_get_contents($path, false, null, 0, 64);

            return $content !== false && str_contains($content, 'ANIM');
        }

        return false;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('edit', ['record' => $this->record]);
    }
}

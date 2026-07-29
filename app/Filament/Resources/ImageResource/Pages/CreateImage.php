<?php

namespace App\Filament\Resources\ImageResource\Pages;

use App\Filament\Resources\ImageResource;
use App\Models\Image;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\Uid\Ulid;

class CreateImage extends CreateRecord
{
    protected static string $resource = ImageResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['uuid'] = (string) Ulid::generate();
        $data['slug'] = $this->generateUniqueSlug($data['title'] ?? 'image');
        $data['is_approved'] = $data['is_approved'] ?? true;
        $data['privacy'] = $data['privacy'] ?? 'public';

        // Handle the uploaded image file
        if (!empty($data['file_path'])) {
            $tempPath = $data['file_path'];
            $slug = $data['slug'];
            $directory = "images/{$slug}";
            $extension = pathinfo($tempPath, PATHINFO_EXTENSION) ?: 'jpg';
            $filename = Str::slug($data['title'] ?? 'image', '_') . '.' . $extension;
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
            }
        }

        unset($data['file_path']);

        return $data;
    }

    protected function generateUniqueSlug(string $title): string
    {
        $baseSlug = Str::slug($title) ?: 'image';
        $slug = $baseSlug;
        $suffix = 2;
        while (Image::withTrashed()->where('slug', $slug)->exists()) {
            $slug = $baseSlug . '-' . $suffix;
            $suffix++;
        }
        return $slug;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('edit', ['record' => $this->record]);
    }
}

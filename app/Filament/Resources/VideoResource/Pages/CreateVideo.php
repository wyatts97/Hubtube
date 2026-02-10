<?php

namespace App\Filament\Resources\VideoResource\Pages;

use App\Events\VideoUploaded;
use App\Filament\Resources\VideoResource;
use App\Jobs\ProcessVideoJob;
use App\Models\Video;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CreateVideo extends CreateRecord
{
    protected static string $resource = VideoResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['uuid'] = (string) Str::uuid();
        $data['slug'] = $this->generateUniqueSlug($data['title']);
        $data['status'] = 'pending';

        // Handle the uploaded video file
        if (!empty($data['video_file'])) {
            $tempPath = $data['video_file'];
            $slug = $data['slug'];
            $directory = "videos/{$slug}";
            $extension = pathinfo($tempPath, PATHINFO_EXTENSION) ?: 'mp4';
            $filename = Str::slug($data['title'], '_') . '.' . $extension;
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

                $data['video_path'] = $newPath;
                $data['storage_disk'] = 'public';
                $data['size'] = Storage::disk('public')->size($newPath);
            }
        }

        unset($data['video_file']);

        return $data;
    }

    protected function afterCreate(): void
    {
        $video = $this->record;

        if ($video->video_path) {
            event(new VideoUploaded($video));
        }
    }

    protected function generateUniqueSlug(string $title): string
    {
        $baseSlug = Str::slug($title) ?: 'video';
        $slug = $baseSlug;
        $suffix = 2;
        while (Video::withTrashed()->where('slug', $slug)->exists()) {
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

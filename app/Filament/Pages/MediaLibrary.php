<?php

namespace App\Filament\Pages;

use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\WithFileUploads;

use App\Filament\Concerns\HasCustomizableNavigation;

class MediaLibrary extends Page
{
    use HasCustomizableNavigation;
    use WithFileUploads;

    protected static ?string $navigationIcon = 'heroicon-o-photo';
    protected static ?string $navigationLabel = 'Media Library';
    protected static ?string $navigationGroup = 'Appearance';
    protected static ?int $navigationSort = 99;
    protected static string $view = 'filament.pages.media-library';

    public string $activeTab = 'images';

    // Upload state
    public $uploadedImages = [];
    public $uploadedVideos = [];

    // Confirmation
    public ?string $deleteTarget = null;

    public function getImageFiles(): array
    {
        $files = Storage::disk('public')->files('media/images');
        $result = [];

        foreach ($files as $path) {
            $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
            if (!in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'ico'])) {
                continue;
            }
            $result[] = [
                'path'     => $path,
                'name'     => basename($path),
                'url'      => Storage::disk('public')->url($path),
                'size'     => $this->formatBytes(Storage::disk('public')->size($path)),
                'modified' => Storage::disk('public')->lastModified($path),
            ];
        }

        usort($result, fn ($a, $b) => $b['modified'] - $a['modified']);

        return $result;
    }

    public function getVideoFiles(): array
    {
        $files = Storage::disk('public')->files('media/ads');
        $result = [];

        foreach ($files as $path) {
            $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
            if (!in_array($ext, ['mp4', 'webm', 'mov'])) {
                continue;
            }
            $result[] = [
                'path'     => $path,
                'name'     => basename($path),
                'url'      => Storage::disk('public')->url($path),
                'size'     => $this->formatBytes(Storage::disk('public')->size($path)),
                'modified' => Storage::disk('public')->lastModified($path),
                'duration' => $this->getVideoDuration(Storage::disk('public')->path($path)),
            ];
        }

        usort($result, fn ($a, $b) => $b['modified'] - $a['modified']);

        return $result;
    }

    public function uploadImages(): void
    {
        $this->validate([
            'uploadedImages.*' => 'file|mimes:jpg,jpeg,png,gif,webp,svg,ico|max:10240',
        ]);

        Storage::disk('public')->makeDirectory('media/images');

        $count = 0;
        foreach ($this->uploadedImages as $file) {
            $original = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
            $ext      = $file->getClientOriginalExtension();
            $filename = Str::slug($original) . '-' . Str::random(6) . '.' . $ext;
            $file->storeAs('media/images', $filename, 'public');
            $count++;
        }

        $this->uploadedImages = [];

        Notification::make()
            ->title("Uploaded {$count} image" . ($count !== 1 ? 's' : ''))
            ->success()
            ->send();
    }

    public function uploadVideos(): void
    {
        $this->validate([
            'uploadedVideos.*' => 'file|mimes:mp4,webm,mov|max:204800',
        ]);

        Storage::disk('public')->makeDirectory('media/ads');

        $count = 0;
        foreach ($this->uploadedVideos as $file) {
            $original = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
            $ext      = $file->getClientOriginalExtension();
            $filename = Str::slug($original) . '-' . Str::random(6) . '.' . $ext;
            $file->storeAs('media/ads', $filename, 'public');
            $count++;
        }

        $this->uploadedVideos = [];

        Notification::make()
            ->title("Uploaded {$count} ad video" . ($count !== 1 ? 's' : ''))
            ->success()
            ->send();
    }

    public function confirmDelete(string $path): void
    {
        $this->deleteTarget = $path;
    }

    public function cancelDelete(): void
    {
        $this->deleteTarget = null;
    }

    public function deleteFile(): void
    {
        if (!$this->deleteTarget) {
            return;
        }

        // Only allow deleting from our media directories
        if (!Str::startsWith($this->deleteTarget, 'media/')) {
            Notification::make()->title('Invalid path')->danger()->send();
            $this->deleteTarget = null;
            return;
        }

        if (Storage::disk('public')->exists($this->deleteTarget)) {
            Storage::disk('public')->delete($this->deleteTarget);
            Notification::make()->title('File deleted')->success()->send();
        }

        $this->deleteTarget = null;
    }

    protected function getVideoDuration(string $absolutePath): ?string
    {
        if (!file_exists($absolutePath)) return null;
        try {
            $output = shell_exec('ffprobe -v error -show_entries format=duration -of default=noprint_wrappers=1:nokey=1 ' . escapeshellarg($absolutePath) . ' 2>/dev/null');
            $seconds = (int) round((float) trim($output ?? ''));
            if ($seconds <= 0) return null;
            return sprintf('%d:%02d', intdiv($seconds, 60), $seconds % 60);
        } catch (\Throwable) {
            return null;
        }
    }

    protected function formatBytes(int $bytes): string
    {
        if ($bytes >= 1048576) {
            return round($bytes / 1048576, 1) . ' MB';
        }
        if ($bytes >= 1024) {
            return round($bytes / 1024, 1) . ' KB';
        }
        return $bytes . ' B';
    }
}

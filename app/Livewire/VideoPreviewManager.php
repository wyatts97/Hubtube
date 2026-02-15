<?php

namespace App\Livewire;

use App\Models\Setting;
use App\Models\Video;
use App\Services\FfmpegService;
use App\Services\StorageManager;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Component;
use Livewire\WithFileUploads;

class VideoPreviewManager extends Component
{
    use WithFileUploads;

    public int $videoId;
    public ?string $videoUrl = null;
    public ?string $hlsUrl = null;
    public ?string $currentThumbnail = null;
    public array $thumbnails = [];
    public $customThumbnail = null;
    public bool $isCapturing = false;

    public function mount(int $videoId): void
    {
        $this->videoId = $videoId;
        $this->loadVideoData();
    }

    public function loadVideoData(): void
    {
        $video = Video::find($this->videoId);
        if (!$video) return;

        // Use the admin streaming route for Range request support (enables seekbar scrubbing)
        if ($video->video_path && $video->storage_disk === 'public') {
            $this->videoUrl = route('admin.video-stream', ['path' => $video->video_path]);
        } else {
            $this->videoUrl = $video->video_url;
        }
        $this->hlsUrl = $video->hls_playlist_url;
        $this->currentThumbnail = $video->thumbnail;
        $this->thumbnails = $video->getAvailableThumbnails();
    }

    public function selectThumbnail(string $path): void
    {
        $video = Video::find($this->videoId);
        if (!$video) return;

        $video->update(['thumbnail' => $path]);
        $this->currentThumbnail = $path;
        $this->loadVideoData();

        Notification::make()
            ->title('Thumbnail updated')
            ->success()
            ->send();
    }

    public function uploadCustomThumbnail(): void
    {
        $this->validate([
            'customThumbnail' => 'required|image|max:5120',
        ]);

        $video = Video::find($this->videoId);
        if (!$video) return;

        $disk = $video->storage_disk ?? 'public';
        $directory = "videos/{$video->slug}";
        $slugTitle = Str::slug($video->title, '_') ?: 'video';
        $extension = $this->customThumbnail->getClientOriginalExtension() ?: 'jpg';
        $filename = "{$slugTitle}_custom_thumb.{$extension}";

        if (StorageManager::isCloudDisk($disk)) {
            $path = "{$directory}/{$filename}";
            StorageManager::put($path, file_get_contents($this->customThumbnail->getRealPath()), $disk);
        } else {
            $path = $this->customThumbnail->storeAs($directory, $filename, 'public');
        }

        $video->update(['thumbnail' => $path]);
        $this->customThumbnail = null;
        $this->loadVideoData();

        Notification::make()
            ->title('Custom thumbnail uploaded')
            ->success()
            ->send();
    }

    public function captureFrame(float $timestamp): void
    {
        $video = Video::find($this->videoId);
        if (!$video || !$video->video_path) {
            Notification::make()
                ->title('Video file not found')
                ->danger()
                ->send();
            return;
        }

        $this->isCapturing = true;

        try {
            $disk = $video->storage_disk ?? 'public';

            // Check if file is cloud-only (offloaded with local deletion)
            if ($disk !== 'public' && Setting::get('cloud_offloading_delete_local', false)) {
                $localDiskPath = Storage::disk('public')->path($video->video_path);
                if (!file_exists($localDiskPath)) {
                    Notification::make()
                        ->title('Frame capture unavailable')
                        ->body('The original video file has been offloaded to cloud storage and deleted locally. FFmpeg cannot capture frames from remote files. Use the custom thumbnail upload instead.')
                        ->warning()
                        ->persistent()
                        ->send();
                    return;
                }
            }

            $localPath = StorageManager::localPath($video->video_path, $disk);

            if (!$localPath || !file_exists($localPath)) {
                throw new \RuntimeException('Could not access video file locally. The file may have been moved or deleted.');
            }

            $ffmpeg = FfmpegService::ffmpegPath();
            $videoDir = "videos/{$video->slug}";
            $slugTitle = Str::slug($video->title, '_') ?: 'video';
            $outputFilename = "{$slugTitle}_frame_" . intval($timestamp) . '.jpg';

            if ($disk === 'public') {
                $outputPath = Storage::disk('public')->path("{$videoDir}/{$outputFilename}");
            } else {
                $outputPath = storage_path("app/temp/{$outputFilename}");
            }

            $outputDir = dirname($outputPath);
            if (!is_dir($outputDir)) {
                mkdir($outputDir, 0755, true);
            }

            $cmd = sprintf(
                '%s -y -ss %s -i %s -vframes 1 -q:v 2 %s 2>&1',
                $ffmpeg,
                escapeshellarg(number_format($timestamp, 2, '.', '')),
                escapeshellarg($localPath),
                escapeshellarg($outputPath)
            );

            exec($cmd, $output, $exitCode);

            if ($exitCode !== 0 || !file_exists($outputPath)) {
                throw new \RuntimeException('FFmpeg frame capture failed (exit code ' . $exitCode . ')');
            }

            $storagePath = "{$videoDir}/{$outputFilename}";

            // If cloud disk, upload the captured frame
            if (StorageManager::isCloudDisk($disk)) {
                StorageManager::uploadLocalFile($outputPath, $storagePath, $disk);
                @unlink($outputPath);
            }

            $video->update(['thumbnail' => $storagePath]);
            $this->loadVideoData();

            Notification::make()
                ->title('Frame captured and set as thumbnail')
                ->success()
                ->send();
        } catch (\Throwable $e) {
            Log::error('Frame capture failed', [
                'video_id' => $this->videoId,
                'timestamp' => $timestamp,
                'error' => $e->getMessage(),
            ]);

            Notification::make()
                ->title('Frame capture failed')
                ->body($e->getMessage())
                ->danger()
                ->send();
        } finally {
            $this->isCapturing = false;
        }
    }

    public function render()
    {
        return view('livewire.video-preview-manager');
    }
}

<?php

namespace App\Livewire;

use App\Jobs\ProcessVideoJob;
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
    public ?string $currentThumbnailUrl = null;
    public array $thumbnails = [];
    public $customThumbnail = null;
    public $replacementVideo = null;
    public bool $isCapturing = false;
    public bool $isReplacing = false;
    public bool $isPortrait = false;
    public array $stats = [];
    public array $shareUrls = [];

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
        // storage_disk is null or 'public' for locally stored videos
        $disk = $video->storage_disk ?? 'public';
        $streamUrl = null;
        if ($video->video_path && $disk === 'public') {
            $normalizedPath = ltrim(str_replace('\\', '/', $video->video_path), '/');
            $streamUrl = route('admin.video-stream') . '?path=' . rawurlencode($normalizedPath);
            $this->videoUrl = $streamUrl;
        } else {
            $this->videoUrl = $video->video_url;
        }
        $this->hlsUrl = $video->hls_playlist_url;
        $this->currentThumbnail = $video->thumbnail;
        $this->currentThumbnailUrl = $video->thumbnail_url ?? null;
        $this->thumbnails = $video->getAvailableThumbnails();
        $this->isPortrait = (bool) ($video->is_portrait ?? false);

        $this->stats = [
            'views'    => (int) $video->views_count,
            'likes'    => (int) $video->likes_count,
            'duration' => $video->formatted_duration ?: '—',
            'size'     => $video->size ? number_format($video->size / 1048576, 1) . ' MB' : '—',
            'disk'     => $disk,
            'status'   => $video->status,
        ];

        $this->shareUrls = [
            'public' => $video->slug ? url('/' . $video->slug) : null,
            'stream' => $streamUrl ?: $video->video_url,
            'source' => $video->video_path,
        ];
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

    public function replaceSourceVideo(): void
    {
        $this->validate([
            'replacementVideo' => 'required|file|mimetypes:video/mp4,video/quicktime,video/x-msvideo,video/x-matroska,video/webm|max:5242880',
        ]);

        $video = Video::find($this->videoId);
        if (!$video) return;

        $this->isReplacing = true;

        try {
            $directory = 'videos/admin-uploads';
            $extension = $this->replacementVideo->getClientOriginalExtension() ?: 'mp4';
            $filename = Str::random(24) . '.' . $extension;
            $path = $this->replacementVideo->storeAs($directory, $filename, 'public');

            $video->update([
                'video_path'         => $path,
                'storage_disk'       => 'public',
                'status'             => 'pending',
                'hls_playlist_url'   => null,
                'failure_reason'     => null,
            ]);

            ProcessVideoJob::dispatch($video)->onQueue('video-processing');

            $this->replacementVideo = null;
            $this->loadVideoData();

            Notification::make()
                ->title('Source video replaced')
                ->body('Re-processing has been queued. The new file will be transcoded shortly.')
                ->success()
                ->send();
        } catch (\Throwable $e) {
            Log::error('Replace source video failed', [
                'video_id' => $this->videoId,
                'error'    => $e->getMessage(),
            ]);

            Notification::make()
                ->title('Replace failed')
                ->body($e->getMessage())
                ->danger()
                ->send();
        } finally {
            $this->isReplacing = false;
        }
    }

    public function render()
    {
        return view('livewire.video-preview-manager');
    }
}

<?php

namespace App\Jobs;

use App\Events\VideoProcessed;
use App\Models\Setting;
use App\Models\Video;
use App\Services\VideoService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ProcessVideoJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 3600;

    public function __construct(
        public Video $video
    ) {}

    public function handle(VideoService $videoService): void
    {
        // Check if FFmpeg processing is enabled
        if (!Setting::get('ffmpeg_enabled', true)) {
            Log::info('FFmpeg processing disabled, serving original file', [
                'video_id' => $this->video->id,
            ]);
            
            $this->markAsProcessedWithOriginal();
            return;
        }

        // Check if FFmpeg is available
        if (!$this->isFFmpegAvailable()) {
            Log::warning('FFmpeg not available, skipping video processing', [
                'video_id' => $this->video->id,
            ]);
            
            $this->markAsProcessedWithOriginal();
            return;
        }

        $this->video->update([
            'status' => 'processing',
            'processing_started_at' => now(),
        ]);

        try {
            $qualities = $this->processVideo();
            
            $videoService->markAsProcessed($this->video, $qualities);
            $videoService->publish($this->video);

            event(new VideoProcessed($this->video));

        } catch (\Exception $e) {
            Log::error('Video processing failed', [
                'video_id' => $this->video->id,
                'error' => $e->getMessage(),
            ]);

            $this->markAsProcessedWithOriginal();
        }
    }

    protected function markAsProcessedWithOriginal(): void
    {
        $this->video->update([
            'status' => 'processed',
            'qualities_available' => ['original'],
            'processing_completed_at' => now(),
            'published_at' => now(),
            'is_approved' => true,
        ]);
        
        event(new VideoProcessed($this->video));
    }

    protected function isFFmpegAvailable(): bool
    {
        $ffmpegPath = Setting::get('ffmpeg_path', '');
        $ffmpeg = !empty($ffmpegPath) ? $ffmpegPath : '/usr/bin/ffmpeg';
        
        // Check if FFmpeg binary exists and is executable
        if (file_exists($ffmpeg) && is_executable($ffmpeg)) {
            return true;
        }
        
        // Try to find ffmpeg in PATH
        $output = shell_exec('which ffmpeg 2>/dev/null');
        return !empty(trim($output ?? ''));
    }

    protected function getFFmpegPath(): string
    {
        $ffmpegPath = Setting::get('ffmpeg_path', '');
        if (!empty($ffmpegPath) && file_exists($ffmpegPath)) {
            return $ffmpegPath;
        }
        
        $output = trim(shell_exec('which ffmpeg 2>/dev/null') ?? '');
        return !empty($output) ? $output : '/usr/bin/ffmpeg';
    }

    protected function getFFprobePath(): string
    {
        $ffprobePath = Setting::get('ffprobe_path', '');
        if (!empty($ffprobePath) && file_exists($ffprobePath)) {
            return $ffprobePath;
        }
        
        $output = trim(shell_exec('which ffprobe 2>/dev/null') ?? '');
        return !empty($output) ? $output : '/usr/bin/ffprobe';
    }

    protected function getQualityPreset(): string
    {
        return Setting::get('video_quality_preset', 'medium');
    }

    protected function processVideo(): array
    {
        $inputPath = Storage::disk('public')->path($this->video->video_path);
        $outputDir = dirname($inputPath) . '/processed';

        if (!file_exists($outputDir)) {
            mkdir($outputDir, 0755, true);
        }

        $qualities = [];
        $allQualities = [
            '240p' => ['width' => 426, 'height' => 240, 'bitrate' => '400k'],
            '360p' => ['width' => 640, 'height' => 360, 'bitrate' => '800k'],
            '480p' => ['width' => 854, 'height' => 480, 'bitrate' => '1400k'],
            '720p' => ['width' => 1280, 'height' => 720, 'bitrate' => '2800k'],
            '1080p' => ['width' => 1920, 'height' => 1080, 'bitrate' => '5000k'],
        ];

        $videoInfo = $this->getVideoInfo($inputPath);
        $this->video->update(['duration' => $videoInfo['duration']]);

        $this->generateThumbnails($inputPath, $outputDir);
        
        // Generate animated preview if enabled
        if (Setting::get('animated_previews_enabled', true)) {
            $this->generateAnimatedPreview($inputPath, $outputDir, $videoInfo['duration']);
        }

        // Generate scrubber preview sprite sheet + VTT for Plyr
        $this->generateScrubberPreviews($inputPath, $outputDir, $videoInfo['duration']);

        // Check if multi-resolution transcoding is enabled
        $multiResolutionEnabled = Setting::get('multi_resolution_enabled', true);
        
        if ($multiResolutionEnabled) {
            // Get enabled resolutions from settings
            $enabledResolutions = Setting::get('enabled_resolutions', ['360p', '480p', '720p']);
            
            // Ensure it's an array
            if (is_string($enabledResolutions)) {
                $enabledResolutions = json_decode($enabledResolutions, true) ?? ['360p', '480p', '720p'];
            }
            
            foreach ($allQualities as $quality => $settings) {
                // Only transcode if: resolution is enabled AND source video is large enough
                if (in_array($quality, $enabledResolutions) && $videoInfo['height'] >= $settings['height']) {
                    $this->transcodeToQuality($inputPath, $outputDir, $quality, $settings);
                    $qualities[] = $quality;
                }
            }

            // Generate HLS playlist if enabled and we have qualities
            if (!empty($qualities) && Setting::get('generate_hls', true)) {
                $this->generateHlsPlaylist($outputDir, $qualities);
            }
        }
        
        // Always add 'original' to indicate the original file is available
        $qualities[] = 'original';

        return $qualities;
    }

    protected function getVideoInfo(string $path): array
    {
        $ffprobe = $this->getFFprobePath();
        
        if (!file_exists($path)) {
            throw new \RuntimeException("Video file not found: {$path}");
        }

        $cmd = "{$ffprobe} -v quiet -print_format json -show_format -show_streams " . escapeshellarg($path);
        
        $output = shell_exec($cmd);
        
        if (empty($output)) {
            throw new \RuntimeException('FFprobe returned empty output. Check if FFprobe is installed.');
        }

        $info = json_decode($output, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException('Failed to parse FFprobe output: ' . json_last_error_msg());
        }

        $duration = 0;
        $width = 0;
        $height = 0;

        if (isset($info['format']['duration'])) {
            $duration = (int) $info['format']['duration'];
        }

        foreach ($info['streams'] ?? [] as $stream) {
            if (($stream['codec_type'] ?? '') === 'video') {
                $width = $stream['width'] ?? 0;
                $height = $stream['height'] ?? 0;
                break;
            }
        }

        return [
            'duration' => $duration,
            'width' => $width,
            'height' => $height,
        ];
    }

    protected function generateThumbnails(string $inputPath, string $outputDir): void
    {
        $ffmpeg = $this->getFFmpegPath();
        $duration = $this->video->duration;
        $count = config('hubtube.video.thumbnail_count', 4);

        for ($i = 0; $i < $count; $i++) {
            $time = (int) ($duration / ($count + 1) * ($i + 1));
            $output = "{$outputDir}/thumb_{$i}.jpg";
            
            $cmd = sprintf(
                '%s -y -ss %d -i %s -vframes 1 -q:v 2 %s 2>&1',
                $ffmpeg,
                $time,
                escapeshellarg($inputPath),
                escapeshellarg($output)
            );
            shell_exec($cmd);
        }

        $this->video->update([
            'thumbnail' => str_replace(Storage::disk('public')->path(''), '', "{$outputDir}/thumb_0.jpg"),
        ]);
    }

    protected function generateAnimatedPreview(string $inputPath, string $outputDir, int $duration): void
    {
        $ffmpeg = $this->getFFmpegPath();
        $output = "{$outputDir}/preview.webp";
        
        // Calculate preview parameters
        $previewDuration = min(6, max(3, (int)($duration * 0.1))); // 3-6 seconds based on video length
        $startTime = max(0, (int)($duration * 0.1)); // Start at 10% of video
        
        // If video is very short, start from beginning
        if ($duration < 10) {
            $startTime = 0;
            $previewDuration = min(3, $duration);
        }
        
        // Generate animated WebP with reduced size for performance
        // Scale to 320px width, 10fps, good quality
        $cmd = sprintf(
            '%s -y -ss %d -t %d -i %s -vf "fps=10,scale=320:-1:flags=lanczos" -c:v libwebp -lossless 0 -compression_level 4 -q:v 70 -loop 0 -preset default -an -vsync 0 %s 2>&1',
            $ffmpeg,
            $startTime,
            $previewDuration,
            escapeshellarg($inputPath),
            escapeshellarg($output)
        );
        
        Log::info('Generating animated preview', [
            'video_id' => $this->video->id,
            'duration' => $duration,
            'preview_start' => $startTime,
            'preview_duration' => $previewDuration,
        ]);
        
        $result = shell_exec($cmd);
        
        // Check if file was created successfully
        if (file_exists($output) && filesize($output) > 0) {
            $previewPath = str_replace(Storage::disk('public')->path(''), '', $output);
            $this->video->update(['preview_path' => $previewPath]);
            
            Log::info('Animated preview generated successfully', [
                'video_id' => $this->video->id,
                'preview_path' => $previewPath,
                'file_size' => filesize($output),
            ]);
        } else {
            Log::warning('Failed to generate animated preview', [
                'video_id' => $this->video->id,
                'output' => $result,
            ]);
        }
    }

    protected function generateScrubberPreviews(string $inputPath, string $outputDir, int $duration): void
    {
        if ($duration < 5) return;

        $ffmpeg = $this->getFFmpegPath();
        $spriteDir = "{$outputDir}/sprites";

        if (!is_dir($spriteDir)) {
            mkdir($spriteDir, 0755, true);
        }

        // Generate one thumbnail every 5 seconds, 160x90px
        $interval = max(5, (int) ($duration / 100)); // At most ~100 frames
        $thumbWidth = 160;
        $thumbHeight = 90;

        $cmd = sprintf(
            '%s -y -i %s -vf "fps=1/%d,scale=%d:%d" -q:v 5 %s/sprite_%%04d.jpg 2>&1',
            $ffmpeg,
            escapeshellarg($inputPath),
            $interval,
            $thumbWidth,
            $thumbHeight,
            escapeshellarg($spriteDir)
        );

        shell_exec($cmd);

        // Count generated frames
        $frames = glob("{$spriteDir}/sprite_*.jpg");
        if (empty($frames)) {
            Log::warning('Failed to generate scrubber preview sprites', ['video_id' => $this->video->id]);
            return;
        }

        sort($frames);

        // Generate VTT file referencing individual thumbnails
        $storagePath = Storage::disk('public')->path('');
        $vttContent = "WEBVTT\n\n";

        foreach ($frames as $i => $frame) {
            $startSec = $i * $interval;
            $endSec = min(($i + 1) * $interval, $duration);

            $startTime = sprintf('%02d:%02d:%02d.000', intdiv($startSec, 3600), intdiv($startSec % 3600, 60), $startSec % 60);
            $endTime = sprintf('%02d:%02d:%02d.000', intdiv($endSec, 3600), intdiv($endSec % 3600, 60), $endSec % 60);

            $relativePath = str_replace($storagePath, '/storage/', $frame);
            $relativePath = str_replace('\\', '/', $relativePath);

            $vttContent .= "{$startTime} --> {$endTime}\n{$relativePath}\n\n";
        }

        $vttPath = "{$outputDir}/scrubber.vtt";
        file_put_contents($vttPath, $vttContent);

        $vttRelative = str_replace($storagePath, '', $vttPath);
        $this->video->update(['scrubber_vtt_path' => $vttRelative]);

        Log::info('Scrubber preview sprites generated', [
            'video_id' => $this->video->id,
            'frames' => count($frames),
            'interval' => $interval,
        ]);
    }

    protected function transcodeToQuality(string $inputPath, string $outputDir, string $quality, array $settings): void
    {
        $ffmpeg = $this->getFFmpegPath();
        $threads = config('hubtube.ffmpeg.threads', 4);
        $preset = $this->getQualityPreset();
        
        $output = "{$outputDir}/{$quality}.mp4";
        
        // Check if watermark is enabled and valid
        $watermarkInput = $this->getWatermarkInputs();
        $hasWatermark = !empty($watermarkInput);
        
        if ($hasWatermark) {
            // Use -filter_complex for watermark overlay (multiple inputs)
            $filterComplex = $this->buildWatermarkFilterComplex($settings['width'], $settings['height']);
            
            $cmd = sprintf(
                '%s -y -i %s %s -filter_complex "%s" -map "[outv]" -map 0:a? -c:v libx264 -preset %s -b:v %s -c:a aac -b:a 128k -threads %d -movflags +faststart %s 2>&1',
                $ffmpeg,
                escapeshellarg($inputPath),
                $watermarkInput,
                $filterComplex,
                $preset,
                $settings['bitrate'],
                $threads,
                escapeshellarg($output)
            );
        } else {
            // Scale preserving aspect ratio: fit within target height, auto-calculate width (divisible by 2)
            $cmd = sprintf(
                '%s -y -i %s -vf "scale=-2:%d" -c:v libx264 -preset %s -b:v %s -c:a aac -b:a 128k -threads %d -movflags +faststart %s 2>&1',
                $ffmpeg,
                escapeshellarg($inputPath),
                $settings['height'],
                $preset,
                $settings['bitrate'],
                $threads,
                escapeshellarg($output)
            );
        }

        Log::info('Transcoding video', ['quality' => $quality, 'command' => $cmd]);
        $result = shell_exec($cmd);
        
        // Check if output file was created successfully
        if (!file_exists($output) || filesize($output) === 0) {
            Log::error('Transcoding failed', [
                'quality' => $quality,
                'output' => $result,
            ]);
        }
    }

    protected function getWatermarkInputs(): string
    {
        if (!Setting::get('watermark_enabled', false)) {
            return '';
        }
        
        $watermarkImage = Setting::get('watermark_image', '');
        if (empty($watermarkImage)) {
            return '';
        }
        
        $watermarkPath = Storage::disk('public')->path($watermarkImage);
        if (!file_exists($watermarkPath)) {
            return '';
        }
        
        return '-i ' . escapeshellarg($watermarkPath);
    }

    protected function buildWatermarkFilterComplex(int $videoWidth, int $videoHeight): string
    {
        $position = Setting::get('watermark_position', 'bottom-right');
        $opacity = Setting::get('watermark_opacity', 70) / 100;
        $scale = Setting::get('watermark_scale', 15) / 100;
        $padding = Setting::get('watermark_padding', 10);
        
        // Calculate watermark width based on video width
        $wmWidth = (int) ($videoWidth * $scale);
        
        // Position mapping for FFmpeg overlay filter
        $positions = [
            'top-left' => "x={$padding}:y={$padding}",
            'top-center' => "x=(W-w)/2:y={$padding}",
            'top-right' => "x=W-w-{$padding}:y={$padding}",
            'center-left' => "x={$padding}:y=(H-h)/2",
            'center' => "x=(W-w)/2:y=(H-h)/2",
            'center-right' => "x=W-w-{$padding}:y=(H-h)/2",
            'bottom-left' => "x={$padding}:y=H-h-{$padding}",
            'bottom-center' => "x=(W-w)/2:y=H-h-{$padding}",
            'bottom-right' => "x=W-w-{$padding}:y=H-h-{$padding}",
        ];
        
        $pos = $positions[$position] ?? $positions['bottom-right'];
        
        // Build the complex filter:
        // 1. Scale the video to target resolution
        // 2. Scale the watermark and apply opacity
        // 3. Overlay watermark on video
        return "[0:v]scale=-2:{$videoHeight}[scaled];[1:v]scale={$wmWidth}:-1,format=rgba,colorchannelmixer=aa={$opacity}[wm];[scaled][wm]overlay={$pos}[outv]";
    }

    protected function generateHlsPlaylist(string $outputDir, array $qualities): void
    {
        $ffmpeg = $this->getFFmpegPath();

        foreach ($qualities as $quality) {
            $input = "{$outputDir}/{$quality}.mp4";
            $hlsDir = "{$outputDir}/hls/{$quality}";
            
            if (!file_exists($hlsDir)) {
                mkdir($hlsDir, 0755, true);
            }

            $cmd = "{$ffmpeg} -y -i \"{$input}\" " .
                "-c:v copy -c:a copy " .
                "-hls_time 10 -hls_list_size 0 " .
                "-hls_segment_filename \"{$hlsDir}/segment_%03d.ts\" " .
                "\"{$hlsDir}/playlist.m3u8\"";

            shell_exec($cmd);
        }

        $this->generateMasterPlaylist($outputDir, $qualities);
    }

    protected function generateMasterPlaylist(string $outputDir, array $qualities): void
    {
        $bandwidths = [
            '240p' => 400000,
            '360p' => 800000,
            '480p' => 1400000,
            '720p' => 2800000,
            '1080p' => 5000000,
        ];

        $resolutions = [
            '240p' => '426x240',
            '360p' => '640x360',
            '480p' => '854x480',
            '720p' => '1280x720',
            '1080p' => '1920x1080',
        ];

        $content = "#EXTM3U\n#EXT-X-VERSION:3\n";

        foreach ($qualities as $quality) {
            $bandwidth = $bandwidths[$quality] ?? 1000000;
            $resolution = $resolutions[$quality] ?? '1280x720';
            
            $content .= "#EXT-X-STREAM-INF:BANDWIDTH={$bandwidth},RESOLUTION={$resolution}\n";
            $content .= "hls/{$quality}/playlist.m3u8\n";
        }

        file_put_contents("{$outputDir}/master.m3u8", $content);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('Video processing job failed permanently', [
            'video_id' => $this->video->id,
            'error' => $exception->getMessage(),
        ]);

        $this->video->update(['status' => 'failed']);
    }
}

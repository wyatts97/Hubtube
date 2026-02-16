<?php

namespace App\Jobs;

use App\Events\VideoProcessed;
use App\Models\Setting;
use App\Models\Video;
use App\Services\FfmpegService;
use App\Services\StorageManager;
use App\Services\VideoService;
use App\Services\WatermarkService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\Models\Notification;

class ProcessVideoJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 3600;

    /**
     * Unique ID for this job — prevents duplicate processing of the same video.
     */
    public function uniqueId(): string
    {
        return 'process-video-' . $this->video->id;
    }

    /**
     * Keep the unique lock for the full timeout duration.
     */
    public int $uniqueFor = 3600;

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

            // Check if cloud offloading is enabled in admin settings
            if (Setting::get('cloud_offloading_enabled', false)) {
                $targetDisk = StorageManager::getActiveDiskName();
                if (StorageManager::isCloudDisk($targetDisk)) {
                    $this->uploadToCloudStorage($targetDisk);
                }
            }

            $videoService->markAsProcessed($this->video, $qualities);

            $this->notifyOnce();

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
        // Check if cloud offloading is enabled in admin settings
        if (Setting::get('cloud_offloading_enabled', false)) {
            $targetDisk = StorageManager::getActiveDiskName();
            if (StorageManager::isCloudDisk($targetDisk)) {
                $this->uploadToCloudStorage($targetDisk);
            }
        }

        $videoService = app(VideoService::class);
        $videoService->markAsProcessed($this->video, ['original']);

        $this->notifyOnce();
    }

    protected function notifyOnce(): void
    {
        // Prevent duplicate notifications on job retries
        $exists = Notification::where('user_id', $this->video->user_id)
            ->where('type', 'video_processed')
            ->where('data->video_id', $this->video->id)
            ->exists();

        if (!$exists) {
            event(new VideoProcessed($this->video));
        }
    }

    protected function isFFmpegAvailable(): bool
    {
        return FfmpegService::isAvailable();
    }

    protected function getFFmpegPath(): string
    {
        return FfmpegService::ffmpegPath();
    }

    protected function getFFprobePath(): string
    {
        return FfmpegService::ffprobePath();
    }

    protected function getQualityPreset(): string
    {
        // Force ultrafast for Bunny Stream migration videos — processing ~1000 videos
        // needs maximum speed, not maximum quality. Ignore admin-configured preset.
        if ($this->video->source_site === 'bunnystream') {
            return 'ultrafast';
        }

        return Setting::get('video_quality_preset', 'veryfast');
    }

    protected function getRateControl(): string
    {
        return Setting::get('ffmpeg_rate_control', 'crf');
    }

    protected function getCrf(): int
    {
        return (int) Setting::get('ffmpeg_crf', 22);
    }

    protected function getPixFmt(): string
    {
        return Setting::get('ffmpeg_pix_fmt', 'yuv420p');
    }

    protected function getMp4ExtraArgs(): string
    {
        return trim((string) Setting::get('ffmpeg_mp4_extra_args', ''));
    }

    protected function getHlsExtraArgs(): string
    {
        return trim((string) Setting::get('ffmpeg_hls_extra_args', ''));
    }

    protected function getHlsPlaylistType(): string
    {
        return trim((string) Setting::get('ffmpeg_hls_playlist_type', 'vod')) ?: 'vod';
    }

    protected function getHlsFlags(): string
    {
        return trim((string) Setting::get('ffmpeg_hls_flags', 'independent_segments')) ?: 'independent_segments';
    }

    protected function getMp4EncodeArgs(?string $bitrate = null): string
    {
        $preset = $this->getQualityPreset();
        $rateControl = $this->getRateControl();
        $crf = $this->getCrf();
        $pixFmt = $this->getPixFmt();
        $audioBitrate = Setting::get('audio_bitrate', '128k');
        $threads = (int) Setting::get('ffmpeg_threads', 4);
        $extraArgs = $this->getMp4ExtraArgs();

        $videoRate = ($rateControl === 'bitrate' && $bitrate)
            ? "-b:v {$bitrate}"
            : "-crf {$crf}";

        return trim(sprintf(
            '-c:v libx264 -preset %s %s -pix_fmt %s -c:a aac -b:a %s -threads %d -movflags +faststart %s',
            $preset,
            $videoRate,
            $pixFmt,
            $audioBitrate,
            $threads,
            $extraArgs
        ));
    }

    protected function getHlsEncodeArgs(): string
    {
        $preset = $this->getQualityPreset();
        $crf = $this->getCrf();
        $audioBitrate = Setting::get('audio_bitrate', '128k');
        $threads = (int) Setting::get('ffmpeg_threads', 4);
        $hlsTime = (int) Setting::get('hls_segment_duration', 6);
        $playlistType = $this->getHlsPlaylistType();
        $flags = $this->getHlsFlags();
        $extraArgs = $this->getHlsExtraArgs();

        return trim(sprintf(
            '-c:v libx264 -preset %s -crf %d -c:a aac -b:a %s -threads %d -f hls -hls_time %d -hls_playlist_type %s -hls_flags %s %s',
            $preset,
            $crf,
            $audioBitrate,
            $threads,
            $hlsTime,
            $playlistType,
            $flags,
            $extraArgs
        ));
    }

    protected function hasImageWatermark(): bool
    {
        if (!Setting::get('watermark_enabled', false)) {
            return false;
        }

        $watermarkImage = Setting::get('watermark_image', '');
        if (empty($watermarkImage)) {
            return false;
        }

        $watermarkPath = Storage::disk('public')->path($watermarkImage);
        return file_exists($watermarkPath);
    }

    protected function hasTextWatermark(): bool
    {
        if (!Setting::get('watermark_text_enabled', false)) {
            return false;
        }

        return trim((string) Setting::get('watermark_text', '')) !== '';
    }

    /**
     * Apply -movflags +faststart to the original uploaded file so it is
     * seekable in browsers even when served without transcoding.
     */
    protected function applyFaststartToOriginal(string $inputPath): void
    {
        $ffmpeg = $this->getFFmpegPath();
        $tempOutput = $inputPath . '.faststart.mp4';

        $cmd = sprintf(
            '%s -y -i %s -c copy -movflags +faststart %s 2>&1',
            $ffmpeg,
            escapeshellarg($inputPath),
            escapeshellarg($tempOutput)
        );

        [$exitCode, $output] = $this->runCommand($cmd);

        if ($exitCode === 0 && file_exists($tempOutput) && filesize($tempOutput) > 0) {
            unlink($inputPath);
            rename($tempOutput, $inputPath);
            Log::info('Applied faststart to original video', ['video_id' => $this->video->id]);
        } else {
            if (file_exists($tempOutput)) {
                unlink($tempOutput);
            }
            Log::warning('Failed to apply faststart to original video', [
                'video_id' => $this->video->id,
                'exit_code' => $exitCode,
                'output' => substr($output, 0, 300),
            ]);
        }
    }

    protected function getVideoDirectory(): string
    {
        return "videos/{$this->video->slug}";
    }

    protected function getSluggedTitle(): string
    {
        return Str::slug($this->video->title, '_') ?: 'video';
    }

    protected function processVideo(): array
    {
        $inputPath = Storage::disk('public')->path($this->video->video_path);
        $videoDir = Storage::disk('public')->path($this->getVideoDirectory());
        $outputDir = $videoDir . '/processed';

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

        // Ensure original file is browser-seekable (moov atom at start).
        // Skip on retry — faststart is idempotent but wastes time re-muxing.
        $faststartMarker = "{$videoDir}/.faststart_done";
        if (!file_exists($faststartMarker)) {
            $this->applyFaststartToOriginal($inputPath);
            file_put_contents($faststartMarker, now()->toIso8601String());
        }

        // Skip thumbnail generation on retry if thumbnails already exist
        $slugTitle = $this->getSluggedTitle();
        if (!file_exists("{$videoDir}/{$slugTitle}_thumb_0.jpg")) {
            $this->generateThumbnails($inputPath, $videoDir);
        }
        
        // Generate animated preview if enabled (skip if already exists)
        if (Setting::get('animated_previews_enabled', true)) {
            $previewFile = "{$videoDir}/{$slugTitle}_preview.webp";
            if (!file_exists($previewFile) || filesize($previewFile) === 0) {
                $this->generateAnimatedPreview($inputPath, $videoDir, $videoInfo['duration']);
            }
        }

        // Generate scrubber preview sprite sheet + VTT for seekbar thumbnails (skip if VTT exists)
        $vttFile = "{$videoDir}/scrubber.vtt";
        if (!file_exists($vttFile)) {
            $this->generateScrubberPreviews($inputPath, $videoDir, $videoInfo['duration']);
        }

        // If watermarking is enabled, apply watermark to the original file ONCE.
        // All lower-quality encodes will use this watermarked file as input,
        // so the watermark scales naturally with the video and FFmpeg only runs
        // the watermark filter once instead of per-quality.
        $hasWatermark = $this->hasImageWatermark() || $this->hasTextWatermark();
        $transcodeInput = $inputPath;
        $watermarkedPath = null;

        if ($hasWatermark) {
            $watermarkedPath = $this->watermarkOriginal($inputPath, $outputDir, $videoInfo);
            if ($watermarkedPath) {
                $transcodeInput = $watermarkedPath;
            }
        }

        // Check if multi-resolution transcoding is enabled
        $multiResolutionEnabled = Setting::get('multi_resolution_enabled', true);
        
        if ($multiResolutionEnabled) {
            // Get enabled resolutions from settings
            $enabledResolutions = Setting::get('enabled_resolutions', ['360p', '480p', '720p']);
            
            // Ensure it's an array
            if (is_string($enabledResolutions)) {
                $enabledResolutions = json_decode($enabledResolutions, true) ?? ['360p', '480p', '720p'];
            }
            
            // Build list of qualities to transcode — only encode to resolutions
            // strictly LOWER than the source. The original quality is always
            // served via the watermarked/optimized original file itself.
            $targetQualities = [];
            $skippedQualities = [];
            foreach ($allQualities as $quality => $settings) {
                if (!in_array($quality, $enabledResolutions)) continue;
                if ($videoInfo['height'] > $settings['height']) {
                    $targetQualities[$quality] = $settings;
                } else {
                    $skippedQualities[] = "{$quality} (source {$videoInfo['height']}p not above {$settings['height']}p)";
                }
            }

            Log::info('Quality selection', [
                'video_id' => $this->video->id,
                'source_resolution' => "{$videoInfo['width']}x{$videoInfo['height']}",
                'will_transcode' => array_keys($targetQualities),
                'skipped' => $skippedQualities,
            ]);

            if (!empty($targetQualities)) {
                $generateHls = (bool) Setting::get('generate_hls', true);
                $qualities = $this->transcodeAllQualities($transcodeInput, $outputDir, $targetQualities, $generateHls);
            }
        }
        
        // Replace the raw uploaded file with the watermarked/optimized version.
        // This way video_path always serves the best quality with watermark.
        // The raw upload is no longer needed — only the watermarked version is served.
        if ($hasWatermark && $watermarkedPath && file_exists($watermarkedPath)) {
            if (file_exists($inputPath)) {
                unlink($inputPath);
                Log::info('Deleted raw uploaded file', ['path' => $inputPath]);
            }
            rename($watermarkedPath, $inputPath);
            Log::info('Replaced original with watermarked version', ['path' => $inputPath]);
        }

        // Always add 'original' to indicate the original (now watermarked) file is available
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
        
        [$exitCode, $output] = $this->runCommand($cmd);
        
        if ($exitCode !== 0 || empty($output)) {
            throw new \RuntimeException("FFprobe failed (exit code {$exitCode}): " . substr($output, 0, 500));
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

    protected function generateThumbnails(string $inputPath, string $videoDir): void
    {
        $ffmpeg = $this->getFFmpegPath();
        $duration = $this->video->duration;
        $count = (int) Setting::get('thumbnail_count', 4);
        $slugTitle = $this->getSluggedTitle();

        for ($i = 0; $i < $count; $i++) {
            $time = (int) ($duration / ($count + 1) * ($i + 1));
            $output = "{$videoDir}/{$slugTitle}_thumb_{$i}.jpg";
            
            $cmd = sprintf(
                '%s -y -ss %d -i %s -vframes 1 -q:v 2 %s 2>&1',
                $ffmpeg,
                $time,
                escapeshellarg($inputPath),
                escapeshellarg($output)
            );
            [$exitCode, $cmdOutput] = $this->runCommand($cmd);
            if ($exitCode !== 0) {
                Log::warning('Thumbnail generation failed', ['index' => $i, 'exit_code' => $exitCode, 'output' => substr($cmdOutput, 0, 300)]);
            }
        }

        $storagePath = Storage::disk('public')->path('');
        $this->video->update([
            'thumbnail' => str_replace($storagePath, '', "{$videoDir}/{$slugTitle}_thumb_0.jpg"),
        ]);
    }

    protected function generateAnimatedPreview(string $inputPath, string $videoDir, int $duration): void
    {
        $ffmpeg = $this->getFFmpegPath();
        $slugTitle = $this->getSluggedTitle();
        $output = "{$videoDir}/{$slugTitle}_preview.webp";
        
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
        
        [$exitCode, $result] = $this->runCommand($cmd);
        
        // Check if file was created successfully
        if ($exitCode === 0 && file_exists($output) && filesize($output) > 0) {
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

    protected function generateScrubberPreviews(string $inputPath, string $videoDir, int $duration): void
    {
        if ($duration < 5) return;

        $ffmpeg = $this->getFFmpegPath();
        $spriteDir = "{$videoDir}/sprites";

        if (!is_dir($spriteDir)) {
            mkdir($spriteDir, 0755, true);
        }

        // Generate one thumbnail every 5 seconds, width=200 with aspect ratio preserved
        $interval = max(5, (int) ($duration / 100)); // At most ~100 frames
        $thumbWidth = 200;

        $cmd = sprintf(
            '%s -y -i %s -vf "fps=1/%d,scale=%d:-2" -q:v 3 %s/sprite_%%04d.jpg 2>&1',
            $ffmpeg,
            escapeshellarg($inputPath),
            $interval,
            $thumbWidth,
            escapeshellarg($spriteDir)
        );

        [$exitCode, $cmdOutput] = $this->runCommand($cmd);
        if ($exitCode !== 0) {
            Log::warning('Scrubber sprite generation failed', ['exit_code' => $exitCode, 'output' => substr($cmdOutput, 0, 300)]);
        }

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

            // Use paths relative to the VTT file so cloud storage/CDN URLs resolve correctly.
            $relativePath = 'sprites/' . basename($frame);
            $relativePath = str_replace('\\', '/', $relativePath);

            $vttContent .= "{$startTime} --> {$endTime}\n{$relativePath}\n\n";
        }

        $vttPath = "{$videoDir}/scrubber.vtt";
        file_put_contents($vttPath, $vttContent);

        $vttRelative = str_replace($storagePath, '', $vttPath);
        $this->video->update(['scrubber_vtt_path' => $vttRelative]);

        Log::info('Scrubber preview sprites generated', [
            'video_id' => $this->video->id,
            'frames' => count($frames),
            'interval' => $interval,
        ]);
    }

    /**
     * Apply watermark (image + text) to the original video at its native resolution.
     * Returns the path to the watermarked intermediate file, or null on failure.
     * This file is then used as input for all lower-quality encodes, so the
     * watermark scales naturally with the video — no per-quality font sizing needed.
     */
    protected function watermarkOriginal(string $inputPath, string $outputDir, array $videoInfo): ?string
    {
        $ffmpeg = $this->getFFmpegPath();
        $output = "{$outputDir}/watermarked_source.mp4";

        // Skip if already done (job retry)
        if (file_exists($output) && filesize($output) > 10240) {
            Log::info('Watermarked source already exists, skipping', ['size' => filesize($output)]);
            return $output;
        }

        $videoWidth = $videoInfo['width'] ?: 1920;
        $videoHeight = $videoInfo['height'] ?: 1080;

        $watermarkInput = $this->getWatermarkInputs();
        $filterComplex = $this->buildWatermarkFilterComplex($videoWidth, $videoHeight);

        // Encode at high quality (CRF 18) to preserve detail for downstream encodes.
        // Using faststart so the intermediate is seekable if needed.
        $cmd = sprintf(
            '%s -y -i %s %s -filter_complex "%s" -map "[outv]" -map 0:a? -c:v libx264 -crf 18 -preset fast -c:a copy -movflags +faststart %s 2>&1',
            $ffmpeg,
            escapeshellarg($inputPath),
            $watermarkInput,
            $filterComplex,
            escapeshellarg($output)
        );

        Log::info('Applying watermark to original', [
            'video_id' => $this->video->id,
            'resolution' => "{$videoWidth}x{$videoHeight}",
        ]);

        [$exitCode, $result] = $this->runCommand($cmd);

        if ($exitCode !== 0 || !file_exists($output) || filesize($output) < 10240) {
            Log::error('Watermarking original failed, will transcode without watermark', [
                'exit_code' => $exitCode,
                'output' => substr($result, 0, 500),
            ]);
            // Clean up partial file
            if (file_exists($output)) {
                unlink($output);
            }
            return null;
        }

        Log::info('Watermarked original created', ['size' => filesize($output)]);
        return $output;
    }

    /**
     * Single-pass multi-output transcoding.
     * Reads the input once and outputs all quality levels simultaneously.
     * Uses -force_key_frames for HLS-compatible keyframe alignment.
     * Input is either the original file or the watermarked intermediate.
     */
    protected function transcodeAllQualities(string $inputPath, string $outputDir, array $targetQualities, bool $generateHls): array
    {
        $ffmpeg = $this->getFFmpegPath();
        $threads = (int) Setting::get('ffmpeg_threads', 4);
        $preset = $this->getQualityPreset();
        $qualities = [];

        // Check if all outputs already exist (job retry)
        $allExist = true;
        foreach ($targetQualities as $quality => $settings) {
            $output = "{$outputDir}/{$quality}.mp4";
            if (!file_exists($output) || filesize($output) < 10240) {
                $allExist = false;
                break;
            }
        }
        if ($allExist) {
            Log::info('All qualities already transcoded, skipping', ['qualities' => array_keys($targetQualities)]);
            $qualities = array_keys($targetQualities);
            if ($generateHls) {
                $this->generateHlsPlaylist($outputDir, $qualities);
            }
            return $qualities;
        }

        // Build single FFmpeg command with multiple outputs.
        // Input is either the original or the watermarked intermediate —
        // either way, just scale down. No per-quality watermark logic needed.
        $cmd = sprintf('%s -y -i %s', $ffmpeg, escapeshellarg($inputPath));

        $outputArgs = [];
        foreach ($targetQualities as $quality => $settings) {
            $output = "{$outputDir}/{$quality}.mp4";

            // Force keyframes every 2 seconds for clean HLS segmentation
            $forceKeyframes = $generateHls ? sprintf(' -force_key_frames %s', escapeshellarg('expr:gte(t,n_forced*2)')) : '';
            $encodeArgs = $this->getMp4EncodeArgs($settings['bitrate']);

            $outputArgs[] = sprintf(
                '-vf %s %s%s %s',
                escapeshellarg("scale=-2:{$settings['height']}"),
                $encodeArgs,
                $forceKeyframes,
                escapeshellarg($output)
            );
        }

        if (!empty($outputArgs)) {
            $cmd .= ' ' . implode(' ', $outputArgs) . ' 2>&1';

            Log::info('Multi-output transcoding', [
                'video_id' => $this->video->id,
                'qualities' => array_keys($targetQualities),
                'threads' => $threads,
                'preset' => $preset,
            ]);

            [$exitCode, $result] = $this->runCommand($cmd);

            // Verify each output
            foreach ($targetQualities as $quality => $settings) {
                $output = "{$outputDir}/{$quality}.mp4";
                if (file_exists($output) && filesize($output) > 10240) {
                    $qualities[] = $quality;
                } else {
                    Log::warning('Multi-output: quality file missing or too small, retrying individually', [
                        'quality' => $quality,
                        'exit_code' => $exitCode,
                    ]);
                    // Fallback: try this quality individually
                    $this->transcodeToQuality($inputPath, $outputDir, $quality, $settings, $threads, $preset);
                    if (file_exists($output) && filesize($output) > 10240) {
                        $qualities[] = $quality;
                    }
                }
            }
        }

        // Generate HLS from the properly-keyframed MP4s
        if ($generateHls && !empty($qualities)) {
            $this->generateHlsPlaylist($outputDir, $qualities);
        }

        return $qualities;
    }

    /**
     * Single-quality transcode fallback (used when multi-output fails for a quality).
     * Input is either the original or the watermarked intermediate — just scale down.
     */
    protected function transcodeToQuality(string $inputPath, string $outputDir, string $quality, array $settings, ?int $threads = null, ?string $preset = null): void
    {
        $ffmpeg = $this->getFFmpegPath();
        $threads = $threads ?? (int) Setting::get('ffmpeg_threads', 4);
        $preset = $preset ?? $this->getQualityPreset();
        
        $output = "{$outputDir}/{$quality}.mp4";

        // Skip if already transcoded (e.g. on job retry)
        if (file_exists($output) && filesize($output) > 10240) {
            Log::info('Skipping already-transcoded quality', ['quality' => $quality, 'size' => filesize($output)]);
            return;
        }
        
        $encodeArgs = $this->getMp4EncodeArgs($settings['bitrate']);

        $cmd = sprintf(
            '%s -y -i %s -vf %s %s -force_key_frames %s %s 2>&1',
            $ffmpeg,
            escapeshellarg($inputPath),
            escapeshellarg("scale=-2:{$settings['height']}"),
            $encodeArgs,
            escapeshellarg('expr:gte(t,n_forced*2)'),
            escapeshellarg($output)
        );

        Log::info('Transcoding video', ['quality' => $quality]);
        [$exitCode, $result] = $this->runCommand($cmd);
        
        if ($exitCode !== 0 || !file_exists($output) || filesize($output) === 0) {
            Log::error('Transcoding failed', [
                'quality' => $quality,
                'exit_code' => $exitCode,
                'output' => substr($result, 0, 500),
            ]);
            throw new \RuntimeException("FFmpeg transcoding failed for {$quality} (exit code {$exitCode})");
        }
    }

    protected function getWatermarkInputs(): string
    {
        if (!$this->hasImageWatermark()) {
            return '';
        }

        $watermarkImage = Setting::get('watermark_image', '');
        $watermarkPath = Storage::disk('public')->path($watermarkImage);
        if (!file_exists($watermarkPath)) {
            return '';
        }

        return '-i ' . escapeshellarg($watermarkPath);
    }

    protected function buildWatermarkFilterComplex(int $videoWidth, int $videoHeight): string
    {
        $filters = [];
        $currentLabel = '0:v';

        if ($this->hasImageWatermark()) {
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

            $filters[] = "[1:v]scale={$wmWidth}:-1,format=rgba,colorchannelmixer=aa={$opacity}[wm]";
            $filters[] = "[{$currentLabel}][wm]overlay={$pos}[wm_out]";
            $currentLabel = 'wm_out';
        }

        $textFilter = $this->buildTextWatermarkFilter($videoWidth, $videoHeight);
        if ($textFilter) {
            $filters[] = "[{$currentLabel}]{$textFilter}[outv]";
        } else {
            $filters[] = "[{$currentLabel}]null[outv]";
        }

        return implode(';', $filters);
    }

    protected function buildTextWatermarkFilter(int $videoWidth, int $videoHeight): ?string
    {
        if (!$this->hasTextWatermark()) {
            return null;
        }

        $text = $this->escapeDrawtextValue((string) Setting::get('watermark_text', ''));
        $font = $this->escapeDrawtextValue((string) Setting::get('watermark_text_font', '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf'));
        $size = (int) Setting::get('watermark_text_size', 24);
        $color = (string) Setting::get('watermark_text_color', 'white');
        $opacity = (int) Setting::get('watermark_text_opacity', 70) / 100;
        $padding = (int) Setting::get('watermark_text_padding', 10);
        $position = (string) Setting::get('watermark_text_position', 'top');

        $scrollEnabled = (bool) Setting::get('watermark_text_scroll_enabled', false);
        $scrollSpeed = (string) Setting::get('watermark_text_scroll_speed', 'medium');
        $scrollInterval = (int) Setting::get('watermark_text_scroll_interval', 0);
        $scrollStartDelay = (int) Setting::get('watermark_text_scroll_start_delay', 0);

        // Responsive font size: scale relative to the shorter dimension of the
        // actual output frame. For landscape video the shorter dim is height;
        // for portrait video it's width. Base reference is 720p (shorter dim = 720).
        // This prevents text from being disproportionately large on narrow portrait frames
        // and keeps it visually consistent across quality levels during HLS ABR switches.
        $shorterDim = min($videoWidth, $videoHeight);
        $scaledSize = max(12, (int) round($size * $shorterDim / 720));

        if ($scrollEnabled) {
            $yPositions = [
                'top' => $padding,
                'middle' => '(h-text_h)/2',
                'bottom' => "h-text_h-{$padding}",
            ];
            $y = $yPositions[$position] ?? $yPositions['top'];

            // Convert speed name to pixels per second, scaled to actual frame width.
            // Base pps is calibrated for 1280px-wide frames (720p landscape).
            $basePps = WatermarkService::getSpeedPps($scrollSpeed);
            $pps = max(10, (int) round($basePps * $videoWidth / 1280));

            if ($scrollInterval > 0) {
                // INTERVAL MODE: text enters from right edge every $interval seconds.
                // t_local = mod(t - delay, interval) resets to 0 at each cycle start.
                // x = w - pps * t_local → starts at x=w (off-screen right), moves left.
                // When x < -tw, text is off-screen left (naturally invisible).
                // At next cycle, mod resets → x jumps back to w (re-enters from right).
                $tLocal = $scrollStartDelay > 0
                    ? "mod(t-{$scrollStartDelay}\\,{$scrollInterval})"
                    : "mod(t\\,{$scrollInterval})";
                $x = "w-{$pps}*{$tLocal}";
            } else {
                // CONTINUOUS MODE: text scrolls endlessly, wrapping around.
                // x = w - mod(pps * (t-delay), w+tw) → wraps when text fully exits left.
                $tExpr = $scrollStartDelay > 0 ? "t-{$scrollStartDelay}" : "t";
                $x = "w-mod({$pps}*({$tExpr})\\,w+tw)";
            }

            // Enable expression: only needed for start delay (to hide text before delay).
            // Interval timing is handled by the x expression itself.
            $enable = '';
            if ($scrollStartDelay > 0) {
                $enable = ":enable=gte(t\\,{$scrollStartDelay})";
            }
        } else {
            $positions = [
                'top-left' => ['x' => $padding, 'y' => $padding],
                'top-center' => ['x' => '(w-text_w)/2', 'y' => $padding],
                'top-right' => ['x' => "w-text_w-{$padding}", 'y' => $padding],
                'center-left' => ['x' => $padding, 'y' => '(h-text_h)/2'],
                'center' => ['x' => '(w-text_w)/2', 'y' => '(h-text_h)/2'],
                'center-right' => ['x' => "w-text_w-{$padding}", 'y' => '(h-text_h)/2'],
                'bottom-left' => ['x' => $padding, 'y' => "h-text_h-{$padding}"],
                'bottom-center' => ['x' => '(w-text_w)/2', 'y' => "h-text_h-{$padding}"],
                'bottom-right' => ['x' => "w-text_w-{$padding}", 'y' => "h-text_h-{$padding}"],
            ];
            $pos = $positions[$position] ?? $positions['bottom-right'];
            $x = $pos['x'];
            $y = $pos['y'];
            $enable = '';
        }

        // Color with opacity (e.g. white@0.8)
        $fontColor = !str_contains($color, '@') ? $color . '@' . $opacity : $color;

        $parts = [
            "drawtext=fontfile={$font}",
            "text={$text}",
            "expansion=normal",
            "fontsize={$scaledSize}",
            "fontcolor={$fontColor}",
            "shadowx=2",
            "shadowy=2",
            "x={$x}",
            "y={$y}",
        ];

        return implode(':', $parts) . $enable;
    }

    protected function escapeDrawtextValue(string $value): string
    {
        $value = str_replace('\\', '\\\\', $value);
        $value = str_replace(';', '\\;', $value);
        $value = str_replace(',', '\\,', $value);
        $value = str_replace(':', '\\:', $value);
        $value = str_replace("'", "\\'", $value);
        $value = str_replace('%', '\\%', $value);
        return $value;
    }

    protected function generateHlsPlaylist(string $outputDir, array $qualities): void
    {
        $ffmpeg = $this->getFFmpegPath();
        $hlsTime = (int) Setting::get('hls_segment_duration', 6);
        $playlistType = $this->getHlsPlaylistType();
        $flags = $this->getHlsFlags();
        $extraArgs = $this->getHlsExtraArgs();
        $hlsQualities = [];

        foreach ($qualities as $quality) {
            $input = "{$outputDir}/{$quality}.mp4";
            $hlsDir = "{$outputDir}/hls/{$quality}";
            
            if (!file_exists($input) || filesize($input) < 10240) {
                Log::warning('HLS: skipping quality, MP4 file missing or too small', ['quality' => $quality]);
                continue;
            }

            if (!file_exists($hlsDir)) {
                mkdir($hlsDir, 0755, true);
            }

            $segmentPattern = "{$hlsDir}/segment_%03d.ts";
            $playlistPath = "{$hlsDir}/playlist.m3u8";

            // Remux only (-c copy) — the MP4s already have aligned keyframes
            // from -force_key_frames during transcoding, so no re-encode needed.
            $cmd = sprintf(
                '%s -y -i %s -c copy -f hls -hls_time %d -hls_playlist_type %s -hls_flags %s -hls_list_size 0 %s -hls_segment_filename %s %s 2>&1',
                $ffmpeg,
                escapeshellarg($input),
                $hlsTime,
                $playlistType,
                $flags,
                $extraArgs,
                escapeshellarg($segmentPattern),
                escapeshellarg($playlistPath)
            );

            [$exitCode, $cmdOutput] = $this->runCommand($cmd);
            
            // Verify HLS segments were actually created and are valid
            $segments = glob("{$hlsDir}/segment_*.ts");
            $validSegments = array_filter($segments, fn($s) => filesize($s) > 2048);
            
            if ($exitCode !== 0 || empty($validSegments)) {
                Log::warning('HLS playlist generation failed or produced invalid segments', [
                    'quality' => $quality,
                    'exit_code' => $exitCode,
                    'segment_count' => count($segments),
                    'valid_segments' => count($validSegments),
                    'output' => substr($cmdOutput, 0, 500),
                ]);
                // Clean up broken HLS files
                array_map('unlink', $segments);
                if (file_exists($playlistPath)) unlink($playlistPath);
            } else {
                $hlsQualities[] = $quality;
            }
        }

        // Only generate master playlist if we have valid HLS qualities
        if (!empty($hlsQualities)) {
            $this->generateMasterPlaylist($outputDir, $hlsQualities);
        } else {
            Log::warning('HLS: no valid qualities produced, skipping master playlist', [
                'video_id' => $this->video->id,
            ]);
            // Remove the master playlist if it exists from a previous attempt
            $masterPath = "{$outputDir}/master.m3u8";
            if (file_exists($masterPath)) unlink($masterPath);
        }
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

    /**
     * Upload all locally-processed files to cloud storage (Wasabi/S3/B2).
     * FFmpeg requires local filesystem access, so we process locally first,
     * then push everything to cloud and optionally clean up local copies.
     */
    protected function uploadToCloudStorage(string $targetDisk): void
    {
        $localDisk = Storage::disk('public');
        $storagePath = $localDisk->path('');
        $videoDir = $this->getVideoDirectory();
        $uploadedCount = 0;
        $failedCount = 0;

        Log::info('ProcessVideoJob: uploading processed files to cloud storage', [
            'video_id' => $this->video->id,
            'target_disk' => $targetDisk,
            'video_dir' => $videoDir,
        ]);

        // Upload all files in the video directory (original + processed/)
        $allFiles = $localDisk->allFiles($videoDir);
        foreach ($allFiles as $file) {
            $localPath = $localDisk->path($file);
            if (StorageManager::uploadLocalFile($localPath, $file, $targetDisk)) {
                $uploadedCount++;
            } else {
                $failedCount++;
                Log::warning('ProcessVideoJob: failed to upload file to cloud', [
                    'file' => $file,
                    'disk' => $targetDisk,
                ]);
            }
        }

        // Upload thumbnail if it exists
        if ($this->video->thumbnail) {
            $thumbLocal = $localDisk->path($this->video->thumbnail);
            if (file_exists($thumbLocal)) {
                if (StorageManager::uploadLocalFile($thumbLocal, $this->video->thumbnail, $targetDisk)) {
                    $uploadedCount++;
                } else {
                    $failedCount++;
                }
            }
        }

        // Upload preview if it exists
        if ($this->video->preview_path) {
            $previewLocal = $localDisk->path($this->video->preview_path);
            if (file_exists($previewLocal)) {
                // preview_path is usually inside the video dir, so it may already be uploaded
                if (!in_array($this->video->preview_path, $allFiles)) {
                    if (StorageManager::uploadLocalFile($previewLocal, $this->video->preview_path, $targetDisk)) {
                        $uploadedCount++;
                    } else {
                        $failedCount++;
                    }
                }
            }
        }

        // Upload scrubber VTT if it exists
        if ($this->video->scrubber_vtt_path) {
            $vttLocal = $localDisk->path($this->video->scrubber_vtt_path);
            if (file_exists($vttLocal)) {
                if (!in_array($this->video->scrubber_vtt_path, $allFiles)) {
                    if (StorageManager::uploadLocalFile($vttLocal, $this->video->scrubber_vtt_path, $targetDisk)) {
                        $uploadedCount++;
                    } else {
                        $failedCount++;
                    }
                }
            }
        }

        Log::info('ProcessVideoJob: cloud upload complete', [
            'video_id' => $this->video->id,
            'uploaded' => $uploadedCount,
            'failed' => $failedCount,
        ]);

        StorageManager::cleanupTemp();

        // Update the video's storage_disk to reflect where files now live
        if ($failedCount === 0) {
            $this->video->update(['storage_disk' => $targetDisk]);

            // Optionally delete local copies after successful cloud upload
            if (Setting::get('cloud_offloading_delete_local', false)) {
                Log::info('ProcessVideoJob: deleting local copies after cloud offload', [
                    'video_id' => $this->video->id,
                ]);

                foreach ($allFiles as $file) {
                    $localDisk->delete($file);
                }

                if ($this->video->thumbnail && $localDisk->exists($this->video->thumbnail)) {
                    $localDisk->delete($this->video->thumbnail);
                }

                // Clean up empty directories
                if ($localDisk->exists($videoDir) && empty($localDisk->allFiles($videoDir))) {
                    $localDisk->deleteDirectory($videoDir);
                }
            }
        } else {
            // Some files failed — keep on local, log warning
            Log::warning('ProcessVideoJob: some files failed to upload, keeping local copies', [
                'video_id' => $this->video->id,
                'failed' => $failedCount,
            ]);
        }
    }

    /**
     * Run a shell command and return [exitCode, output].
     * Unlike shell_exec(), this captures the exit code so we can detect FFmpeg failures.
     */
    protected function runCommand(string $cmd): array
    {
        $process = proc_open($cmd, [
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ], $pipes);

        if (!is_resource($process)) {
            return [1, 'Failed to start process'];
        }

        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);

        return [$exitCode, trim($stdout . "\n" . $stderr)];
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('Video processing job failed permanently', [
            'video_id' => $this->video->id,
            'error' => $exception->getMessage(),
        ]);

        $this->video->update([
            'status' => 'failed',
            'failure_reason' => $exception->getMessage(),
        ]);
    }
}

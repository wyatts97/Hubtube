<?php

namespace App\Jobs;

use App\Models\Setting;
use App\Models\Video;
use App\Models\VideoEncoding;
use App\Services\AdminLogger;
use App\Services\Encoding\FfmpegCommands;
use App\Services\Encoding\FfmpegRunner;
use App\Services\Encoding\RenditionCoordinator;
use App\Services\Encoding\VideoCloudOffloader;
use App\Services\StorageManager;
use App\Services\VideoService;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Prepares an uploaded video and starts encoding its renditions.
 *
 * This job probes the file and makes the thumbnails, hover preview and seek
 * bar sprite sheet. It then plans the renditions and hands them to
 * RenditionCoordinator. Encoding itself happens in separate chunk jobs, so
 * this job finishes in minutes even for very long uploads.
 *
 * Also dispatched to encode renditions a processed video is missing, for
 * example after a new profile is enabled. Such a video stays live throughout.
 */
class ProcessVideoJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 3600;

    /** Keep the unique lock for the full timeout duration. */
    public int $uniqueFor = 3600;

    /** @var array<string, mixed> Setting::getAll(), loaded once per run */
    protected array $settings = [];

    protected FfmpegCommands $commands;

    protected FfmpegRunner $runner;

    public function __construct(
        public Video $video
    ) {}

    /** Prevents duplicate processing of the same video. */
    public function uniqueId(): string
    {
        return 'process-video-'.$this->video->id;
    }

    protected function s(string $key, mixed $default = null): mixed
    {
        return $this->settings[$key] ?? $default;
    }

    public function handle(VideoService $videoService, RenditionCoordinator $coordinator, FfmpegRunner $runner): void
    {
        $this->settings = Setting::getAll();
        $this->commands = new FfmpegCommands($this->settings);
        $this->runner = $runner;

        $video = $this->video;
        $wasProcessed = $video->status === 'processed';

        if (! $this->s('ffmpeg_enabled', true) || ! $runner->isAvailable()) {
            Log::info('FFmpeg unavailable or disabled, serving original file', ['video_id' => $video->id]);

            if (! $wasProcessed) {
                $this->markAsProcessedWithOriginal($videoService);
            }

            return;
        }

        if (! $wasProcessed) {
            $video->update(['status' => 'processing', 'processing_started_at' => now()]);
        }

        try {
            $source = $this->prepare($coordinator);

            $coordinator->setStage($video, 'planning');
            $needsWork = $coordinator->plan($video, $source, $this->commands);

            if ($needsWork->contains(fn ($encoding) => $encoding->chunks_total > 1) && $source['has_audio']) {
                $coordinator->setStage($video, 'audio');

                if (! $this->ensureSharedAudioTrack($coordinator)) {
                    // Without the shared track, chunks can't be joined in sync;
                    // encode each rendition whole instead, audio included.
                    VideoEncoding::whereIn('id', $needsWork->pluck('id'))
                        ->update(['chunks_total' => 1, 'chunk_seconds' => null]);
                }
            }

            $coordinator->setStage($video, $needsWork->isEmpty() ? 'finishing' : 'encoding');
            $coordinator->start($video);
        } catch (Exception $e) {
            Log::error('Video processing failed', ['video_id' => $video->id, 'error' => $e->getMessage()]);

            // Nothing will pick up rows planned before the failure.
            $video->encodings()
                ->whereNotIn('status', VideoEncoding::TERMINAL)
                ->update(['status' => VideoEncoding::FAILED, 'error' => Str::limit($e->getMessage(), 2000), 'completed_at' => now()]);

            if ($wasProcessed) {
                // Already live with its existing renditions; leave it that way.
                $coordinator->setStage($video, null);

                return;
            }

            $this->markAsProcessedWithOriginal($videoService, degraded: true, reason: $e->getMessage());
        }
    }

    /**
     * Probe the file and make everything that doesn't depend on renditions.
     *
     * Each step is skipped when its output already exists, so a retry or a
     * re-encode doesn't redo work.
     *
     * @return array{duration: float, width: int, height: int, has_audio: bool}
     */
    protected function prepare(RenditionCoordinator $coordinator): array
    {
        $video = $this->video;
        $inputPath = $coordinator->sourcePath($video);
        $videoDir = Storage::disk('public')->path("videos/{$video->slug}");
        $processedDir = $coordinator->processedDir($video);

        if (! is_dir($processedDir)) {
            mkdir($processedDir, 0755, true);
        }

        $coordinator->setStage($video, 'probing');
        $info = $this->getVideoInfo($inputPath);

        $video->update([
            'duration' => (int) $info['duration'],
            'is_portrait' => $info['height'] > $info['width'],
        ]);

        // Make the upload seekable in browsers (moov atom first). Idempotent,
        // but a full remux, so only once.
        $faststartMarker = "{$videoDir}/.faststart_done";
        if (! file_exists($faststartMarker)) {
            $this->applyFaststartToOriginal($inputPath);
            file_put_contents($faststartMarker, now()->toIso8601String());
        }

        $slugTitle = $this->getSluggedTitle();

        if (! file_exists("{$videoDir}/{$slugTitle}_thumb_0.jpg") && ! file_exists("{$videoDir}/{$slugTitle}_thumb_0.webp")) {
            $coordinator->setStage($video, 'thumbnails');
            $this->generateThumbnails($inputPath, $videoDir);
        }

        if ($this->s('animated_previews_enabled', true)) {
            $previewFile = "{$videoDir}/{$slugTitle}_preview.webp";
            if (! file_exists($previewFile) || filesize($previewFile) === 0) {
                $coordinator->setStage($video, 'preview');
                $this->generateAnimatedPreview($inputPath, $videoDir, (int) $info['duration']);
            }
        }

        if (! file_exists("{$videoDir}/sprites/sprite.jpg") || ! file_exists("{$videoDir}/scrubber.vtt")) {
            $coordinator->setStage($video, 'sprites');
            $this->generateScrubberSprite($inputPath, $videoDir, (int) $info['duration']);
        }

        return $info;
    }

    /**
     * Encode the audio once for every chunked rendition to share.
     *
     * Encoding audio separately per chunk would add a few milliseconds of
     * encoder padding at every boundary, which adds up to audible drift over a
     * long video. Chunked renditions are therefore encoded video-only and
     * joined to this one continuous track.
     */
    protected function ensureSharedAudioTrack(RenditionCoordinator $coordinator): bool
    {
        $output = $coordinator->audioTrackPath($this->video);

        if (file_exists($output) && filesize($output) > 0) {
            return true;
        }

        if (! is_dir(dirname($output))) {
            mkdir(dirname($output), 0755, true);
        }

        $tmp = substr($output, 0, -4).'.part.m4a';

        $cmd = sprintf(
            '%s -hide_banner -nostdin -y -i %s -vn -map 0:a:0 %s %s 2>&1',
            $this->commands->ffmpeg(),
            escapeshellarg($coordinator->sourcePath($this->video)),
            $this->commands->audioArgs(),
            escapeshellarg($tmp)
        );

        [$exitCode, $log] = $this->runner->run($cmd, $this->commands->timeout());

        if ($exitCode !== 0 || ! file_exists($tmp) || filesize($tmp) === 0) {
            @unlink($tmp);

            Log::warning('Encoding the shared audio track failed; renditions will not be chunked', [
                'video_id' => $this->video->id,
                'output' => substr($log, -500),
            ]);

            return false;
        }

        rename($tmp, $output);

        return true;
    }

    /**
     * Fallback when nothing can be transcoded: publish the upload as-is.
     */
    protected function markAsProcessedWithOriginal(VideoService $videoService, bool $degraded = false, ?string $reason = null): void
    {
        if ($this->s('cloud_offloading_enabled', false)) {
            $target = StorageManager::getActiveDiskName();
            if (StorageManager::isCloudDisk($target)) {
                app(VideoCloudOffloader::class)->offload($this->video, $target, $this->settings);
            }
        }

        $videoService->markAsProcessed(
            $this->video,
            ['original'],
            $degraded ? ($reason ?? 'Transcoding failed; shipped as unprocessed original.') : null
        );

        app(RenditionCoordinator::class)->setStage($this->video, null);

        if ($degraded) {
            AdminLogger::error(
                "Video #{$this->video->id} ({$this->video->title}) shipped as unprocessed original after a processing failure — no multi-quality renditions, HLS, or watermark were produced.",
                ['video_id' => $this->video->id, 'reason' => $reason]
            );
        }

        $videoService->notifyProcessedOnce($this->video);
    }

    protected function getSluggedTitle(): string
    {
        return Str::slug($this->video->title, '_') ?: 'video';
    }

    /**
     * @return array{duration: float, width: int, height: int, has_audio: bool}
     */
    protected function getVideoInfo(string $path): array
    {
        if (! file_exists($path)) {
            throw new RuntimeException("Video file not found: {$path}");
        }

        $cmd = $this->commands->ffprobe().' -v quiet -print_format json -show_format -show_streams '.escapeshellarg($path);

        [$exitCode, $output] = $this->runner->run($cmd, $this->commands->timeout());

        if ($exitCode !== 0 || empty($output)) {
            throw new RuntimeException("FFprobe failed (exit code {$exitCode}): ".substr($output, 0, 500));
        }

        $info = json_decode($output, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException('Failed to parse FFprobe output: '.json_last_error_msg());
        }

        $width = 0;
        $height = 0;
        $hasVideo = false;
        $hasAudio = false;

        foreach ($info['streams'] ?? [] as $stream) {
            $type = $stream['codec_type'] ?? '';

            if ($type === 'audio') {
                $hasAudio = true;
            }

            if ($type !== 'video' || $hasVideo) {
                continue;
            }

            $hasVideo = true;
            $width = (int) ($stream['width'] ?? 0);
            $height = (int) ($stream['height'] ?? 0);
            $rotation = 0;

            // Phones often store portrait video as landscape plus rotation
            // metadata; use the displayed orientation.
            if (isset($stream['tags']['rotate']) && is_numeric($stream['tags']['rotate'])) {
                $rotation = (int) $stream['tags']['rotate'];
            }

            foreach ($stream['side_data_list'] ?? [] as $sideData) {
                if (isset($sideData['rotation']) && is_numeric($sideData['rotation'])) {
                    $rotation = (int) $sideData['rotation'];
                    break;
                }
            }

            if (in_array((($rotation % 360) + 360) % 360, [90, 270], true)) {
                [$width, $height] = [$height, $width];
            }
        }

        return [
            'duration' => (float) ($info['format']['duration'] ?? 0),
            'width' => $width,
            'height' => $height,
            'has_audio' => $hasAudio,
        ];
    }

    /**
     * Move the moov atom to the front of the upload so browsers can seek it
     * even when it is served untranscoded.
     */
    protected function applyFaststartToOriginal(string $inputPath): void
    {
        $tempOutput = $inputPath.'.faststart.mp4';

        $cmd = sprintf(
            '%s -hide_banner -nostdin -y -i %s -c copy -movflags +faststart %s 2>&1',
            $this->commands->ffmpeg(),
            escapeshellarg($inputPath),
            escapeshellarg($tempOutput)
        );

        [$exitCode, $output] = $this->runner->run($cmd, $this->commands->timeout());

        if ($exitCode === 0 && file_exists($tempOutput) && filesize($tempOutput) > 0) {
            unlink($inputPath);
            rename($tempOutput, $inputPath);

            return;
        }

        @unlink($tempOutput);

        Log::warning('Failed to apply faststart to original video', [
            'video_id' => $this->video->id,
            'exit_code' => $exitCode,
            'output' => substr($output, 0, 300),
        ]);
    }

    protected function generateThumbnails(string $inputPath, string $videoDir): void
    {
        $ffmpeg = $this->commands->ffmpeg();
        $duration = (int) $this->video->duration;
        $count = (int) $this->s('thumbnail_count', 4);
        $slugTitle = $this->getSluggedTitle();

        $useWebP = $this->ffmpegSupportsWebP($ffmpeg);
        $ext = $useWebP ? 'webp' : 'jpg';

        for ($i = 0; $i < $count; $i++) {
            $time = (int) ($duration / ($count + 1) * ($i + 1));
            $output = "{$videoDir}/{$slugTitle}_thumb_{$i}.{$ext}";

            $codec = $useWebP
                ? '-vf "scale=640:-2:flags=lanczos" -c:v libwebp -lossless 0 -q:v 85'
                : '-q:v 2';

            $cmd = sprintf(
                '%s -hide_banner -nostdin -y -ss %d -i %s -vframes 1 %s %s 2>&1',
                $ffmpeg,
                $time,
                escapeshellarg($inputPath),
                $codec,
                escapeshellarg($output)
            );

            [$exitCode, $cmdOutput] = $this->runner->run($cmd, $this->commands->timeout());

            if ($exitCode !== 0) {
                Log::warning('Thumbnail generation failed', ['index' => $i, 'ext' => $ext, 'exit_code' => $exitCode, 'output' => substr($cmdOutput, 0, 300)]);
            }
        }

        $current = (string) $this->video->thumbnail;

        // Only adopt a generated frame when the video has no poster already.
        // Videos imported from elsewhere arrive with their own thumbnail under
        // a different name, so this step's own "already generated?" check above
        // does not recognise it — and re-encoding a video that has been live
        // for months must not silently swap the poster the site has been
        // showing for it.
        if ($current !== '' && Storage::disk('public')->exists($current)) {
            return;
        }

        $this->video->update([
            'thumbnail' => "videos/{$this->video->slug}/{$slugTitle}_thumb_0.{$ext}",
        ]);
    }

    /** Whether this FFmpeg build has libwebp. Cached for the process. */
    protected function ffmpegSupportsWebP(string $ffmpeg): bool
    {
        static $cache = [];

        if (! isset($cache[$ffmpeg])) {
            [, $output] = $this->runner->run("{$ffmpeg} -hide_banner -encoders 2>&1", 30);
            $cache[$ffmpeg] = str_contains($output, 'libwebp');
        }

        return $cache[$ffmpeg];
    }

    protected function generateAnimatedPreview(string $inputPath, string $videoDir, int $duration): void
    {
        $slugTitle = $this->getSluggedTitle();
        $output = "{$videoDir}/{$slugTitle}_preview.webp";

        // 3–6 seconds starting 10% in; very short videos from the start.
        $previewDuration = min(6, max(3, (int) ($duration * 0.1)));
        $startTime = max(0, (int) ($duration * 0.1));

        if ($duration < 10) {
            $startTime = 0;
            $previewDuration = min(3, $duration);
        }

        // Fit into a 16:9 card with pillarboxing so portrait frames stay whole.
        $cmd = sprintf(
            '%s -hide_banner -nostdin -y -ss %d -t %d -i %s -vf "fps=10,scale=320:180:force_original_aspect_ratio=decrease:flags=lanczos,pad=320:180:(ow-iw)/2:(oh-ih)/2:black" -c:v libwebp -lossless 0 -compression_level 4 -q:v 70 -loop 0 -preset default -an -vsync 0 %s 2>&1',
            $this->commands->ffmpeg(),
            $startTime,
            $previewDuration,
            escapeshellarg($inputPath),
            escapeshellarg($output)
        );

        [$exitCode, $result] = $this->runner->run($cmd, $this->commands->timeout());

        if ($exitCode === 0 && file_exists($output) && filesize($output) > 0) {
            $this->video->update(['preview_path' => "videos/{$this->video->slug}/{$slugTitle}_preview.webp"]);

            return;
        }

        Log::warning('Failed to generate animated preview', [
            'video_id' => $this->video->id,
            'output' => substr($result, 0, 500),
        ]);
    }

    /**
     * Seek-bar previews as one sprite sheet plus a WebVTT index into it.
     *
     * One image instead of up to a hundred separate files: the player loads
     * it with a single request, and the VTT cues point at regions of it using
     * the #xywh= media fragment Fluid Player understands.
     */
    protected function generateScrubberSprite(string $inputPath, string $videoDir, int $duration): void
    {
        if ($duration < 5) {
            return;
        }

        $spriteDir = "{$videoDir}/sprites";

        if (! is_dir($spriteDir)) {
            mkdir($spriteDir, 0755, true);
        }

        $interval = max(5, (int) ($duration / 100));
        $frames = (int) ceil($duration / $interval);
        $columns = min(10, $frames);
        $rows = (int) ceil($frames / $columns);
        $thumbWidth = 200;
        $thumbHeight = 112;
        $sprite = "{$spriteDir}/sprite.jpg";

        // Each frame is fitted into a 16:9 cell so portrait sources show whole.
        $cmd = sprintf(
            '%s -hide_banner -nostdin -y -i %s -vf %s -frames:v 1 -q:v 4 %s 2>&1',
            $this->commands->ffmpeg(),
            escapeshellarg($inputPath),
            escapeshellarg(sprintf(
                'fps=1/%d,scale=%d:%d:force_original_aspect_ratio=decrease:flags=lanczos,pad=%d:%d:(ow-iw)/2:(oh-ih)/2:black,tile=%dx%d',
                $interval, $thumbWidth, $thumbHeight, $thumbWidth, $thumbHeight, $columns, $rows
            )),
            escapeshellarg($sprite)
        );

        [$exitCode, $output] = $this->runner->run($cmd, $this->commands->timeout());

        if ($exitCode !== 0 || ! file_exists($sprite) || filesize($sprite) === 0) {
            Log::warning('Scrubber sprite sheet generation failed', [
                'video_id' => $this->video->id,
                'exit_code' => $exitCode,
                'output' => substr($output, 0, 300),
            ]);

            return;
        }

        // Per-frame images from the previous format are no longer referenced.
        array_map('unlink', glob("{$spriteDir}/sprite_*.jpg") ?: []);

        $vtt = "WEBVTT\n\n";

        for ($i = 0; $i < $frames; $i++) {
            $start = $i * $interval;
            $end = min(($i + 1) * $interval, $duration);
            $x = ($i % $columns) * $thumbWidth;
            $y = intdiv($i, $columns) * $thumbHeight;

            // Relative to the VTT, so cloud storage and CDN URLs still resolve.
            $vtt .= sprintf(
                "%s --> %s\nsprites/sprite.jpg#xywh=%d,%d,%d,%d\n\n",
                $this->vttTime($start), $this->vttTime($end), $x, $y, $thumbWidth, $thumbHeight
            );
        }

        file_put_contents("{$videoDir}/scrubber.vtt", $vtt);

        $this->video->update(['scrubber_vtt_path' => "videos/{$this->video->slug}/scrubber.vtt"]);
    }

    protected function vttTime(int $seconds): string
    {
        return sprintf('%02d:%02d:%02d.000', intdiv($seconds, 3600), intdiv($seconds % 3600, 60), $seconds % 60);
    }

    public function failed(Throwable $exception): void
    {
        Log::error('Video processing job failed permanently', [
            'video_id' => $this->video->id,
            'error' => $exception->getMessage(),
        ]);

        if ($this->video->status !== 'processed') {
            $this->video->update([
                'status' => 'failed',
                'failure_reason' => $exception->getMessage(),
            ]);
        }
    }
}

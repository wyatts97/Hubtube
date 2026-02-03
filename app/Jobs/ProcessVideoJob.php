<?php

namespace App\Jobs;

use App\Events\VideoProcessed;
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

            $videoService->markAsFailed($this->video, $e->getMessage());
        }
    }

    protected function processVideo(): array
    {
        $inputPath = Storage::disk('videos')->path($this->video->video_path);
        $outputDir = dirname($inputPath) . '/processed';

        if (!file_exists($outputDir)) {
            mkdir($outputDir, 0755, true);
        }

        $qualities = [];
        $targetQualities = [
            '240p' => ['width' => 426, 'height' => 240, 'bitrate' => '400k'],
            '360p' => ['width' => 640, 'height' => 360, 'bitrate' => '800k'],
            '480p' => ['width' => 854, 'height' => 480, 'bitrate' => '1400k'],
            '720p' => ['width' => 1280, 'height' => 720, 'bitrate' => '2800k'],
            '1080p' => ['width' => 1920, 'height' => 1080, 'bitrate' => '5000k'],
        ];

        $videoInfo = $this->getVideoInfo($inputPath);
        $this->video->update(['duration' => $videoInfo['duration']]);

        $this->generateThumbnails($inputPath, $outputDir);

        foreach ($targetQualities as $quality => $settings) {
            if ($videoInfo['height'] >= $settings['height']) {
                $this->transcodeToQuality($inputPath, $outputDir, $quality, $settings);
                $qualities[] = $quality;
            }
        }

        $this->generateHlsPlaylist($outputDir, $qualities);

        return $qualities;
    }

    protected function getVideoInfo(string $path): array
    {
        $ffprobe = config('hubtube.ffmpeg.ffprobe', '/usr/bin/ffprobe');
        
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
        $ffmpeg = config('hubtube.ffmpeg.binary', '/usr/bin/ffmpeg');
        $duration = $this->video->duration;
        $count = config('hubtube.video.thumbnail_count', 3);

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
            'thumbnail' => str_replace(Storage::disk('videos')->path(''), '', "{$outputDir}/thumb_0.jpg"),
        ]);
    }

    protected function transcodeToQuality(string $inputPath, string $outputDir, string $quality, array $settings): void
    {
        $ffmpeg = config('hubtube.ffmpeg.binary', '/usr/bin/ffmpeg');
        $threads = config('hubtube.ffmpeg.threads', 4);
        
        $output = "{$outputDir}/{$quality}.mp4";
        
        $cmd = sprintf(
            '%s -y -i %s -vf scale=%d:%d -c:v libx264 -preset medium -b:v %s -c:a aac -b:a 128k -threads %d -movflags +faststart %s 2>&1',
            $ffmpeg,
            escapeshellarg($inputPath),
            $settings['width'],
            $settings['height'],
            $settings['bitrate'],
            $threads,
            escapeshellarg($output)
        );

        shell_exec($cmd);
    }

    protected function generateHlsPlaylist(string $outputDir, array $qualities): void
    {
        $ffmpeg = config('hubtube.ffmpeg.binary', '/usr/bin/ffmpeg');

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

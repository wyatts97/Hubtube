<?php

namespace App\Jobs;

use App\Models\Setting;
use App\Models\VideoAd;
use App\Services\AdminLogger;
use App\Services\FfmpegService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;

/**
 * Transcodes a locally-uploaded MP4 ad creative into HLS (short segments, single
 * quality) so pre-roll/mid-roll/post-roll ads start playing faster than a single
 * large MP4 download allows. Deliberately separate from ProcessVideoJob: ad
 * creatives are small, single-quality, and use their own (more compressed,
 * shorter-segment) settings — this does not touch or share the main video
 * transcode pipeline/settings in any way.
 */
class ProcessAdCreativeJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;
    public int $timeout = 300;

    public function __construct(
        public VideoAd $videoAd
    ) {}

    public function handle(): void
    {
        if (!Setting::get('ad_hls_enabled', true)) {
            $this->videoAd->update(['hls_status' => 'skipped']);
            return;
        }

        if (!FfmpegService::isAvailable()) {
            Log::warning('ProcessAdCreativeJob: FFmpeg not available, skipping HLS conversion', [
                'video_ad_id' => $this->videoAd->id,
            ]);
            $this->videoAd->update(['hls_status' => 'skipped']);
            return;
        }

        $this->videoAd->update(['hls_status' => 'processing']);

        $localDisk = Storage::disk('public');
        $inputPath = $localDisk->path($this->videoAd->file_path);

        if (!file_exists($inputPath)) {
            Log::warning('ProcessAdCreativeJob: source file missing', [
                'video_ad_id' => $this->videoAd->id,
                'file_path' => $this->videoAd->file_path,
            ]);
            $this->videoAd->update(['hls_status' => 'failed']);
            return;
        }

        $relativeOutDir = "media/ads/hls/{$this->videoAd->id}";
        $outDir = $localDisk->path($relativeOutDir);

        if (!is_dir($outDir)) {
            mkdir($outDir, 0755, true);
        }

        $maxHeight = (int) Setting::get('ad_hls_max_height', 480);
        $crf = (int) Setting::get('ad_hls_crf', 28);
        $segmentDuration = (int) Setting::get('ad_hls_segment_duration', 2);

        $ffmpeg = FfmpegService::ffmpegPath();
        $segmentPattern = "{$outDir}/segment_%03d.ts";
        $playlistPath = "{$outDir}/playlist.m3u8";

        $cmd = sprintf(
            '%s -y -i %s -vf %s -c:v libx264 -preset veryfast -crf %d -pix_fmt yuv420p -c:a aac -b:a 96k -ac 2 -force_key_frames %s -f hls -hls_time %d -hls_playlist_type vod -hls_flags independent_segments -hls_segment_filename %s %s 2>&1',
            $ffmpeg,
            escapeshellarg($inputPath),
            escapeshellarg("scale=-2:'min(ih,{$maxHeight})'"),
            $crf,
            escapeshellarg("expr:gte(t,n_forced*{$segmentDuration})"),
            $segmentDuration,
            escapeshellarg($segmentPattern),
            escapeshellarg($playlistPath)
        );

        [$exitCode, $output] = $this->runCommand($cmd);

        $segments = glob("{$outDir}/segment_*.ts");
        $validSegments = array_filter($segments, fn ($s) => filesize($s) > 1024);

        if ($exitCode === 0 && file_exists($playlistPath) && !empty($validSegments)) {
            $this->videoAd->update([
                'hls_path' => "{$relativeOutDir}/playlist.m3u8",
                'hls_status' => 'ready',
            ]);

            Log::info('ProcessAdCreativeJob: HLS conversion succeeded', [
                'video_ad_id' => $this->videoAd->id,
                'segments' => count($validSegments),
            ]);
        } else {
            // Clean up any partial output — raw MP4 fallback keeps the ad playable.
            array_map('unlink', $segments);
            if (file_exists($playlistPath)) {
                unlink($playlistPath);
            }

            $this->videoAd->update(['hls_status' => 'failed']);

            Log::warning('ProcessAdCreativeJob: HLS conversion failed', [
                'video_ad_id' => $this->videoAd->id,
                'exit_code' => $exitCode,
                'output' => substr($output, 0, 500),
            ]);

            AdminLogger::error(
                "Ad creative #{$this->videoAd->id} ({$this->videoAd->name}) failed HLS conversion — still served as the original MP4.",
                ['video_ad_id' => $this->videoAd->id]
            );
        }
    }

    /**
     * Run a shell command via Symfony Process with a per-command timeout, so a
     * hung ffmpeg invocation is killed cleanly instead of relying solely on the
     * job's own timeout. Mirrors ProcessVideoJob's pattern as a small local copy
     * (not shared code — this job intentionally stays independent).
     */
    protected function runCommand(string $cmd): array
    {
        $process = Process::fromShellCommandline($cmd);
        $process->setTimeout(120);

        try {
            $process->run();
        } catch (ProcessTimedOutException $e) {
            Log::error('ProcessAdCreativeJob: ffmpeg command timed out', [
                'video_ad_id' => $this->videoAd->id,
                'timeout' => $process->getTimeout(),
            ]);
            return [1, 'Command timed out after ' . $process->getTimeout() . 's'];
        }

        $output = trim($process->getOutput() . "\n" . $process->getErrorOutput());

        return [$process->getExitCode() ?? 1, $output];
    }
}

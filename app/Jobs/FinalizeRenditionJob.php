<?php

namespace App\Jobs;

use App\Models\Setting;
use App\Models\VideoEncoding;
use App\Services\Encoding\FfmpegCommands;
use App\Services\Encoding\FfmpegRunner;
use App\Services\Encoding\HlsPackager;
use App\Services\Encoding\RenditionCoordinator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Turns a rendition's encoded chunks into its finished MP4 and HLS stream.
 *
 * Joining is a stream copy (concat demuxer), not a re-encode: every chunk
 * starts on a keyframe. Chunked renditions are video-only, so the shared
 * audio track is muxed in at the same time.
 */
class FinalizeRenditionJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $timeout = 1800;

    public int $backoff = 30;

    public function __construct(
        public int $encodingId,
        public string $runId,
    ) {}

    public function handle(RenditionCoordinator $coordinator, FfmpegRunner $runner, HlsPackager $hls): void
    {
        $encoding = VideoEncoding::with('video', 'profile')->find($this->encodingId);

        if (! $encoding || ! $encoding->video || $encoding->run_id !== $this->runId
            || $encoding->status !== VideoEncoding::FINALIZING) {
            return;
        }

        $video = $encoding->video;
        $commands = new FfmpegCommands(Setting::getAll());
        $output = $coordinator->outputPath($encoding);
        $partial = substr($output, 0, -4).'.part.mp4';

        if ($encoding->chunks_total <= 1) {
            $chunk = $coordinator->chunkPath($encoding, 0);

            // On a retry the chunk may already have been moved into place.
            if (file_exists($chunk)) {
                rename($chunk, $partial);
            } elseif (! file_exists($partial) && file_exists($output)) {
                rename($output, $partial);
            } elseif (! file_exists($partial)) {
                throw new RuntimeException("Encoded file for {$encoding->quality} is missing.");
            }
        } else {
            $this->join($coordinator, $runner, $commands, $encoding, $partial);
        }

        if (! file_exists($partial) || filesize($partial) < 10240) {
            @unlink($partial);

            throw new RuntimeException("Finished {$encoding->quality} file is missing or too small.");
        }

        rename($partial, $output);

        $size = filesize($output);

        if (! $encoding->isOriginal() && $commands->s('generate_hls', true)) {
            $processedDir = $coordinator->processedDir($video);

            if ($hls->packageRendition($commands, $processedDir, $encoding->quality)) {
                $size = $this->dropMp4IfStreamable($hls, $commands, $processedDir, $encoding, $output) ?? $size;
            }
        }

        $encoding->update([
            'status' => VideoEncoding::COMPLETED,
            'progress' => 100,
            'chunks_completed' => $encoding->chunks_total,
            'size' => $size,
            'error' => null,
            'completed_at' => now(),
        ]);

        $this->removeChunkDir($coordinator->chunkDir($encoding));

        $coordinator->renditionFinished($encoding);
    }

    /**
     * Delete a rendition's MP4 once its HLS stream is proven to stand alone.
     *
     * The HLS segments used to be a `-c copy` of this very file, so every
     * rendition was stored twice — on the measured library, 21 GB of MP4s
     * beside 21.7 GB of the same bytes as segments. HLS is what the player is
     * handed whenever it exists (VideoPlayer.vue), so the MP4 is the copy that
     * can go; quality_urls and bestDownloadPath both test each file for
     * existence and fall back to the master, so downloads and the progressive
     * fallback keep working.
     *
     * Nothing is deleted unless verifyPackaged() proves the stream plays at
     * the right length: there are no media backups, so a packaging run that
     * exits 0 is not on its own evidence. A failure keeps both copies — the
     * old, wasteful state, which is the safe one.
     *
     * @return int|null the size to record, or null to keep the MP4's own
     */
    protected function dropMp4IfStreamable(
        HlsPackager $hls,
        FfmpegCommands $commands,
        string $processedDir,
        VideoEncoding $encoding,
        string $output,
    ): ?int {
        $expected = (float) ($encoding->video->duration ?? 0);
        $reason = $hls->verifyPackaged($commands, $processedDir, $encoding->quality, $expected);

        if ($reason !== null) {
            Log::warning('HLS stream not verified; keeping the rendition MP4', [
                'video' => $encoding->video_id,
                'quality' => $encoding->quality,
                'reason' => $reason,
            ]);

            return null;
        }

        @unlink($output);

        return $hls->packagedSize($processedDir, $encoding->quality);
    }

    protected function join(RenditionCoordinator $coordinator, FfmpegRunner $runner, FfmpegCommands $commands, VideoEncoding $encoding, string $partial): void
    {
        $list = $coordinator->chunkDir($encoding).'/concat.txt';
        $lines = [];

        for ($i = 0; $i < $encoding->chunks_total; $i++) {
            $chunk = $coordinator->chunkPath($encoding, $i);

            if (! file_exists($chunk)) {
                throw new RuntimeException('Chunk '.($i + 1)." of {$encoding->quality} is missing.");
            }

            // Concat list quoting: single quotes, with embedded ones escaped.
            $lines[] = "file '".str_replace("'", "'\\''", str_replace('\\', '/', $chunk))."'";
        }

        file_put_contents($list, implode("\n", $lines)."\n");

        $audio = $coordinator->audioTrackPath($encoding->video);
        $withAudio = file_exists($audio) && filesize($audio) > 0;

        $cmd = sprintf(
            '%s -hide_banner -nostdin -y -f concat -safe 0 -i %s %s -c copy -movflags +faststart %s 2>&1',
            $commands->ffmpeg(),
            escapeshellarg($list),
            $withAudio
                ? '-i '.escapeshellarg($audio).' -map 0:v:0 -map 1:a:0'
                : '-map 0:v:0',
            escapeshellarg($partial)
        );

        [$exitCode, $log] = $runner->run($cmd, $commands->timeout());

        if ($exitCode !== 0) {
            @unlink($partial);

            throw new RuntimeException("Joining {$encoding->quality} chunks failed (exit code {$exitCode}): ".substr($log, -800));
        }
    }

    protected function removeChunkDir(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }

        array_map('unlink', glob("{$dir}/*") ?: []);
        @rmdir($dir);
        // The per-quality parent is empty once its last run is cleaned up.
        @rmdir(dirname($dir));
    }

    public function failed(Throwable $exception): void
    {
        app(RenditionCoordinator::class)->renditionFailed($this->encodingId, $this->runId, $exception->getMessage());
    }
}

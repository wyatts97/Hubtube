<?php

namespace App\Jobs;

use App\Models\Setting;
use App\Models\VideoEncoding;
use App\Services\Encoding\FfmpegCommands;
use App\Services\Encoding\FfmpegRunner;
use App\Services\Encoding\RenditionCoordinator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use RuntimeException;
use Throwable;

/**
 * Encodes one chunk of one rendition.
 *
 * A rendition of a long video is split into fixed-length chunks, each encoded
 * by its own job, so several workers can work on one video at once. Chunks
 * seek into the upload rather than splitting it first, so there is no copy
 * of the source to make and each chunk starts exactly on its boundary.
 *
 * Chunks are encoded video-only and joined to a single shared audio track in
 * FinalizeRenditionJob. A video short enough to be one chunk is encoded whole,
 * audio included.
 */
class EncodeVideoChunkJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $timeout = 3600;

    public int $backoff = 30;

    public function __construct(
        public int $encodingId,
        public string $runId,
        public int $index,
    ) {}

    public function handle(RenditionCoordinator $coordinator, FfmpegRunner $runner): void
    {
        $encoding = VideoEncoding::with('video', 'profile')->find($this->encodingId);

        // Superseded by a re-plan, already finished, or the video was deleted.
        if (! $encoding || ! $encoding->video || $encoding->run_id !== $this->runId || $encoding->isTerminal()
            || $encoding->status === VideoEncoding::FINALIZING) {
            return;
        }

        $video = $encoding->video;

        if (file_exists($coordinator->chunkDoneMarker($encoding, $this->index))) {
            $coordinator->chunkFinished($encoding, $this->index);

            return;
        }

        VideoEncoding::whereKey($encoding->id)
            ->where('run_id', $this->runId)
            ->where('status', VideoEncoding::QUEUED)
            ->update(['status' => VideoEncoding::PROCESSING, 'started_at' => now()]);

        $commands = new FfmpegCommands(Setting::getAll());
        $source = $coordinator->sourcePath($video);

        if (! file_exists($source)) {
            throw new RuntimeException("Source file not found: {$source}");
        }

        $dir = $coordinator->chunkDir($encoding);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $output = $coordinator->chunkPath($encoding, $this->index);
        $partial = substr($output, 0, -4).'.part.mp4';

        [$start, $length] = $this->window($encoding, (float) $video->duration);

        $cmd = $this->command($commands, $encoding, $source, $partial, $start, $length);

        $coordinator->recordChunkProgress($encoding, $this->index, 0);

        [$exitCode, $log] = $runner->run(
            $cmd,
            $commands->timeout(),
            function (float $seconds) use ($coordinator, $encoding, $length) {
                if ($length > 0) {
                    $coordinator->recordChunkProgress($encoding, $this->index, $seconds / $length * 100);
                }
            }
        );

        if ($exitCode !== 0 || ! file_exists($partial) || filesize($partial) < 1024) {
            @unlink($partial);

            throw new RuntimeException(sprintf(
                'Encoding %s chunk %d failed (exit code %d): %s',
                $encoding->quality,
                $this->index + 1,
                $exitCode,
                substr($log, -800)
            ));
        }

        rename($partial, $output);
        touch($coordinator->chunkDoneMarker($encoding, $this->index));

        $coordinator->chunkFinished($encoding, $this->index);
    }

    /**
     * Where this chunk starts and how long it runs, in seconds.
     * The last chunk runs to the end of the file (length is an estimate).
     *
     * @return array{0: float, 1: float}
     */
    protected function window(VideoEncoding $encoding, float $duration): array
    {
        if ($encoding->chunks_total <= 1 || ! $encoding->chunk_seconds) {
            return [0.0, $duration];
        }

        $start = (float) ($this->index * $encoding->chunk_seconds);
        $isLast = $this->index >= $encoding->chunks_total - 1;

        return [$start, $isLast ? max(1.0, $duration - $start) : (float) $encoding->chunk_seconds];
    }

    protected function command(FfmpegCommands $commands, VideoEncoding $encoding, string $source, string $output, float $start, float $length): string
    {
        $chunked = $encoding->chunks_total > 1 && $encoding->chunk_seconds;
        $isLast = $this->index >= $encoding->chunks_total - 1;

        $parts = [$commands->ffmpeg(), '-hide_banner -nostdin -y'];

        // Seeking before -i is fast, and exact when transcoding.
        if ($chunked && $start > 0) {
            $parts[] = '-ss '.$commands->number($start);
        }

        $parts[] = '-i '.escapeshellarg($source);

        if ($encoding->apply_watermark && $commands->hasImageWatermark()) {
            $parts[] = $commands->watermarkInput();
        }

        if ($chunked && ! $isLast) {
            $parts[] = '-t '.$commands->number($length);
        }

        // The watermark is laid out against the upload's own frame, as the
        // old whole-file watermark pass did, then the result is scaled.
        $graph = $commands->videoFilterGraph(
            $encoding->apply_watermark,
            $encoding->source_width,
            $encoding->source_height,
            $encoding->isOriginal() ? null : $encoding->height,
            $chunked ? $start : 0.0
        );

        $parts[] = '-filter_complex '.escapeshellarg($graph).' -map '.escapeshellarg('[outv]');

        if ($chunked) {
            $parts[] = '-an';
        } else {
            $parts[] = '-map '.escapeshellarg('0:a:0?').' '.$commands->audioArgs();
        }

        if ($encoding->isOriginal()) {
            // High quality, since this copy replaces the upload itself.
            $parts[] = '-c:v libx264 -preset fast -crf 18 -pix_fmt '.escapeshellarg((string) $commands->s('ffmpeg_pix_fmt', 'yuv420p'))
                .' -threads '.(int) $commands->s('ffmpeg_threads', 4)
                .' -force_key_frames '.escapeshellarg('expr:gte(t,n_forced*'.FfmpegCommands::KEYFRAME_SECONDS.')');
        } else {
            $parts[] = $commands->videoArgs($encoding->profile?->video_bitrate);
        }

        $parts[] = '-movflags +faststart -progress pipe:1 -nostats';
        $parts[] = escapeshellarg($output);

        return implode(' ', $parts);
    }

    public function failed(Throwable $exception): void
    {
        app(RenditionCoordinator::class)->renditionFailed($this->encodingId, $this->runId, $exception->getMessage());
    }
}

<?php

namespace App\Jobs;

use App\Services\Encoding\FfmpegRunner;
use App\Services\FfmpegService;
use App\Services\Media\MediaCompressService;
use App\Services\Media\MediaIndexService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Compress one library video file to H.265 / VP9 / AV1.
 *
 * Always writes a NEW file next to the original (see MediaCompressService::
 * targetPathFor) — the source is never overwritten or deleted, so a failed
 * encode only leaves a temp file behind.
 */
class CompressMediaFileJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 7200;

    public function __construct(
        public string $sourcePath,
        public string $codec = 'h265',
        public string $quality = 'balanced',
        public ?int $requestedBy = null,
    ) {
        $this->onQueue('media-compress');
    }

    public function handle(MediaCompressService $compress, FfmpegRunner $runner, MediaIndexService $index): void
    {
        $codec = in_array($this->codec, MediaCompressService::CODECS, true) ? $this->codec : 'h265';
        $quality = in_array($this->quality, MediaCompressService::QUALITIES, true) ? $this->quality : 'balanced';

        if (! $runner->isAvailable()) {
            Log::warning('CompressMediaFileJob skipped: ffmpeg unavailable.', ['path' => $this->sourcePath]);

            return;
        }

        $disk = Storage::disk('public');

        try {
            if (! $disk->exists($this->sourcePath)) {
                return;
            }
        } catch (Throwable) {
            return;
        }

        $target = $compress->targetPathFor($this->sourcePath, $codec);

        // Resolve paths to absolute for the shell command.
        $absoluteIn = $this->absolute($this->sourcePath);
        $absoluteOut = $this->absolute($target);

        if ($absoluteIn === null || $absoluteOut === null) {
            return;
        }

        $command = $compress->buildCommand($absoluteIn, $absoluteOut, $codec, $quality);

        // AV1 fallback: prefer libaom, use SVT-AV1 when that encoder is missing.
        if ($codec === 'av1' && ! $this->encoderPresent('libaom-av1')) {
            $command = str_replace('-c:v libaom-av1 -b:v 0', '-c:v libsvtav1', $command);
            $command = str_replace(' -cpu-used 4', ' -preset 6', $command);
        }

        [$exit, $output] = $runner->run($command, 7000);

        if ($exit !== 0) {
            Log::warning('CompressMediaFileJob failed.', ['path' => $this->sourcePath, 'output' => mb_substr($output, -2000)]);
            try {
                $disk->delete($target);
            } catch (Throwable) {
            }

            return;
        }

        try {
            if (! $disk->exists($target) || (int) $disk->size($target) === 0) {
                Log::warning('CompressMediaFileJob produced no output.', ['path' => $this->sourcePath]);
                try {
                    $disk->delete($target);
                } catch (Throwable) {
                }

                return;
            }
        } catch (Throwable) {
            return;
        }

        // Index the new file so it appears in the explorer immediately.
        try {
            $index->indexPath($target);
        } catch (Throwable $e) {
            Log::warning('CompressMediaFileJob could not index output.', ['target' => $target, 'error' => $e->getMessage()]);
        }
    }

    protected function absolute(string $path): ?string
    {
        try {
            $disk = Storage::disk('public');

            // Adapters backed by the local filesystem expose path(); anything
            // else (S3 etc.) cannot be shelled out to.
            if (method_exists($disk, 'path')) {
                return $disk->path($path);
            }
        } catch (Throwable) {
            return null;
        }

        return null;
    }

    protected function encoderPresent(string $encoder): bool
    {
        try {
            $ffmpeg = FfmpegService::ffmpegPath();
            $binary = is_file($ffmpeg) ? escapeshellarg($ffmpeg) : 'ffmpeg';
            $out = (string) shell_exec($binary.' -hide_banner -encoders 2>&1');

            return str_contains($out, $encoder);
        } catch (Throwable) {
            return false;
        }
    }
}

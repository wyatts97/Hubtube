<?php

namespace App\Services\Media;

use App\Services\Encoding\FfmpegRunner;
use App\Services\FfmpegService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

/**
 * File-level video compression for the Media Library explorer.
 *
 * Unlike StorageReclaimService (Video-record originals → H.264 only), this
 * works on any loose video file in the library and offers modern codecs.
 * It always writes a NEW file alongside the original — never overwrites —
 * so a failed or ugly encode cannot lose the good file.
 */
class MediaCompressService
{
    public const CODECS = ['h265', 'vp9', 'av1'];

    public const QUALITIES = ['balanced', 'small', 'smallest'];

    /** Extensions this action accepts (lowercase, no dot). */
    public const VIDEO_EXTENSIONS = ['mp4', 'mov', 'mkv', 'webm', 'avi', 'wmv', 'flv', 'm4v', 'mpg', 'mpeg'];

    /**
     * Which codecs the installed ffmpeg can actually encode.
     *
     * @return array<string, bool> codec => available
     */
    public function available(): array
    {
        return Cache::remember('media-compress:encoders', 600, function () {
            $out = '';
            try {
                $ffmpeg = FfmpegService::ffmpegPath();
                $binary = is_file($ffmpeg) ? escapeshellarg($ffmpeg) : 'ffmpeg';
                $out = (string) shell_exec($binary.' -hide_banner -encoders 2>&1');
            } catch (\Throwable) {
                $out = '';
            }

            // Empty output (no ffmpeg) → report none available; UI disables all.
            if ($out === '') {
                return ['h265' => false, 'vp9' => false, 'av1' => false];
            }

            return [
                'h265' => str_contains($out, 'libx265'),
                'vp9' => str_contains($out, 'libvpx-vp9'),
                'av1' => str_contains($out, 'libaom-av1') || str_contains($out, 'libsvtav1'),
            ];
        });
    }

    public function codecLabel(string $codec): string
    {
        return match ($codec) {
            'h265' => 'H.265 / HEVC (.mp4)',
            'vp9' => 'VP9 / WebM (.webm)',
            'av1' => 'AV1 (.mp4)',
            default => $codec,
        };
    }

    public function qualityLabel(string $quality): string
    {
        return match ($quality) {
            'balanced' => 'Balanced (recommended)',
            'small' => 'Smaller file',
            'smallest' => 'Smallest file',
            default => $quality,
        };
    }

    /**
     * Split sanitized library paths into compressible videos + human refusals.
     *
     * @param  list<string>  $paths
     * @return array{0: list<string>, 1: list<array{name: string, reason: string}>}
     */
    public function splitTargets(array $paths): array
    {
        $ok = [];
        $refused = [];
        $disk = Storage::disk('public');

        foreach (array_values(array_unique($paths)) as $path) {
            $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));

            if (! in_array($ext, self::VIDEO_EXTENSIONS, true)) {
                $refused[] = ['name' => basename($path), 'reason' => 'Not a video file.'];

                continue;
            }

            try {
                if (! $disk->exists($path)) {
                    $refused[] = ['name' => basename($path), 'reason' => 'No longer on disk.'];

                    continue;
                }
            } catch (\Throwable) {
                $refused[] = ['name' => basename($path), 'reason' => 'Could not be read.'];

                continue;
            }

            $ok[] = $path;
        }

        return [$ok, $refused];
    }

    /**
     * Output path: same folder, `name.<codec>.<container>` without clobbering.
     */
    public function targetPathFor(string $source, string $codec): string
    {
        $dir = dirname($source);
        $base = pathinfo($source, PATHINFO_FILENAME);

        // VP9 lives in WebM; H.265 and AV1 in MP4 (widest playback support).
        $container = $codec === 'vp9' ? 'webm' : 'mp4';
        $candidate = ($dir === '.' ? '' : $dir.'/').$base.'.'.$codec.'.'.$container;

        $disk = Storage::disk('public');
        if (! $disk->exists($candidate)) {
            return $candidate;
        }

        for ($i = 1; $i < 1000; $i++) {
            $try = ($dir === '.' ? '' : $dir.'/').$base.'.'.$codec.'-'.$i.'.'.$container;
            if (! $disk->exists($try)) {
                return $try;
            }
        }

        return ($dir === '.' ? '' : $dir.'/').$base.'.'.$codec.'.'.uniqid().'.'.$container;
    }

    /**
     * Build the ffmpeg command line. Audio is copied when possible (re-encoding
     * lossy AAC buys almost no bytes); video uses CRF rate control.
     */
    public function buildCommand(string $absoluteIn, string $absoluteOut, string $codec, string $quality): string
    {
        $ffmpeg = escapeshellarg(FfmpegService::ffmpegPath());

        [$videoArgs, $extra] = match ($codec) {
            'vp9' => $this->vp9Args($quality),
            'av1' => $this->av1Args($quality),
            default => $this->h265Args($quality),
        };

        return sprintf(
            '%s -hide_banner -y -i %s %s -c:a aac -b:a 128k %s %s',
            $ffmpeg,
            escapeshellarg($absoluteIn),
            $videoArgs,
            $extra,
            escapeshellarg($absoluteOut)
        );
    }

    /** @return array{0: string, 1: string} */
    protected function h265Args(string $quality): array
    {
        $crf = match ($quality) {
            'small' => 28,
            'smallest' => 30,
            default => 26,
        };
        $preset = $quality === 'balanced' ? 'medium' : 'slow';

        return ["-c:v libx265 -preset {$preset} -crf {$crf} -pix_fmt yuv420p", '-movflags +faststart -tag:v hvc1'];
    }

    /** @return array{0: string, 1: string} */
    protected function vp9Args(string $quality): array
    {
        $crf = match ($quality) {
            'small' => 32,
            'smallest' => 35,
            default => 30,
        };

        return ["-c:v libvpx-vp9 -b:v 0 -crf {$crf}", '-row-mt 1'];
    }

    /** @return array{0: string, 1: string} */
    protected function av1Args(string $quality): array
    {
        $crf = match ($quality) {
            'small' => 32,
            'smallest' => 35,
            default => 30,
        };

        // libaom-av1 if present, else libsvtav1 — resolved at run time inside the job.
        return ["-c:v libaom-av1 -b:v 0 -crf {$crf} -cpu-used 4", '-movflags +faststart'];
    }

    public function runner(): FfmpegRunner
    {
        return app(FfmpegRunner::class);
    }
}

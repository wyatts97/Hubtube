<?php

namespace App\Services\Encoding;

use App\Models\EncodeProfile;
use App\Models\VideoEncoding;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Packages finished MP4 renditions as HLS and maintains the master playlist.
 *
 * Packaging is a remux (-c copy): renditions are encoded with keyframes on a
 * fixed grid, so segments can be cut without re-encoding.
 */
class HlsPackager
{
    public function __construct(
        protected FfmpegRunner $runner,
    ) {}

    /** Remux processed/{quality}.mp4 into processed/hls/{quality}/. */
    public function packageRendition(FfmpegCommands $commands, string $processedDir, string $quality): bool
    {
        $input = "{$processedDir}/{$quality}.mp4";
        $hlsDir = "{$processedDir}/hls/{$quality}";

        if (! file_exists($input) || filesize($input) < 10240) {
            Log::warning('HLS: skipping quality, MP4 file missing or too small', ['quality' => $quality]);

            return false;
        }

        if (is_dir($hlsDir)) {
            array_map('unlink', glob("{$hlsDir}/*") ?: []);
        } else {
            mkdir($hlsDir, 0755, true);
        }

        $playlist = "{$hlsDir}/playlist.m3u8";

        $cmd = sprintf(
            '%s -hide_banner -nostdin -y -i %s -c copy -f hls -hls_time %d -hls_playlist_type %s -hls_flags %s -hls_list_size 0 %s -hls_segment_filename %s %s 2>&1',
            $commands->ffmpeg(),
            escapeshellarg($input),
            $commands->hlsSegmentSeconds(),
            escapeshellarg($commands->hlsPlaylistType()),
            escapeshellarg($commands->hlsFlags()),
            $commands->hlsExtraArgs(),
            escapeshellarg("{$hlsDir}/segment_%03d.ts"),
            escapeshellarg($playlist)
        );

        [$exitCode, $output] = $this->runner->run($cmd, $commands->timeout());

        $segments = glob("{$hlsDir}/segment_*.ts") ?: [];
        $valid = array_filter($segments, fn ($segment) => filesize($segment) > 2048);

        if ($exitCode !== 0 || empty($valid) || ! file_exists($playlist)) {
            Log::warning('HLS packaging failed or produced invalid segments', [
                'quality' => $quality,
                'exit_code' => $exitCode,
                'segments' => count($segments),
                'output' => substr($output, 0, 500),
            ]);
            array_map('unlink', glob("{$hlsDir}/*") ?: []);
            @rmdir($hlsDir);

            return false;
        }

        return true;
    }

    /**
     * Rewrite master.m3u8 to list every packaged rendition, lowest first.
     *
     * Called each time a rendition finishes, so a video that went live on its
     * lowest quality gains higher ones in place. Written to a temporary file
     * and renamed, so a player never reads a half-written playlist.
     *
     * @param  Collection<int, VideoEncoding>  $completed
     */
    public function writeMasterPlaylist(string $processedDir, Collection $completed): void
    {
        $master = "{$processedDir}/master.m3u8";
        $audioBandwidth = 128000;

        $lines = ['#EXTM3U', '#EXT-X-VERSION:3'];

        foreach ($completed->reject->isOriginal()->sortBy('height') as $encoding) {
            if (! file_exists("{$processedDir}/hls/{$encoding->quality}/playlist.m3u8")) {
                continue;
            }

            $profile = $encoding->profile;
            $bandwidth = static::bandwidthFor($profile, $encoding->height) + $audioBandwidth;
            $width = $profile?->width ?: (int) round($encoding->height * 16 / 9);
            $height = $profile?->height ?: $encoding->height;

            $lines[] = "#EXT-X-STREAM-INF:BANDWIDTH={$bandwidth},RESOLUTION={$width}x{$height}";
            $lines[] = "hls/{$encoding->quality}/playlist.m3u8";
        }

        if (count($lines) === 2) {
            @unlink($master);

            return;
        }

        $tmp = "{$master}.tmp";
        file_put_contents($tmp, implode("\n", $lines)."\n");
        rename($tmp, $master);
    }

    /** Bandwidth fallback for renditions whose profile has since been deleted. */
    public static function bandwidthFor(?EncodeProfile $profile, int $height): int
    {
        return $profile?->bandwidth() ?: max(250000, $height * 2600);
    }
}

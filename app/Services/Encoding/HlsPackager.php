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

    /** The single media file every rendition's segments live inside. */
    public const SEGMENT_FILE = 'stream.ts';

    /**
     * Remux processed/{quality}.mp4 into processed/hls/{quality}/.
     *
     * One `stream.ts` addressed by `#EXT-X-BYTERANGE`, not a segment per six
     * seconds (`single_file`). The bytes are identical either way, but a
     * library of a few thousand videos went from ~19,000 segment files to one
     * per rendition — fewer inodes, a far smaller media index, and a directory
     * listing that does not take seconds to read.
     *
     * The directory layout and master.m3u8 are unchanged, so videos packaged
     * the old way keep playing without a migration.
     */
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
        $flags = $this->flagsWithSingleFile($commands->hlsFlags());

        $cmd = sprintf(
            '%s -hide_banner -nostdin -y -i %s -c copy -f hls -hls_time %d -hls_playlist_type %s -hls_flags %s -hls_list_size 0 %s -hls_segment_filename %s %s 2>&1',
            $commands->ffmpeg(),
            escapeshellarg($input),
            $commands->hlsSegmentSeconds(),
            escapeshellarg($commands->hlsPlaylistType()),
            escapeshellarg($flags),
            $commands->hlsExtraArgs(),
            escapeshellarg("{$hlsDir}/".self::SEGMENT_FILE),
            escapeshellarg($playlist)
        );

        [$exitCode, $output] = $this->runner->run($cmd, $commands->timeout());

        if ($exitCode !== 0 || ! $this->hasMedia($hlsDir) || ! file_exists($playlist)) {
            Log::warning('HLS packaging failed or produced invalid segments', [
                'quality' => $quality,
                'exit_code' => $exitCode,
                'output' => substr($output, 0, 500),
            ]);
            array_map('unlink', glob("{$hlsDir}/*") ?: []);
            @rmdir($hlsDir);

            return false;
        }

        return true;
    }

    /** `single_file`, added to whatever the admin configured. */
    protected function flagsWithSingleFile(string $flags): string
    {
        $parts = array_filter(explode('+', $flags));

        if (! in_array('single_file', $parts, true)) {
            $parts[] = 'single_file';
        }

        return implode('+', $parts);
    }

    /** Whether a packaged directory holds real media, either layout. */
    protected function hasMedia(string $hlsDir): bool
    {
        return static::hasMediaIn($hlsDir);
    }

    protected static function hasMediaIn(string $hlsDir): bool
    {
        $files = array_merge(
            glob("{$hlsDir}/".self::SEGMENT_FILE) ?: [],
            glob("{$hlsDir}/segment_*.ts") ?: [],
        );

        return array_filter($files, fn (string $file) => filesize($file) > 2048) !== [];
    }

    /**
     * Whether this rendition is already streamable from its HLS stream.
     *
     * The other half of deleting rendition MP4s: once the MP4 is gone, a
     * packaged stream is the only evidence that quality exists, and the
     * encoder must not re-encode it just because the MP4 is missing.
     */
    public static function isPackaged(string $processedDir, string $quality): bool
    {
        $hlsDir = "{$processedDir}/hls/{$quality}";

        return file_exists("{$hlsDir}/playlist.m3u8") && static::hasMediaIn($hlsDir);
    }

    /**
     * Whether a packaged rendition can stand alone, so its MP4 may be deleted.
     *
     * Deleting the source of a stream on a box with no media backups is worth
     * proving rather than assuming: the playlist has to exist, the media has
     * to be there, and ffprobe has to read the *playlist* — following its
     * segment references — at the same length as the MP4 it came from. A
     * packaging run that exits 0 but writes a truncated stream fails here.
     *
     * Returns null when it is sound, or the reason it is not.
     */
    public function verifyPackaged(FfmpegCommands $commands, string $processedDir, string $quality, float $expectedSeconds): ?string
    {
        $hlsDir = "{$processedDir}/hls/{$quality}";
        $playlist = "{$hlsDir}/playlist.m3u8";

        if (! file_exists($playlist)) {
            return 'the playlist is missing';
        }

        if (! $this->hasMedia($hlsDir)) {
            return 'no segment data was written';
        }

        // -allowed_extensions ALL: ffprobe refuses unknown segment extensions
        // when reading a playlist from disk.
        [$exitCode, $output] = $this->runner->run(sprintf(
            '%s -v quiet -allowed_extensions ALL -print_format json -show_format %s',
            $commands->ffprobe(),
            escapeshellarg($playlist),
        ), $commands->timeout());

        $duration = (float) (json_decode($output, true)['format']['duration'] ?? 0);

        if ($exitCode !== 0 || $duration <= 0) {
            return 'the packaged stream could not be probed';
        }

        if ($expectedSeconds > 0 && abs($duration - $expectedSeconds) > 1.0) {
            return sprintf('it is %.1fs long against the rendition\'s %.1fs', $duration, $expectedSeconds);
        }

        return null;
    }

    /** Bytes the packaged rendition occupies, for the encoding row's size. */
    public function packagedSize(string $processedDir, string $quality): int
    {
        $total = 0;

        foreach (glob("{$processedDir}/hls/{$quality}/*") ?: [] as $file) {
            $total += is_file($file) ? (int) filesize($file) : 0;
        }

        return $total;
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

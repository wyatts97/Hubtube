<?php

namespace App\Services\Media;

use App\Models\MediaFile;
use App\Models\Setting;
use App\Models\Video;
use App\Services\AdminLogger;
use App\Services\Encoding\FfmpegRunner;
use App\Services\Encoding\MediaProbe;
use App\Services\FfmpegService;
use App\Support\Bytes;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Re-encoding video files from the Media Library explorer.
 *
 * The one compression pipeline. It replaced a second, H.264-only "storage
 * reclaim" workflow that had its own ledger table, review page and command.
 *
 * Every encode writes a NEW file beside its source (`clip.av1.mp4`), so a
 * failed or ugly encode can never lose the good file. For a loose file that is
 * the whole feature: compare the two, delete whichever you do not want. For a
 * video's original upload the old file cannot simply be deleted — video_path
 * points at it — so replaceOriginal() repoints the column and then removes it.
 */
class MediaCompressService
{
    public const CODECS = ['h265', 'vp9', 'av1'];

    public const QUALITIES = ['balanced', 'small', 'smallest'];

    /** Extensions this action accepts (lowercase, no dot). */
    public const VIDEO_EXTENSIONS = ['mp4', 'mov', 'mkv', 'webm', 'avi', 'wmv', 'flv', 'm4v', 'mpg', 'mpeg'];

    /** How long a progress entry outlives its job. */
    protected const STATUS_TTL_HOURS = 24;

    public function __construct(
        protected FfmpegRunner $runner,
        protected MediaProbe $probe,
        protected MediaReferenceResolver $references,
        protected MediaIndexService $index,
    ) {}

    // ── Encoders ────────────────────────────────────────────────────────────

    /**
     * Which codecs the installed ffmpeg can actually encode.
     *
     * @return array{h265: bool, vp9: bool, av1: bool}
     */
    public function available(): array
    {
        $encoders = $this->encoders();

        return [
            'h265' => in_array('libx265', $encoders, true),
            'vp9' => in_array('libvpx-vp9', $encoders, true),
            'av1' => in_array('libaom-av1', $encoders, true) || in_array('libsvtav1', $encoders, true),
        ];
    }

    /**
     * The video encoders ffmpeg reports, cached for ten minutes.
     *
     * Through FfmpegRunner rather than shell_exec() so the tests' fake answers
     * for it, and so the binary path gets the same hardening as every other
     * ffmpeg call.
     *
     * @return list<string>
     */
    protected function encoders(): array
    {
        return Cache::remember('media-compress:encoders', 600, function () {
            try {
                [$exit, $output] = $this->runner->run(
                    escapeshellarg(FfmpegService::ffmpegPath()).' -hide_banner -encoders 2>&1',
                    30,
                );
            } catch (Throwable) {
                return [];
            }

            if ($exit !== 0) {
                return [];
            }

            preg_match_all('/^\s*V\S*\s+(\S+)/m', $output, $matches);

            return $matches[1];
        });
    }

    public function codecLabel(string $codec): string
    {
        return match ($codec) {
            'h265' => 'H.265 / HEVC (.mp4)',
            'vp9' => 'VP9 (.webm)',
            'av1' => 'AV1 (.mp4)',
            default => $codec,
        };
    }

    public function qualityLabel(string $quality): string
    {
        return match ($quality) {
            'small' => 'Smaller file',
            'smallest' => 'Smallest file',
            default => 'Balanced (recommended)',
        };
    }

    // ── Targets ─────────────────────────────────────────────────────────────

    public function isVideoPath(string $path): bool
    {
        return in_array(strtolower(pathinfo($path, PATHINFO_EXTENSION)), self::VIDEO_EXTENSIONS, true);
    }

    /**
     * Split library paths into compressible videos and human refusals.
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
            if (! $this->isVideoPath($path)) {
                $refused[] = ['name' => basename($path), 'reason' => 'Not a video file.'];

                continue;
            }

            try {
                $exists = $disk->exists($path);
            } catch (Throwable) {
                $exists = false;
            }

            if (! $exists) {
                $refused[] = ['name' => basename($path), 'reason' => 'No longer on disk.'];

                continue;
            }

            $ok[] = $path;
        }

        return [$ok, $refused];
    }

    /** `clip.mp4` → `clip.av1.mp4`, suffixed `-1`, `-2`… rather than overwriting. */
    public function targetPathFor(string $source, string $codec): string
    {
        $prefix = dirname($source) === '.' ? '' : dirname($source).'/';
        $base = pathinfo($source, PATHINFO_FILENAME);
        // VP9 lives in WebM; H.265 and AV1 in MP4, which plays almost everywhere.
        $container = $codec === 'vp9' ? 'webm' : 'mp4';
        $disk = Storage::disk('public');

        for ($i = 0; $i < 1000; $i++) {
            $candidate = $prefix.$base.'.'.$codec.($i > 0 ? '-'.$i : '').'.'.$container;

            if (! $disk->exists($candidate)) {
                return $candidate;
            }
        }

        return $prefix.$base.'.'.$codec.'.'.uniqid().'.'.$container;
    }

    /**
     * Compressed copies of $source already sitting beside it, from the index.
     *
     * The naming convention is the link between a file and its encodes, so the
     * relationship survives without a table and without the progress cache.
     *
     * @return list<array{path: string, name: string, size: int}>
     */
    public function compressedCopiesOf(string $source): array
    {
        $directory = dirname($source) === '.' ? '' : dirname($source);
        $base = pathinfo($source, PATHINFO_FILENAME);
        $pattern = '/^'.preg_quote($base, '/').'\.(h265|vp9|av1)(-\d+)?\.(mp4|webm)$/i';

        return MediaFile::query()
            ->inDirectory($directory)
            ->matchingName($base.'.')
            ->get(['path', 'name', 'size'])
            ->filter(fn ($file) => preg_match($pattern, $file->name) === 1)
            ->map(fn ($file) => ['path' => $file->path, 'name' => $file->name, 'size' => (int) $file->size])
            ->values()
            ->all();
    }

    // ── Command ─────────────────────────────────────────────────────────────

    /**
     * The ffmpeg command line.
     *
     * `-progress pipe:1` feeds FfmpegRunner's progress callback. An MP4
     * source's audio is copied — re-encoding already-lossy AAC buys almost
     * nothing — but other containers often carry audio MP4 cannot hold (PCM in
     * .mov, WMA in .wmv, Vorbis in .mkv), so theirs is encoded to AAC. WebM
     * cannot carry AAC at all, so VP9 always gets Opus.
     */
    public function buildCommand(string $absoluteIn, string $absoluteOut, string $codec, string $quality): string
    {
        $mp4Audio = in_array(strtolower(pathinfo($absoluteIn, PATHINFO_EXTENSION)), ['mp4', 'm4v'], true)
            ? '-c:a copy'
            : '-c:a aac -b:a 160k';

        [$video, $container] = match ($codec) {
            'vp9' => [$this->vp9Args($quality), '-c:a libopus -b:a 128k'],
            'av1' => [$this->av1Args($quality), $mp4Audio.' -movflags +faststart'],
            default => [$this->h265Args($quality), $mp4Audio.' -movflags +faststart -tag:v hvc1'],
        };

        return sprintf(
            '%s -hide_banner -nostdin -y -i %s -map 0:v:0 -map 0:a:0? %s %s -progress pipe:1 -nostats %s',
            escapeshellarg(FfmpegService::ffmpegPath()),
            escapeshellarg($absoluteIn),
            $video,
            $container,
            escapeshellarg($absoluteOut),
        );
    }

    protected function h265Args(string $quality): string
    {
        $crf = ['small' => 28, 'smallest' => 30][$quality] ?? 26;
        $preset = $quality === 'balanced' ? 'medium' : 'slow';

        return "-c:v libx265 -preset {$preset} -crf {$crf} -pix_fmt yuv420p";
    }

    protected function vp9Args(string $quality): string
    {
        $crf = ['small' => 36, 'smallest' => 40][$quality] ?? 32;

        return "-c:v libvpx-vp9 -b:v 0 -crf {$crf} -row-mt 1 -pix_fmt yuv420p";
    }

    /**
     * SVT-AV1 when it is there — many times faster than libaom at a similar
     * size, which matters on a box that is also serving the site — else
     * libaom.
     */
    protected function av1Args(string $quality): string
    {
        $crf = ['small' => 36, 'smallest' => 40][$quality] ?? 32;

        if (in_array('libsvtav1', $this->encoders(), true)) {
            return "-c:v libsvtav1 -crf {$crf} -preset 8 -pix_fmt yuv420p";
        }

        return "-c:v libaom-av1 -b:v 0 -crf {$crf} -cpu-used 6 -row-mt 1 -pix_fmt yuv420p";
    }

    /**
     * The codec the dialog starts on: AV1, then VP9, then H.265 — the order
     * of how widely browsers play them, which matters most if the result is
     * ever swapped in as a video's original.
     */
    public function defaultCodec(): string
    {
        $available = $this->available();

        foreach (['av1', 'vp9', 'h265'] as $codec) {
            if ($available[$codec]) {
                return $codec;
            }
        }

        return 'av1';
    }

    // ── Verification ────────────────────────────────────────────────────────

    /**
     * Why an encode must not be kept, or null if it is sound.
     *
     *  - Readable and at least 10 KB: a killed ffmpeg leaves a header-only
     *    file that exists() is perfectly happy with.
     *  - A real video stream.
     *  - The same length within 250 ms. For a video's original, the scrubber's
     *    VTT cue times derive from videos.duration, so drift would silently
     *    desynchronise it on the next re-encode.
     *  - Smaller than the source, or it bought nothing.
     */
    public function verify(string $source, string $target): ?string
    {
        $disk = Storage::disk('public');
        $before = $this->readableSize($source);
        $after = $this->readableSize($target);

        if ($after === null) {
            return 'The compressed file could not be read.';
        }

        if ($after < 10240) {
            return 'The compressed file is too small to be real.';
        }

        $probe = $this->probe->inspect($disk->path($target));

        if (! $probe || ! $probe['has_video']) {
            return 'The compressed file has no readable video stream.';
        }

        if ($before !== null && $after >= $before) {
            return 'It came out no smaller than the original.';
        }

        $expected = $this->probe->inspect($disk->path($source))['duration_ms'] ?? 0;

        if ($expected > 0 && abs($probe['duration_ms'] - $expected) > 250) {
            return sprintf('Its length drifted by %.2fs.', abs($probe['duration_ms'] - $expected) / 1000);
        }

        return null;
    }

    /**
     * A file's size, or null if it cannot be read.
     *
     * Read once rather than exists()-then-size(): a stat can fail transiently
     * on a file written moments ago, and a failure here means "unverifiable".
     */
    public function readableSize(string $path): ?int
    {
        try {
            return Storage::disk('public')->size($path);
        } catch (Throwable) {
            return null;
        }
    }

    // ── Progress ────────────────────────────────────────────────────────────

    /**
     * Progress for one source file, shown as a badge in the explorer.
     *
     * The cache rather than a table: this is transient display state, and the
     * outcome that matters is the new file on disk plus a notification.
     *
     * @return array{state: string, codec: string, percent?: int, target?: string, before?: int, after?: int, error?: string}|null
     */
    public function status(string $path): ?array
    {
        return Cache::get($this->statusKey($path));
    }

    /**
     * Statuses for a page of files, in one cache round-trip.
     *
     * @param  list<string>  $paths
     * @return array<string, array> keyed by path, active entries only
     */
    public function statuses(array $paths): array
    {
        if ($paths === []) {
            return [];
        }

        $keys = array_combine($paths, array_map(fn ($path) => $this->statusKey($path), $paths));
        $values = Cache::many(array_values($keys));

        $result = [];

        foreach ($keys as $path => $key) {
            if (is_array($values[$key] ?? null)) {
                $result[$path] = $values[$key];
            }
        }

        return $result;
    }

    public function setStatus(string $path, array $status): void
    {
        Cache::put($this->statusKey($path), $status, now()->addHours(self::STATUS_TTL_HOURS));
    }

    protected function statusKey(string $path): string
    {
        return 'media-compress:'.md5($path);
    }

    // ── Replacing a video's original upload ─────────────────────────────────

    /**
     * The video whose original upload this path is, or null.
     *
     * Only the original: renditions and HLS segments are what the player
     * streams, so they are never swapped out from here. A soft-deleted video
     * can still be restored, so its files are not ours to touch.
     */
    public function videoOriginalFor(string $path): ?Video
    {
        if (! str_starts_with($path, 'videos/')) {
            return null;
        }

        $video = Video::withTrashed()->where('video_path', $path)->first();

        return $video && ! $video->trashed() ? $video : null;
    }

    /**
     * Why this video's original cannot be replaced by $replacement, or null.
     */
    public function replaceBlockedReason(Video $video, string $replacement): ?string
    {
        if (($video->storage_disk ?? 'public') !== 'public') {
            return 'This video is stored on '.$video->storage_disk.', not locally.';
        }

        if ($video->is_embedded) {
            return 'Embedded videos have no files of their own.';
        }

        if (! $video->canReencode()) {
            return $video->isEncoding() ? 'This video is still encoding.' : 'This video has no finished local files.';
        }

        // The player declares every progressive source as video/mp4, so a
        // WebM original would be refused by the browser.
        if (strtolower(pathinfo($replacement, PATHINFO_EXTENSION)) !== 'mp4') {
            return 'The player serves the original as MP4, so only an H.265 or AV1 copy can replace it.';
        }

        // A watermark is drawn once, into the original, and .watermark_done
        // records that. A copy made before that would lack it.
        if (Setting::get('watermark_enabled', false)
            && ! Storage::disk('public')->exists(dirname((string) $video->video_path).'/.watermark_done')) {
            return 'A watermark is configured but has not been applied yet.';
        }

        if (dirname($replacement) !== dirname((string) $video->video_path)) {
            return 'The compressed copy must sit beside the original.';
        }

        return null;
    }

    /**
     * Point the video at the compressed copy and delete the old upload.
     *
     * Ordered so a failure can never lose the file being served: verify, then
     * repoint the column, then delete. Returns null on success or the reason
     * nothing happened.
     */
    public function replaceOriginal(Video $video, string $replacement): ?string
    {
        $old = (string) $video->video_path;

        if ($reason = $this->replaceBlockedReason($video, $replacement)) {
            return $reason;
        }

        if ($reason = $this->verify($old, $replacement)) {
            return $reason;
        }

        $disk = Storage::disk('public');
        $before = $this->readableSize($old) ?? 0;
        $after = (int) $this->readableSize($replacement);

        try {
            // Quiet, because Video::booted() flushes ~130 cache keys on update.
            $video->forceFill(['video_path' => $replacement, 'size' => $after])->saveQuietly();
            $disk->delete($old);
        } catch (Throwable $e) {
            Log::error('Replacing a video original failed', ['video' => $video->id, 'error' => $e->getMessage()]);

            return 'The replacement failed: '.$e->getMessage();
        }

        // Inline rather than queued, so the listing is right the moment the
        // dialog closes — no row for a deleted file, folder sizes recomputed.
        $this->index->forgetPath($old);
        $this->index->indexPath($replacement);

        // saveQuietly() skips VideoObserver, which is what keeps
        // media_files.is_referenced honest — without this the file now being
        // served would read as unused, one click from deletion.
        $this->references->syncForPaths([$old, $replacement]);

        AdminLogger::log(
            sprintf('Replaced the original upload of video #%d with %s — saved %s', $video->id, basename($replacement), Bytes::saving($before, $after)),
            'admin',
            ['old' => $old, 'new' => $replacement, 'before_bytes' => $before, 'after_bytes' => $after],
            $video,
        );

        return null;
    }
}

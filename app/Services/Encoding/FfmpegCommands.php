<?php

namespace App\Services\Encoding;

use App\Services\FfmpegService;
use App\Services\WatermarkService;
use Illuminate\Support\Facades\Storage;

/**
 * Builds the pieces of ffmpeg command lines from the admin's encoding settings.
 *
 * Shared by the job that prepares a video and the jobs that encode its chunks,
 * so every part of a rendition is encoded with identical arguments. Everything
 * returned is already shell-escaped where it contains user-controlled values.
 */
class FfmpegCommands
{
    /**
     * Keyframe interval, in seconds, forced on every encode. HLS segments and
     * chunk boundaries both rely on keyframes landing on this grid, which is
     * why chunk lengths are always a multiple of it.
     */
    public const KEYFRAME_SECONDS = 2;

    /** @param  array<string, mixed>  $settings  Setting::getAll() */
    public function __construct(protected array $settings) {}

    public function s(string $key, mixed $default = null): mixed
    {
        return $this->settings[$key] ?? $default;
    }

    public function ffmpeg(): string
    {
        return escapeshellarg(FfmpegService::ffmpegPath());
    }

    public function ffprobe(): string
    {
        return escapeshellarg(FfmpegService::ffprobePath());
    }

    public function timeout(): int
    {
        return (int) $this->s('ffmpeg_command_timeout', 1800);
    }

    /** x264 video arguments. $bitrate is used only in bitrate rate-control mode. */
    public function videoArgs(?string $bitrate = null): string
    {
        $videoRate = ($this->s('ffmpeg_rate_control', 'crf') === 'bitrate' && $bitrate)
            ? '-b:v '.escapeshellarg($bitrate)
            : '-crf '.(int) $this->s('ffmpeg_crf', 22);

        // HLS segments and chunk-boundary stream copies both need keyframes on
        // this grid.
        $keyframes = '-force_key_frames '.escapeshellarg('expr:gte(t,n_forced*'.self::KEYFRAME_SECONDS.')');

        return trim(sprintf(
            '-c:v libx264 -preset %s %s -pix_fmt %s -threads %d %s %s',
            escapeshellarg((string) $this->s('video_quality_preset', 'veryfast')),
            $videoRate,
            escapeshellarg((string) $this->s('ffmpeg_pix_fmt', 'yuv420p')),
            (int) $this->s('ffmpeg_threads', 4),
            $keyframes,
            trim((string) $this->s('ffmpeg_mp4_extra_args', ''))
        ));
    }

    /**
     * x264 arguments for the watermarked copy that replaces the upload. It is
     * encoded near-losslessly regardless of the quality settings, since it
     * becomes the only original the site keeps.
     */
    public function originalVideoArgs(): string
    {
        return sprintf(
            '-c:v libx264 -preset fast -crf 18 -pix_fmt %s -threads %d -force_key_frames %s',
            escapeshellarg((string) $this->s('ffmpeg_pix_fmt', 'yuv420p')),
            (int) $this->s('ffmpeg_threads', 4),
            escapeshellarg('expr:gte(t,n_forced*'.self::KEYFRAME_SECONDS.')')
        );
    }

    public function audioArgs(): string
    {
        return '-c:a aac -b:a '.escapeshellarg((string) $this->s('audio_bitrate', '128k'));
    }

    public function hlsSegmentSeconds(): int
    {
        return max(1, (int) $this->s('hls_segment_duration', 6));
    }

    public function hlsPlaylistType(): string
    {
        return trim((string) $this->s('ffmpeg_hls_playlist_type', 'vod')) ?: 'vod';
    }

    public function hlsFlags(): string
    {
        return trim((string) $this->s('ffmpeg_hls_flags', 'independent_segments')) ?: 'independent_segments';
    }

    public function hlsExtraArgs(): string
    {
        return trim((string) $this->s('ffmpeg_hls_extra_args', ''));
    }

    // ── Watermark ──────────────────────────────────────────────────────────

    public function hasImageWatermark(): bool
    {
        if (! $this->s('watermark_enabled', false)) {
            return false;
        }

        $image = (string) $this->s('watermark_image', '');

        return $image !== '' && file_exists(Storage::disk('public')->path($image));
    }

    public function hasTextWatermark(): bool
    {
        return (bool) $this->s('watermark_text_enabled', false)
            && trim((string) $this->s('watermark_text', '')) !== '';
    }

    public function hasWatermark(): bool
    {
        return $this->hasImageWatermark() || $this->hasTextWatermark();
    }

    /** Extra `-i` input for the watermark image, or ''. It is always input 1. */
    public function watermarkInput(): string
    {
        if (! $this->hasImageWatermark()) {
            return '';
        }

        return '-i '.escapeshellarg(Storage::disk('public')->path((string) $this->s('watermark_image')));
    }

    /**
     * A filter graph from input 0 to the label [outv].
     *
     * The watermark is drawn at the source's own resolution and the result is
     * then scaled, so it keeps the same proportions in every rendition.
     * $timeOffset is where this chunk starts in the full video: input seeking
     * restarts `t` at zero, so scrolling text would otherwise jump at every
     * chunk boundary.
     */
    public function videoFilterGraph(bool $watermark, int $sourceWidth, int $sourceHeight, ?int $scaleHeight, float $timeOffset = 0.0): string
    {
        $filters = [];
        $label = '0:v';

        if ($watermark && $this->hasImageWatermark()) {
            $filters[] = '[1:v]'.$this->imageWatermarkPrep($sourceWidth).'[wm]';
            $filters[] = "[{$label}][wm]overlay=".$this->imageWatermarkPosition().'[wm_out]';
            $label = 'wm_out';
        }

        $chain = [];

        if ($watermark && ($text = $this->textWatermarkFilter($sourceWidth, $sourceHeight, $timeOffset))) {
            $chain[] = $text;
        }

        if ($scaleHeight) {
            $chain[] = "scale=-2:{$scaleHeight}";
        }

        $filters[] = "[{$label}]".(empty($chain) ? 'null' : implode(',', $chain)).'[outv]';

        return implode(';', $filters);
    }

    protected function imageWatermarkPrep(int $videoWidth): string
    {
        $opacity = (float) $this->s('watermark_opacity', 70) / 100;
        $scale = (float) $this->s('watermark_scale', 15) / 100;
        $width = max(1, (int) (($videoWidth ?: 1920) * $scale));

        return "scale={$width}:-1,format=rgba,colorchannelmixer=aa={$opacity}";
    }

    protected function imageWatermarkPosition(): string
    {
        $padding = (int) $this->s('watermark_padding', 10);

        $positions = [
            'top-left' => "x={$padding}:y={$padding}",
            'top-center' => "x=(W-w)/2:y={$padding}",
            'top-right' => "x=W-w-{$padding}:y={$padding}",
            'center-left' => "x={$padding}:y=(H-h)/2",
            'center' => 'x=(W-w)/2:y=(H-h)/2',
            'center-right' => "x=W-w-{$padding}:y=(H-h)/2",
            'bottom-left' => "x={$padding}:y=H-h-{$padding}",
            'bottom-center' => "x=(W-w)/2:y=H-h-{$padding}",
            'bottom-right' => "x=W-w-{$padding}:y=H-h-{$padding}",
        ];

        return $positions[(string) $this->s('watermark_position', 'bottom-right')] ?? $positions['bottom-right'];
    }

    public function textWatermarkFilter(int $videoWidth, int $videoHeight, float $timeOffset = 0.0): ?string
    {
        if (! $this->hasTextWatermark()) {
            return null;
        }

        $videoWidth = $videoWidth ?: 1920;
        $videoHeight = $videoHeight ?: 1080;

        $text = $this->escapeDrawtext((string) $this->s('watermark_text', ''));
        $font = $this->escapeDrawtext((string) $this->s('watermark_text_font', '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf'));
        $size = (int) $this->s('watermark_text_size', 24);
        $color = (string) $this->s('watermark_text_color', 'white');
        $opacity = (int) $this->s('watermark_text_opacity', 70) / 100;
        $padding = (int) $this->s('watermark_text_padding', 10);
        $position = (string) $this->s('watermark_text_position', 'top');

        $scrollEnabled = (bool) $this->s('watermark_text_scroll_enabled', false);
        $scrollSpeed = (string) $this->s('watermark_text_scroll_speed', 'medium');
        $scrollInterval = (int) $this->s('watermark_text_scroll_interval', 0);
        $scrollStartDelay = (int) $this->s('watermark_text_scroll_start_delay', 0);

        // Scale relative to the frame's shorter side against a 720p reference,
        // so portrait frames don't get oversized text.
        $scaledSize = max(12, (int) round($size * min($videoWidth, $videoHeight) / 720));

        // Presentation time within the full video, not within this chunk.
        $t = $timeOffset > 0 ? '(t+'.$this->number($timeOffset).')' : 't';

        $enable = '';

        if ($scrollEnabled) {
            $yPositions = [
                'top' => $padding,
                'middle' => '(h-text_h)/2',
                'bottom' => "h-text_h-{$padding}",
            ];
            $y = $yPositions[$position] ?? $yPositions['top'];

            // Speed is calibrated for 1280px-wide frames.
            $pps = max(10, (int) round(WatermarkService::getSpeedPps($scrollSpeed) * $videoWidth / 1280));

            if ($scrollInterval > 0) {
                // Text re-enters from the right edge every $scrollInterval seconds.
                $tLocal = $scrollStartDelay > 0
                    ? "mod({$t}-{$scrollStartDelay}\\,{$scrollInterval})"
                    : "mod({$t}\\,{$scrollInterval})";
                $x = "w-{$pps}*{$tLocal}";
            } else {
                // Continuous scroll, wrapping once the text has fully left.
                $tExpr = $scrollStartDelay > 0 ? "{$t}-{$scrollStartDelay}" : $t;
                $x = "w-mod({$pps}*({$tExpr})\\,w+tw)";
            }

            if ($scrollStartDelay > 0) {
                $enable = ":enable=gte({$t}\\,{$scrollStartDelay})";
            }
        } else {
            $positions = [
                'top-left' => ['x' => $padding, 'y' => $padding],
                'top-center' => ['x' => '(w-text_w)/2', 'y' => $padding],
                'top-right' => ['x' => "w-text_w-{$padding}", 'y' => $padding],
                'center-left' => ['x' => $padding, 'y' => '(h-text_h)/2'],
                'center' => ['x' => '(w-text_w)/2', 'y' => '(h-text_h)/2'],
                'center-right' => ['x' => "w-text_w-{$padding}", 'y' => '(h-text_h)/2'],
                'bottom-left' => ['x' => $padding, 'y' => "h-text_h-{$padding}"],
                'bottom-center' => ['x' => '(w-text_w)/2', 'y' => "h-text_h-{$padding}"],
                'bottom-right' => ['x' => "w-text_w-{$padding}", 'y' => "h-text_h-{$padding}"],
            ];
            $pos = $positions[$position] ?? $positions['bottom-right'];
            $x = $pos['x'];
            $y = $pos['y'];
        }

        $fontColor = ! str_contains($color, '@') ? $color.'@'.$opacity : $color;

        return implode(':', [
            "drawtext=fontfile={$font}",
            "text={$text}",
            'expansion=normal',
            "fontsize={$scaledSize}",
            "fontcolor={$fontColor}",
            'shadowx=2',
            'shadowy=2',
            "x={$x}",
            "y={$y}",
        ]).$enable;
    }

    protected function escapeDrawtext(string $value): string
    {
        return str_replace(
            ['\\', ';', ',', ':', "'", '%'],
            ['\\\\', '\\;', '\\,', '\\:', "\\'", '\\%'],
            $value
        );
    }

    /** Seconds formatted for an ffmpeg command line: at most 3 decimals, no exponent. */
    public function number(float $seconds): string
    {
        return rtrim(rtrim(number_format($seconds, 3, '.', ''), '0'), '.') ?: '0';
    }
}

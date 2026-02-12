<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Storage;

class WatermarkService
{
    protected static ?array $cachedFonts = null;

    public static function getSystemFonts(): array
    {
        if (static::$cachedFonts !== null) {
            return static::$cachedFonts;
        }

        $fonts = [];

        // Try fc-list (Linux/macOS)
        $output = @shell_exec('fc-list --format="%{family}|%{file}\n" 2>/dev/null');
        if ($output) {
            foreach (explode("\n", trim($output)) as $line) {
                $parts = explode('|', $line, 2);
                if (count($parts) === 2) {
                    $family = trim(explode(',', $parts[0])[0]);
                    $file = trim($parts[1]);
                    if ($family && $file && file_exists($file)) {
                        $fonts[$file] = $family;
                    }
                }
            }
        }

        // Fallback: scan common font directories
        if (empty($fonts)) {
            $dirs = [
                '/usr/share/fonts',
                '/usr/local/share/fonts',
                '/usr/share/fonts/truetype',
            ];
            foreach ($dirs as $dir) {
                if (!is_dir($dir)) continue;
                $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir));
                foreach ($iterator as $file) {
                    if (preg_match('/\.(ttf|otf)$/i', $file->getFilename())) {
                        $name = pathinfo($file->getFilename(), PATHINFO_FILENAME);
                        $fonts[$file->getPathname()] = $name;
                    }
                }
            }
        }

        asort($fonts);
        static::$cachedFonts = $fonts;
        return $fonts;
    }

    public static function getSpeedOptions(): array
    {
        return [
            'very_slow' => 'Very Slow',
            'slow' => 'Slow',
            'medium' => 'Medium',
            'fast' => 'Fast',
            'very_fast' => 'Very Fast',
        ];
    }

    public static function getSpeedPps(string $speed): int
    {
        return match ($speed) {
            'very_slow' => 40,
            'slow' => 80,
            'medium' => 150,
            'fast' => 300,
            'very_fast' => 500,
            default => 150,
        };
    }

    public static function getColorOptions(): array
    {
        return [
            'white' => 'White',
            'black' => 'Black',
            'red' => 'Red',
            'yellow' => 'Yellow',
            'green' => 'Green',
            'blue' => 'Blue',
            'cyan' => 'Cyan',
            'magenta' => 'Magenta',
            'orange' => 'Orange',
            'gray' => 'Gray',
        ];
    }
    public static function hasImageWatermark(): bool
    {
        if (!Setting::get('watermark_enabled', false)) {
            return false;
        }

        $watermarkImage = Setting::get('watermark_image', '');
        if (empty($watermarkImage)) {
            return false;
        }

        $watermarkPath = Storage::disk('public')->path($watermarkImage);
        return file_exists($watermarkPath);
    }

    public static function hasTextWatermark(): bool
    {
        if (!Setting::get('watermark_text_enabled', false)) {
            return false;
        }

        return trim((string) Setting::get('watermark_text', '')) !== '';
    }

    public static function getWatermarkPath(): ?string
    {
        if (!static::hasImageWatermark()) {
            return null;
        }

        $watermarkImage = Setting::get('watermark_image', '');
        if (empty($watermarkImage)) {
            return null;
        }

        $path = Storage::disk('public')->path($watermarkImage);
        return file_exists($path) ? $path : null;
    }

    public static function getWatermarkInput(): string
    {
        $path = static::getWatermarkPath();
        if (!$path) {
            return '';
        }

        return '-i ' . escapeshellarg($path);
    }

    public static function buildFilterComplex(int $videoWidth, int $videoHeight): string
    {
        $filters = [];
        $filters[] = "[0:v]scale=-2:{$videoHeight}[base]";
        $currentLabel = 'base';

        if (static::hasImageWatermark()) {
            $position = Setting::get('watermark_position', 'bottom-right');
            $opacity = Setting::get('watermark_opacity', 70) / 100;
            $scale = Setting::get('watermark_scale', 15) / 100;
            $padding = Setting::get('watermark_padding', 10);

            $wmWidth = (int) ($videoWidth * $scale);

            $positions = [
                'top-left' => "x={$padding}:y={$padding}",
                'top-center' => "x=(W-w)/2:y={$padding}",
                'top-right' => "x=W-w-{$padding}:y={$padding}",
                'center-left' => "x={$padding}:y=(H-h)/2",
                'center' => "x=(W-w)/2:y=(H-h)/2",
                'center-right' => "x=W-w-{$padding}:y=(H-h)/2",
                'bottom-left' => "x={$padding}:y=H-h-{$padding}",
                'bottom-center' => "x=(W-w)/2:y=H-h-{$padding}",
                'bottom-right' => "x=W-w-{$padding}:y=H-h-{$padding}",
            ];

            $pos = $positions[$position] ?? $positions['bottom-right'];

            $filters[] = "[1:v]scale={$wmWidth}:-1,format=rgba,colorchannelmixer=aa={$opacity}[wm]";
            $filters[] = "[{$currentLabel}][wm]overlay={$pos}[wm_out]";
            $currentLabel = 'wm_out';
        }

        $textFilter = static::buildTextWatermarkFilter($videoWidth, $videoHeight);
        if ($textFilter) {
            $filters[] = "[{$currentLabel}]{$textFilter}[outv]";
        } else {
            $filters[] = "[{$currentLabel}]null[outv]";
        }

        return implode(';', $filters);
    }

    protected static function buildTextWatermarkFilter(int $videoWidth, int $videoHeight): ?string
    {
        if (!static::hasTextWatermark()) {
            return null;
        }

        $text = static::escapeDrawtextValue((string) Setting::get('watermark_text', ''));
        $font = static::escapeDrawtextValue((string) Setting::get('watermark_text_font', '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf'));
        $size = (int) Setting::get('watermark_text_size', 24);
        $color = (string) Setting::get('watermark_text_color', 'white');
        $opacity = (int) Setting::get('watermark_text_opacity', 70) / 100;
        $padding = (int) Setting::get('watermark_text_padding', 10);
        $position = (string) Setting::get('watermark_text_position', 'top');

        $scrollEnabled = (bool) Setting::get('watermark_text_scroll_enabled', false);
        $scrollSpeed = (string) Setting::get('watermark_text_scroll_speed', 'medium');
        $scrollInterval = (int) Setting::get('watermark_text_scroll_interval', 0);
        $scrollStartDelay = (int) Setting::get('watermark_text_scroll_start_delay', 0);

        // Responsive font size: scale relative to video height.
        // Base size is authored for 720p. Scale proportionally.
        // Uses FFmpeg expression so it adapts per-video.
        $scaledSize = max(12, (int) round($size * $videoHeight / 720));

        // Color with opacity (e.g. white@0.7)
        $fontColor = static::buildFontColor($color, $opacity);

        // Y position based on simple top/middle/bottom (for scroll mode)
        // or full 9-position grid (for static mode)
        if ($scrollEnabled) {
            $yPositions = [
                'top' => $padding,
                'middle' => '(h-text_h)/2',
                'bottom' => "h-text_h-{$padding}",
            ];
            $y = $yPositions[$position] ?? $yPositions['top'];

            // Convert speed name to pixels per second.
            $pps = static::getSpeedPps($scrollSpeed);

            if ($scrollInterval > 0) {
                // INTERVAL MODE: text enters from right edge every $interval seconds.
                // t_local = mod(t - delay, interval) resets to 0 at each cycle start.
                // x = w - pps * t_local → starts at x=w (off-screen right), moves left.
                // When x < -tw, text is off-screen left (naturally invisible).
                // At next cycle, mod resets → x jumps back to w (re-enters from right).
                // The interval must be long enough for text to fully cross: (w+tw)/pps seconds.
                $tLocal = $scrollStartDelay > 0
                    ? "mod(t-{$scrollStartDelay}\\,{$scrollInterval})"
                    : "mod(t\\,{$scrollInterval})";
                $x = "w-{$pps}*{$tLocal}";
            } else {
                // CONTINUOUS MODE: text scrolls endlessly, wrapping around.
                // x = w - mod(pps * (t-delay), w+tw) → wraps when text fully exits left.
                $tExpr = $scrollStartDelay > 0 ? "t-{$scrollStartDelay}" : "t";
                $x = "w-mod({$pps}*({$tExpr})\\,w+tw)";
            }

            // Enable expression: only needed for start delay (to hide text before delay).
            // Interval timing is handled by the x expression itself.
            $enable = '';
            if ($scrollStartDelay > 0) {
                $enable = ":enable=gte(t\\,{$scrollStartDelay})";
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
            $enable = '';
        }

        $parts = [
            "drawtext=fontfile={$font}",
            "text={$text}",
            "expansion=normal",
            "fontsize={$scaledSize}",
            "fontcolor={$fontColor}",
            "shadowx=2",
            "shadowy=2",
            "x={$x}",
            "y={$y}",
        ];

        return implode(':', $parts) . $enable;
    }

    protected static function buildFontColor(string $color, float $opacity): string
    {
        // Color is already a named FFmpeg color (white, black, red, etc.)
        // Just append opacity
        if (!str_contains($color, '@')) {
            return $color . '@' . $opacity;
        }
        return $color;
    }

    protected static function escapeDrawtextValue(string $value): string
    {
        $value = str_replace('\\', '\\\\', $value);
        $value = str_replace(';', '\\;', $value);
        $value = str_replace(',', '\\,', $value);
        $value = str_replace(':', '\\:', $value);
        $value = str_replace("'", "\\'", $value);
        $value = str_replace('%', '\\%', $value);
        return $value;
    }
}

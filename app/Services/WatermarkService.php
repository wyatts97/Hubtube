<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Storage;

class WatermarkService
{
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
        $color = (string) Setting::get('watermark_text_color', '#ffffff');
        $opacity = (int) Setting::get('watermark_text_opacity', 70) / 100;
        $padding = (int) Setting::get('watermark_text_padding', 10);
        $position = (string) Setting::get('watermark_text_position', 'bottom-right');
        $customX = trim((string) Setting::get('watermark_text_x', ''));
        $customY = trim((string) Setting::get('watermark_text_y', ''));

        $scrollEnabled = (bool) Setting::get('watermark_text_scroll_enabled', false);
        $scrollSpeed = (int) Setting::get('watermark_text_scroll_speed', 5);
        $scrollInterval = (int) Setting::get('watermark_text_scroll_interval', 0);
        $scrollDuration = (int) Setting::get('watermark_text_scroll_duration', 10);

        $positions = [
            'top-left' => [
                'x' => $padding,
                'y' => $padding,
            ],
            'top-center' => [
                'x' => '(w-text_w)/2',
                'y' => $padding,
            ],
            'top-right' => [
                'x' => "w-text_w-{$padding}",
                'y' => $padding,
            ],
            'center-left' => [
                'x' => $padding,
                'y' => '(h-text_h)/2',
            ],
            'center' => [
                'x' => '(w-text_w)/2',
                'y' => '(h-text_h)/2',
            ],
            'center-right' => [
                'x' => "w-text_w-{$padding}",
                'y' => '(h-text_h)/2',
            ],
            'bottom-left' => [
                'x' => $padding,
                'y' => "h-text_h-{$padding}",
            ],
            'bottom-center' => [
                'x' => '(w-text_w)/2',
                'y' => "h-text_h-{$padding}",
            ],
            'bottom-right' => [
                'x' => "w-text_w-{$padding}",
                'y' => "h-text_h-{$padding}",
            ],
        ];

        $pos = $positions[$position] ?? $positions['bottom-right'];
        $x = $customX !== '' ? $customX : $pos['x'];
        $y = $customY !== '' ? $customY : $pos['y'];

        // Frame-based horizontal scroll: x=(mod(speed*n\,w+tw)-tw)
        // Commas inside expressions must be backslash-escaped.
        if ($scrollEnabled) {
            $x = "(mod({$scrollSpeed}*n\\,w+tw)-tw)";
        }

        // Convert hex color to FFmpeg named color or 0x format.
        // # is unsafe in shell double-quoted strings, so use 0xRRGGBB instead.
        $fontColor = static::convertColor($color, $opacity);

        // Interval timing: show for $scrollDuration seconds every $scrollInterval seconds.
        // Commas inside expressions must be backslash-escaped.
        $enable = '';
        if ($scrollEnabled && $scrollInterval > 0 && $scrollDuration > 0) {
            $enable = ":enable=lt(mod(t\\,{$scrollInterval})\\,{$scrollDuration})";
        }

        $parts = [
            "drawtext=fontfile={$font}",
            "text={$text}",
            "expansion=normal",
            "fontsize={$size}",
            "fontcolor={$fontColor}",
            "shadowx=2",
            "shadowy=2",
            "x={$x}",
            "y={$y}",
        ];

        return implode(':', $parts) . $enable;
    }

    protected static function convertColor(string $color, float $opacity): string
    {
        $colorMap = [
            '#ffffff' => 'white',
            '#000000' => 'black',
            '#ff0000' => 'red',
            '#00ff00' => 'green',
            '#0000ff' => 'blue',
            '#ffff00' => 'yellow',
            '#ff00ff' => 'magenta',
            '#00ffff' => 'cyan',
            '#808080' => 'gray',
        ];

        $lower = strtolower($color);

        if (isset($colorMap[$lower])) {
            return $colorMap[$lower] . '@' . $opacity;
        }

        // Convert #RRGGBB to 0xRRGGBB (shell-safe, no # character)
        if (str_starts_with($lower, '#')) {
            return '0x' . substr($lower, 1) . '@' . $opacity;
        }

        // Already a named color or other format
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

<?php

namespace App\Services;

use App\Models\Setting;

class FfmpegService
{
    public const DEFAULT_FFMPEG_PATH = '/usr/local/bin/ffmpeg';
    public const DEFAULT_FFPROBE_PATH = '/usr/local/bin/ffprobe';

    public static function ffmpegPath(): string
    {
        $configured = Setting::get('ffmpeg_path', '');
        return static::resolveBinaryPath('ffmpeg', $configured, static::DEFAULT_FFMPEG_PATH, '/usr/bin/ffmpeg');
    }

    public static function ffprobePath(): string
    {
        $configured = Setting::get('ffprobe_path', '');
        return static::resolveBinaryPath('ffprobe', $configured, static::DEFAULT_FFPROBE_PATH, '/usr/bin/ffprobe');
    }

    public static function isAvailable(): bool
    {
        $ffmpeg = static::ffmpegPath();
        if (file_exists($ffmpeg) && is_executable($ffmpeg)) {
            return true;
        }

        $binary = PHP_OS_FAMILY === 'Windows' ? 'where ffmpeg' : 'which ffmpeg 2>/dev/null';
        $output = trim(shell_exec($binary) ?? '');
        return !empty($output);
    }

    protected static function resolveBinaryPath(string $binary, ?string $configured, string $preferred, string $fallback): string
    {
        if (!empty($configured) && file_exists($configured)) {
            return $configured;
        }

        if (file_exists($preferred)) {
            return $preferred;
        }

        $lookup = PHP_OS_FAMILY === 'Windows' ? "where {$binary}" : "which {$binary} 2>/dev/null";
        $which = trim(shell_exec($lookup) ?? '');
        if (!empty($which)) {
            return $which;
        }

        return $fallback;
    }
}

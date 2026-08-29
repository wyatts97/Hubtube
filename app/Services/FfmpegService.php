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

    /**
     * Reject a configured binary path that is not a plain absolute filesystem path.
     *
     * These paths come from admin-editable settings and are interpolated into
     * shell command strings, so anything carrying shell metacharacters, quotes,
     * whitespace or a traversal segment is discarded in favour of the detected
     * default rather than being executed.
     */
    protected static function isSafeBinaryPath(string $path): bool
    {
        if ($path === '' || strlen($path) > 4096) {
            return false;
        }

        // Absolute paths only: POSIX "/usr/bin/ffmpeg" or Windows "C:\ffmpeg\ffmpeg.exe".
        $isPosixAbsolute = str_starts_with($path, '/');
        $isWindowsAbsolute = strlen($path) > 2
            && ctype_alpha($path[0])
            && $path[1] === ':'
            && ($path[2] === '/' || $path[2] === chr(92));

        if (! $isPosixAbsolute && ! $isWindowsAbsolute) {
            return false;
        }

        if (str_contains($path, '..')) {
            return false;
        }

        // Any shell metacharacter, quote or whitespace disqualifies the path.
        return ! preg_match('/[\s;&|<>$`(){}\[\]!*?~#\x27"]/', $path);
    }

    protected static function resolveBinaryPath(string $binary, ?string $configured, string $preferred, string $fallback): string
    {
        $configured = (string) $configured;

        if ($configured !== '' && static::isSafeBinaryPath($configured) && file_exists($configured)) {
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

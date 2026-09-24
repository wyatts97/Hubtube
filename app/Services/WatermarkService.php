<?php

namespace App\Services;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

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
                if (! is_dir($dir)) {
                    continue;
                }
                $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
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
}

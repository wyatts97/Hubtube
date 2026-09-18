<?php

namespace App\Support;

/**
 * Human-readable byte sizes, in one place.
 *
 * There were seven independent implementations of this — in MediaLibrary,
 * StatsOverview, SystemStatusBar, Backups, LogViewerService,
 * ArchiveImportService and the Image model — disagreeing on decimal places and
 * on whether terabytes existed at all. MediaLibrary's had no TB branch, so a
 * multi-terabyte video root would have rendered as "3,481.22 GB" in the
 * storage panel while the dashboard beside it said "3.40 TB".
 *
 * Deliberately not Number::fileSize(): that uses SI-ish labels against
 * binary-ish maths depending on the arguments given, and the existing call
 * sites all use 1024-based units with the "KB/MB/GB" labels users here already
 * see. Keeping that convention means no displayed value changes except
 * MediaLibrary's, which gains TB on purpose.
 */
class Bytes
{
    /** 1024-based steps, largest first. */
    private const UNITS = [
        ['label' => 'TB', 'size' => 1099511627776],
        ['label' => 'GB', 'size' => 1073741824],
        ['label' => 'MB', 'size' => 1048576],
        ['label' => 'KB', 'size' => 1024],
    ];

    /**
     * Format a byte count.
     *
     * @param  int  $precision  decimal places for anything at or above 1 KB
     */
    public static function format(int|float|null $bytes, int $precision = 2): string
    {
        $bytes = (float) ($bytes ?? 0);

        if ($bytes < 0) {
            return '0 B';
        }

        foreach (self::UNITS as $unit) {
            if ($bytes >= $unit['size']) {
                return number_format($bytes / $unit['size'], $precision).' '.$unit['label'];
            }
        }

        return number_format($bytes).' B';
    }

    /**
     * Format a saving as "120 MB (43%)", or just the size when the original
     * size is unknown.
     */
    public static function saving(int $before, int $after, int $precision = 1): string
    {
        $saved = max(0, $before - $after);
        $formatted = self::format($saved, $precision);

        if ($before <= 0) {
            return $formatted;
        }

        return sprintf('%s (%d%%)', $formatted, (int) round($saved / $before * 100));
    }
}

<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use SplFileObject;

/**
 * LogViewerService
 *
 * Streams/parses storage/logs/*.log files safely for huge files.
 * Supports level filtering, free-text search, tail mode, and pagination.
 */
class LogViewerService
{
    private const CACHE_TTL = 10; // seconds

    /**
     * Get metadata for every .log file in storage/logs, newest first.
     */
    public function getLogFiles(): array
    {
        return Cache::remember('log_viewer:files', self::CACHE_TTL, function () {
            $logPath = storage_path('logs');
            if (!is_dir($logPath)) {
                return [];
            }

            $files = [];
            foreach (File::files($logPath) as $file) {
                if ($file->getExtension() !== 'log') {
                    continue;
                }
                $files[] = [
                    'name' => $file->getFilename(),
                    'path' => $file->getPathname(),
                    'size_bytes' => $file->getSize(),
                    'size' => $this->formatBytes($file->getSize()),
                    'modified_at' => $file->getMTime(),
                    'modified' => date('Y-m-d H:i:s', $file->getMTime()),
                ];
            }

            usort($files, fn ($a, $b) => $b['modified_at'] <=> $a['modified_at']);
            return $files;
        });
    }

    /**
     * Parse a log file into structured entries.
     *
     * @param string      $filename   Filename inside storage/logs/
     * @param int|null    $limit      Max entries to return (null = all)
     * @param string|null $level      Filter by level (lowercase)
     * @param string|null $search     Free-text search against message
     * @param bool        $tail       If true, read from end of file (tail-N)
     * @return array<int, array{timestamp:string,environment:string,level:string,level_color:string,message:string,context:string,trace:string}>
     */
    public function parseLogFile(
        string $filename,
        ?int $limit = 500,
        ?string $level = null,
        ?string $search = null,
        bool $tail = true,
    ): array {
        $path = storage_path('logs/' . basename($filename));
        if (!File::exists($path)) {
            return [];
        }

        $entries = $this->readEntries($path, $tail ? ($limit ?? 500) : null);

        // Apply filters
        if ($level !== null && $level !== '') {
            $level = strtolower($level);
            $entries = array_values(array_filter($entries, fn ($e) => $e['level'] === $level));
        }

        if ($search !== null && $search !== '') {
            $needle = mb_strtolower($search);
            $entries = array_values(array_filter($entries, fn ($e) =>
                str_contains(mb_strtolower($e['message']), $needle)
                || str_contains(mb_strtolower($e['context']), $needle)
                || str_contains(mb_strtolower($e['trace']), $needle)
            ));
        }

        if ($limit !== null && count($entries) > $limit) {
            $entries = array_slice($entries, 0, $limit);
        }

        return $entries;
    }

    /**
     * Search across all log files.
     */
    public function searchLogs(string $search, int $limit = 200, ?string $level = null): array
    {
        $results = [];
        foreach ($this->getLogFiles() as $file) {
            $matches = $this->parseLogFile($file['name'], $limit, $level, $search, true);
            foreach ($matches as $m) {
                $m['file'] = $file['name'];
                $results[] = $m;
                if (count($results) >= $limit) {
                    return $results;
                }
            }
        }
        return $results;
    }

    /**
     * Read entries from a log file, streaming. If $tailLimit is set,
     * only the last ~$tailLimit entries are returned (efficient for huge files).
     */
    private function readEntries(string $path, ?int $tailLimit = null): array
    {
        $entries = [];
        $current = null;
        $pattern = '/^\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\]\s+([^.]+)\.(\w+):\s*(.*)$/';

        // For tail mode, we need to read entries from the end.
        // Strategy: read the file line-by-line into a capped deque-like array,
        // keyed by entry (not line), discarding older entries when limit exceeded.
        $keep = $tailLimit !== null ? max($tailLimit * 4, 500) : PHP_INT_MAX; // buffer extra so filters still have room

        try {
            $file = new SplFileObject($path, 'r');
        } catch (\Throwable $e) {
            return [];
        }

        while (!$file->eof()) {
            $line = $file->fgets();
            if ($line === false) {
                break;
            }

            if (preg_match($pattern, $line, $m)) {
                if ($current !== null) {
                    $entries[] = $this->finalizeEntry($current);
                    if ($tailLimit !== null && count($entries) > $keep) {
                        // drop oldest to stay within buffer
                        $entries = array_slice($entries, -$keep);
                    }
                }
                $level = strtolower($m[3]);
                $current = [
                    'timestamp' => $m[1],
                    'environment' => $m[2],
                    'level' => $level,
                    'level_color' => $this->getLevelColor($level),
                    'message' => rtrim($m[4], "\r\n"),
                    'raw_rest' => '',
                ];
            } elseif ($current !== null) {
                // continuation of previous entry (multi-line exception body)
                $current['raw_rest'] .= $line;
            }
        }

        if ($current !== null) {
            $entries[] = $this->finalizeEntry($current);
        }

        // Newest first
        $entries = array_reverse($entries);

        if ($tailLimit !== null && count($entries) > $tailLimit * 4) {
            $entries = array_slice($entries, 0, $tailLimit * 4);
        }

        return $entries;
    }

    /**
     * Split the raw continuation into context (trailing JSON) and trace (stack).
     */
    private function finalizeEntry(array $entry): array
    {
        $rest = $entry['raw_rest'];
        unset($entry['raw_rest']);
        $entry['context'] = '';
        $entry['trace'] = '';

        $rest = trim($rest);
        if ($rest === '') {
            return $entry;
        }

        // Laravel exception logs look like:
        //   message {"context":"json"}
        //   [stacktrace]
        //   #0 /path/to/File.php(123): method()
        //   ...
        //   "}
        // The context JSON may actually be inline on the message line, so first
        // try to lift trailing JSON off the message itself.
        if (preg_match('/^(.*?)(\{.*\}|\[.*\])\s*$/s', $entry['message'], $mm)) {
            $entry['message'] = rtrim($mm[1]);
            $entry['context'] = $mm[2];
        }

        // Split rest: before "[stacktrace]" marker → append to context,
        // after → trace.
        if (str_contains($rest, '[stacktrace]')) {
            [$ctxPart, $tracePart] = explode('[stacktrace]', $rest, 2);
            $entry['context'] = trim($entry['context'] . "\n" . $ctxPart);
            $entry['trace'] = trim($tracePart);
        } else {
            // No explicit marker. If lines start with '#N ' assume trace.
            if (preg_match('/^#\d+\s/m', $rest)) {
                $entry['trace'] = $rest;
            } else {
                $entry['context'] = trim($entry['context'] . "\n" . $rest);
            }
        }

        return $entry;
    }

    private function getLevelColor(string $level): string
    {
        return match ($level) {
            'emergency', 'alert', 'critical', 'error' => 'danger',
            'warning' => 'warning',
            'notice', 'info' => 'info',
            'debug' => 'gray',
            default => 'gray',
        };
    }

    public function deleteLogFile(string $filename): bool
    {
        $path = storage_path('logs/' . basename($filename));
        if (!File::exists($path)) {
            return false;
        }
        Cache::forget('log_viewer:files');
        return File::delete($path);
    }

    public function clearLogFile(string $filename): bool
    {
        $path = storage_path('logs/' . basename($filename));
        if (!File::exists($path)) {
            return false;
        }
        Cache::forget('log_viewer:files');
        return File::put($path, '') !== false;
    }

    public function getFilePath(string $filename): ?string
    {
        $path = storage_path('logs/' . basename($filename));
        return File::exists($path) ? $path : null;
    }

    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = 0;
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }
        return round($bytes, 2) . ' ' . $units[$i];
    }
}

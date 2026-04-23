<?php

namespace App\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use SplFileInfo;

class LogViewerService
{
    /**
     * Get all Laravel log files with their metadata.
     *
     * @return array
     */
    public function getLogFiles(): array
    {
        $logPath = storage_path('logs');
        $files = [];

        if (!is_dir($logPath)) {
            return $files;
        }

        $logFiles = File::files($logPath);

        foreach ($logFiles as $file) {
            if ($file->getExtension() !== 'log') {
                continue;
            }

            $files[] = [
                'name' => $file->getFilename(),
                'path' => $file->getPathname(),
                'size' => $this->formatBytes($file->getSize()),
                'size_bytes' => $file->getSize(),
                'modified' => date('Y-m-d H:i:s', $file->getMTime()),
                'modified_at' => $file->getMTime(),
            ];
        }

        // Sort by modification time, newest first
        usort($files, fn ($a, $b) => $b['modified_at'] <=> $a['modified_at']);

        return $files;
    }

    /**
     * Parse a Laravel log file and extract entries.
     *
     * @param string $filename
     * @param int $limit
     * @param string|null $level Filter by log level (debug, info, warning, error, critical, alert, emergency)
     * @return array
     */
    public function parseLogFile(string $filename, int $limit = 100, ?string $level = null): array
    {
        $logPath = storage_path('logs/' . $filename);

        if (!File::exists($logPath)) {
            return [];
        }

        $content = File::get($logPath);
        $entries = $this->extractLogEntries($content, $level);

        // Limit results
        return array_slice($entries, 0, $limit);
    }

    /**
     * Tail a log file (get last N lines).
     *
     * @param string $filename
     * @param int $lines
     * @return array
     */
    public function tailLogFile(string $filename, int $lines = 50): array
    {
        $logPath = storage_path('logs/' . $filename);

        if (!File::exists($logPath)) {
            return [];
        }

        $content = File::get($logPath);
        $entries = $this->extractLogEntries($content);

        // Get last N entries
        return array_slice($entries, -$lines);
    }

    /**
     * Search logs for a specific term.
     *
     * @param string $search
     * @param int $limit
     * @param string|null $level
     * @return array
     */
    public function searchLogs(string $search, int $limit = 100, ?string $level = null): array
    {
        $files = $this->getLogFiles();
        $results = [];

        foreach ($files as $file) {
            $entries = $this->parseLogFile($file['name'], 1000, $level);

            foreach ($entries as $entry) {
                if (stripos($entry['message'], $search) !== false ||
                    stripos($entry['context'], $search) !== false) {
                    $results[] = array_merge($entry, ['file' => $file['name']]);

                    if (count($results) >= $limit) {
                        return $results;
                    }
                }
            }
        }

        return $results;
    }

    /**
     * Extract log entries from raw log content using regex.
     *
     * @param string $content
     * @param string|null $levelFilter
     * @return array
     */
    private function extractLogEntries(string $content, ?string $levelFilter = null): array
    {
        $entries = [];

        // Laravel log format: [YYYY-MM-DD HH:MM:SS] environment.LEVEL: message {context}
        // Pattern matches both single-line and multi-line log entries
        $pattern = '/\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\]\s+(\w+)\.(\w+):\s+(.+?)(?=\[\d{4}-\d{2}-\d{2}|$)/s';

        preg_match_all($pattern, $content, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            $timestamp = $match[1];
            $environment = $match[2];
            $level = strtolower($match[3]);
            $message = trim($match[4]);

            // Extract context (JSON at end) if present
            $context = '';
            if (preg_match('/(\{.*\}|\[.*\])$/s', $message, $contextMatch)) {
                $context = $contextMatch[1];
                $message = trim(substr($message, 0, -strlen($context)));
            }

            // Apply level filter
            if ($levelFilter && $level !== strtolower($levelFilter)) {
                continue;
            }

            $entries[] = [
                'timestamp' => $timestamp,
                'environment' => $environment,
                'level' => $level,
                'level_color' => $this->getLevelColor($level),
                'message' => $message,
                'context' => $context,
            ];
        }

        // Reverse to show newest first
        return array_reverse($entries);
    }

    /**
     * Get color for log level.
     *
     * @param string $level
     * @return string
     */
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

    /**
     * Format bytes to human readable string.
     *
     * @param int $bytes
     * @return string
     */
    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, 2) . ' ' . $units[$i];
    }

    /**
     * Delete a log file.
     *
     * @param string $filename
     * @return bool
     */
    public function deleteLogFile(string $filename): bool
    {
        $logPath = storage_path('logs/' . $filename);

        if (!File::exists($logPath)) {
            return false;
        }

        return File::delete($logPath);
    }

    /**
     * Clear (truncate) a log file.
     *
     * @param string $filename
     * @return bool
     */
    public function clearLogFile(string $filename): bool
    {
        $logPath = storage_path('logs/' . $filename);

        if (!File::exists($logPath)) {
            return false;
        }

        return File::put($logPath, '') !== false;
    }

    /**
     * Download a log file.
     *
     * @param string $filename
     * @return \Symfony\Component\HttpFoundation\StreamedResponse|null
     */
    public function downloadLogFile(string $filename)
    {
        $logPath = storage_path('logs/' . $filename);

        if (!File::exists($logPath)) {
            return null;
        }

        return response()->streamDownload(function () use ($logPath) {
            $stream = fopen($logPath, 'r');
            fpassthru($stream);
            fclose($stream);
        }, $filename, [
            'Content-Type' => 'text/plain',
        ]);
    }
}

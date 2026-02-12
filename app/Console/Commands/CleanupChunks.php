<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CleanupChunks extends Command
{
    protected $signature = 'uploads:cleanup-chunks {--hours=24 : Remove chunks older than this many hours}';
    protected $description = 'Remove abandoned chunk upload files';

    public function handle(): int
    {
        $hours = (int) $this->option('hours');
        $chunksDir = storage_path('app/chunks');

        if (!is_dir($chunksDir)) {
            $this->info('No chunks directory found. Nothing to clean.');
            return self::SUCCESS;
        }

        $cutoff = now()->subHours($hours)->timestamp;
        $removedFiles = 0;
        $removedDirs = 0;

        // Each upload gets a subdirectory named by uploadId
        foreach (scandir($chunksDir) as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $path = $chunksDir . DIRECTORY_SEPARATOR . $entry;

            if (is_dir($path)) {
                // Check if the directory is older than the cutoff
                $dirTime = filemtime($path);
                if ($dirTime < $cutoff) {
                    $this->removeDirectory($path);
                    $removedDirs++;
                }
            } elseif (is_file($path) && filemtime($path) < $cutoff) {
                unlink($path);
                $removedFiles++;
            }
        }

        $this->info("Chunk cleanup complete: removed {$removedDirs} directories and {$removedFiles} files older than {$hours}h.");

        if ($removedDirs > 0 || $removedFiles > 0) {
            Log::info('Chunk cleanup', [
                'removed_dirs' => $removedDirs,
                'removed_files' => $removedFiles,
                'hours_threshold' => $hours,
            ]);
        }

        return self::SUCCESS;
    }

    protected function removeDirectory(string $dir): void
    {
        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($items as $item) {
            if ($item->isDir()) {
                rmdir($item->getRealPath());
            } else {
                unlink($item->getRealPath());
            }
        }

        rmdir($dir);
    }
}

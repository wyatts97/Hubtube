<?php

namespace App\Console\Commands;

use App\Services\StorageManager;
use Illuminate\Console\Command;

class CleanupTempStorage extends Command
{
    protected $signature = 'storage:cleanup';
    protected $description = 'Clean up temporary files created during video processing';

    public function handle(): int
    {
        $this->info('Cleaning up temporary storage files...');

        StorageManager::cleanupTemp();

        // Also clean up old watermark preview files (older than 24h)
        $watermarkDir = storage_path('app/public/watermarks');
        if (is_dir($watermarkDir)) {
            $count = 0;
            foreach (glob($watermarkDir . '/watermark_preview*.mp4') as $file) {
                if (filemtime($file) < now()->subDay()->timestamp) {
                    unlink($file);
                    $count++;
                }
            }
            if ($count > 0) {
                $this->info("Removed {$count} old watermark preview file(s).");
            }
        }

        $this->info('Temp storage cleanup complete.');

        return self::SUCCESS;
    }
}

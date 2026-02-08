<?php

namespace App\Console\Commands;

use App\Models\Video;
use App\Services\StorageManager;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class MigrateStorageCommand extends Command
{
    protected $signature = 'storage:migrate
        {--from=public : Source disk name}
        {--to=wasabi : Target disk name}
        {--limit=0 : Max videos to migrate (0 = all)}
        {--dry-run : Show what would be migrated without actually doing it}';

    protected $description = 'Migrate video files from one storage disk to another (e.g. local → Wasabi)';

    private int $uploaded = 0;
    private int $failed = 0;
    private int $skipped = 0;

    public function handle(): int
    {
        $fromDisk = $this->option('from');
        $toDisk = $this->option('to');
        $limit = (int) $this->option('limit');
        $dryRun = $this->option('dry-run');

        if ($fromDisk === $toDisk) {
            $this->error('Source and target disks cannot be the same.');
            return self::FAILURE;
        }

        // Validate target disk is configured
        if (!config("filesystems.disks.{$toDisk}")) {
            $this->error("Disk '{$toDisk}' is not configured in filesystems.php.");
            return self::FAILURE;
        }

        // Test connection to target
        if (!$dryRun) {
            $this->info("Testing connection to '{$toDisk}'...");
            $result = StorageManager::testConnection($toDisk);
            if (!$result['success']) {
                $this->error("Connection test failed: {$result['message']}");
                return self::FAILURE;
            }
            $this->info("✓ Connected to {$toDisk}");
        }

        // Find videos on the source disk
        $query = Video::where('storage_disk', $fromDisk)
            ->whereNotNull('video_path')
            ->where('video_path', '!=', '');

        if ($limit > 0) {
            $query->limit($limit);
        }

        $videos = $query->get();
        $total = $videos->count();

        if ($total === 0) {
            $this->info("No videos found on '{$fromDisk}' disk.");
            return self::SUCCESS;
        }

        $this->info(($dryRun ? '[DRY RUN] ' : '') . "Found {$total} videos to migrate from '{$fromDisk}' → '{$toDisk}'");

        if (!$dryRun && !$this->confirm("Proceed with migrating {$total} videos?")) {
            return self::SUCCESS;
        }

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        foreach ($videos as $video) {
            if ($dryRun) {
                $this->newLine();
                $this->line("  Would migrate: {$video->title} (ID: {$video->id})");
                $bar->advance();
                continue;
            }

            $this->migrateVideo($video, $fromDisk, $toDisk);
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $this->info("Migration complete:");
        $this->line("  Uploaded: {$this->uploaded}");
        $this->line("  Failed:   {$this->failed}");
        $this->line("  Skipped:  {$this->skipped}");

        return $this->failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    protected function migrateVideo(Video $video, string $fromDisk, string $toDisk): void
    {
        $localDisk = Storage::disk($fromDisk);
        $filesToUpload = [];

        // Collect all file paths for this video
        if ($video->video_path && $localDisk->exists($video->video_path)) {
            $filesToUpload[] = $video->video_path;
        }

        if ($video->thumbnail && $localDisk->exists($video->thumbnail)) {
            $filesToUpload[] = $video->thumbnail;
        }

        if ($video->preview_path && $localDisk->exists($video->preview_path)) {
            $filesToUpload[] = $video->preview_path;
        }

        if ($video->scrubber_vtt_path && $localDisk->exists($video->scrubber_vtt_path)) {
            $filesToUpload[] = $video->scrubber_vtt_path;
        }

        // Upload processed directory files
        $videoDir = "videos/{$video->user_id}/{$video->uuid}";
        if ($localDisk->exists($videoDir)) {
            $allFiles = $localDisk->allFiles($videoDir);
            foreach ($allFiles as $file) {
                if (!in_array($file, $filesToUpload)) {
                    $filesToUpload[] = $file;
                }
            }
        }

        if (empty($filesToUpload)) {
            $this->skipped++;
            return;
        }

        $videoFailed = false;
        foreach ($filesToUpload as $filePath) {
            $localPath = $localDisk->path($filePath);
            if (!StorageManager::uploadLocalFile($localPath, $filePath, $toDisk)) {
                $this->newLine();
                $this->warn("  Failed to upload: {$filePath}");
                $videoFailed = true;
            }
        }

        if ($videoFailed) {
            $this->failed++;
            Log::warning('storage:migrate - partial failure', [
                'video_id' => $video->id,
                'title' => $video->title,
            ]);
        } else {
            $video->update(['storage_disk' => $toDisk]);
            $this->uploaded++;
        }
    }
}

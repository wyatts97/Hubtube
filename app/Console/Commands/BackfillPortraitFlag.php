<?php

namespace App\Console\Commands;

use App\Models\Video;
use App\Services\FfmpegService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class BackfillPortraitFlag extends Command
{
    protected $signature = 'videos:backfill-portrait {--dry-run : Preview changes without saving}';
    protected $description = 'Detect portrait videos and set the is_portrait flag based on video dimensions';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');

        $videos = Video::where('is_portrait', false)
            ->where('status', 'processed')
            ->get();

        $this->info("Found {$videos->count()} processed videos to check.");

        $updated = 0;
        $skipped = 0;
        $errors = 0;

        foreach ($videos as $video) {
            try {
                $isPortrait = $this->detectPortrait($video);

                if ($isPortrait === null) {
                    $skipped++;
                    continue;
                }

                if ($isPortrait) {
                    if ($dryRun) {
                        $this->line("  [DRY RUN] Would mark as portrait: #{$video->id} \"{$video->title}\"");
                    } else {
                        $video->update(['is_portrait' => true]);
                        $this->line("  Marked as portrait: #{$video->id} \"{$video->title}\"");
                    }
                    $updated++;
                }
            } catch (\Exception $e) {
                $this->error("  Error checking #{$video->id}: {$e->getMessage()}");
                $errors++;
            }
        }

        $this->newLine();
        $this->info("Done. Portrait: {$updated}, Skipped: {$skipped}, Errors: {$errors}");

        if ($dryRun && $updated > 0) {
            $this->warn('Run without --dry-run to apply changes.');
        }

        return self::SUCCESS;
    }

    /**
     * Detect if a video is portrait. Returns true/false, or null if undetermined.
     */
    protected function detectPortrait(Video $video): ?bool
    {
        // Method 1: Use ffprobe on local video file
        if ($video->video_path) {
            $disk = $video->storage_disk ?? 'public';
            if ($disk === 'public' && Storage::disk('public')->exists($video->video_path)) {
                $localPath = Storage::disk('public')->path($video->video_path);
                $dims = $this->probeVideoDimensions($localPath);
                if ($dims) {
                    return $dims['height'] > $dims['width'];
                }
            }
        }

        // Method 2: Check preview WebP dimensions using getimagesize
        if ($video->preview_path) {
            $disk = $video->storage_disk ?? 'public';
            if ($disk === 'public' && Storage::disk('public')->exists($video->preview_path)) {
                $previewPath = Storage::disk('public')->path($video->preview_path);
                $size = @getimagesize($previewPath);
                if ($size && $size[0] > 0 && $size[1] > 0) {
                    return $size[1] > $size[0]; // height > width
                }
            }
        }

        // Method 3: Check thumbnail dimensions
        if ($video->thumbnail) {
            $disk = $video->storage_disk ?? 'public';
            if ($disk === 'public' && Storage::disk('public')->exists($video->thumbnail)) {
                $thumbPath = Storage::disk('public')->path($video->thumbnail);
                $size = @getimagesize($thumbPath);
                if ($size && $size[0] > 0 && $size[1] > 0) {
                    return $size[1] > $size[0];
                }
            }
        }

        return null;
    }

    protected function probeVideoDimensions(string $path): ?array
    {
        if (!file_exists($path)) {
            return null;
        }

        $ffprobe = FfmpegService::ffprobePath();
        $cmd = "{$ffprobe} -v quiet -print_format json -show_streams " . escapeshellarg($path);
        $output = shell_exec($cmd);

        if (!$output) {
            return null;
        }

        $info = json_decode($output, true);

        foreach ($info['streams'] ?? [] as $stream) {
            if (($stream['codec_type'] ?? '') === 'video') {
                $width = $stream['width'] ?? 0;
                $height = $stream['height'] ?? 0;
                if ($width > 0 && $height > 0) {
                    return ['width' => $width, 'height' => $height];
                }
            }
        }

        return null;
    }
}

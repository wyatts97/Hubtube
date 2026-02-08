<?php

namespace App\Console\Commands;

use App\Models\Video;
use App\Services\BunnyStreamService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class BunnyDownloadSingle extends Command
{
    protected $signature = 'bunny:download-single {videoId} {--disk=public}';
    protected $description = 'Download a single Bunny Stream video in the background. Downloads locally, then ProcessVideoJob handles processing + cloud offload.';

    private const CACHE_DOWNLOADING = 'bunny_migration_downloading';
    private const CACHE_CURRENT_VIDEO = 'bunny_migration_current_video';
    private const CACHE_RESULT = 'bunny_migration_result';

    public function handle(): int
    {
        $videoId = (int) $this->argument('videoId');

        $video = Video::find($videoId);

        if (!$video || !$video->is_embedded) {
            Log::warning('BunnyDownloadSingle: video not found or not embedded', ['id' => $videoId]);
            Cache::forget(self::CACHE_DOWNLOADING);
            Cache::put(self::CACHE_RESULT, [
                'success' => false,
                'video_id' => $videoId,
                'title' => 'Unknown',
                'bunny_id' => '',
                'error' => 'Video not found or not embedded',
            ], 300);
            return 1;
        }

        $this->info("Downloading: {$video->title} (ID: {$video->id})");

        $service = new BunnyStreamService();
        // Always downloads to local first; ProcessVideoJob handles processing + cloud offload
        $result = $service->downloadVideo($video);

        // Store result for the Livewire page to pick up
        Cache::put(self::CACHE_RESULT, [
            'success' => $result['success'],
            'video_id' => $video->id,
            'title' => $video->title,
            'bunny_id' => $video->source_video_id ?? '',
            'error' => $result['error'] ?? null,
        ], 300);

        // Clear the downloading flag so the next video can start
        Cache::forget(self::CACHE_DOWNLOADING);
        Cache::forget(self::CACHE_CURRENT_VIDEO);

        if ($result['success']) {
            $this->info("Success: {$result['video_path']}");
            return 0;
        }

        $this->error("Failed: {$result['error']}");
        return 1;
    }
}

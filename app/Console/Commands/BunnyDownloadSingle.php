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

    private const CACHE_PREFIX = 'bunny_dl_';

    public function handle(): int
    {
        $videoId = (int) $this->argument('videoId');
        $slotKey = self::CACHE_PREFIX . $videoId;

        $video = Video::find($videoId);

        if (!$video || !$video->source_video_id) {
            Log::warning('BunnyDownloadSingle: video not found or has no source_video_id', ['id' => $videoId]);
            Cache::put($slotKey, [
                'done' => true,
                'success' => false,
                'video_id' => $videoId,
                'title' => $video->title ?? 'Unknown',
                'bunny_id' => '',
                'error' => 'Video not found or has no Bunny Stream ID',
            ], 600);
            return 1;
        }

        $this->info("Downloading: {$video->title} (ID: {$video->id}, Bunny: {$video->source_video_id})");

        $service = new BunnyStreamService();
        $result = $service->downloadVideo($video);

        // Store result for the Livewire page to pick up
        Cache::put($slotKey, [
            'done' => true,
            'success' => $result['success'],
            'video_id' => $video->id,
            'title' => $video->title,
            'bunny_id' => $video->source_video_id ?? '',
            'error' => $result['error'] ?? null,
            'video_path' => $result['video_path'] ?? null,
        ], 600);

        if ($result['success']) {
            $this->info("Success: {$result['video_path']}");
            return 0;
        }

        $this->error("Failed: {$result['error']}");
        return 1;
    }
}

<?php

namespace App\Jobs;

use App\Models\Video;
use App\Services\BunnyStreamService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class DownloadBunnyVideoJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;
    public int $timeout = 900; // 15 minutes per video
    public int $backoff = 60;

    public function __construct(
        public Video $video,
        public string $targetDisk = 'public'
    ) {}

    public function handle(BunnyStreamService $bunny): void
    {
        $result = $bunny->downloadVideo($this->video, $this->targetDisk);

        if (!$result['success']) {
            Log::error('DownloadBunnyVideoJob: failed', [
                'video_id' => $this->video->id,
                'error' => $result['error'],
            ]);
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('DownloadBunnyVideo: job failed permanently', [
            'video_id' => $this->video->id,
            'error' => $exception->getMessage(),
        ]);

        $this->video->update(['status' => 'failed']);
    }
}

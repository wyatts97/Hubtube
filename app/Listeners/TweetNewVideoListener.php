<?php

namespace App\Listeners;

use App\Events\VideoProcessed;
use App\Services\TwitterService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

class TweetNewVideoListener implements ShouldQueue
{
    public string $queue = 'default';

    public function handle(VideoProcessed $event): void
    {
        $video = $event->video;

        // Only tweet for successfully processed (published) videos
        if ($video->status !== 'processed') {
            return;
        }

        try {
            $service = app(TwitterService::class);
            $service->tweetNewVideo($video);
        } catch (\Throwable $e) {
            Log::warning('TweetNewVideoListener: Failed to tweet', [
                'video_id' => $video->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}

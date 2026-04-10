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
        $video->refresh();

        // Only tweet for successfully processed (published) videos
        if ($video->status !== 'processed') {
            return;
        }

        // Only tweet when the video is actually live — not just processed/scheduled.
        if (
            !$video->published_at ||
            !$video->is_approved ||
            $video->privacy !== 'public' ||
            $video->scheduled_at ||
            $video->queue_order !== null ||
            $video->requires_schedule
        ) {
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

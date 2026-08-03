<?php

namespace App\Listeners;

use App\Events\VideoProcessed;
use App\Notifications\VideoProcessedNotification;

class NotifyVideoProcessed
{
    public function handle(VideoProcessed $event): void
    {
        $video = $event->video;
        $video->refresh();

        // Suppress notifications entirely for bulk-uploaded videos (flag persists on the model
        // so scheduled publishes also stay silent).
        if ($event->suppressNotifications || $video->suppress_notifications) {
            return;
        }

        // Only notify when the video is actually live.
        if (
            !$video->published_at ||
            !$video->is_approved ||
            $video->scheduled_at ||
            $video->queue_order !== null ||
            $video->requires_schedule
        ) {
            return;
        }

        $video->loadMissing('user');

        if ($video->user) {
            $video->user->notify(new VideoProcessedNotification($video));
        }
    }
}

<?php

namespace App\Listeners;

use App\Events\VideoProcessed;
use App\Models\Notification;

class NotifyVideoProcessed
{
    public function handle(VideoProcessed $event): void
    {
        $video = $event->video;

        // Prevent duplicate notifications (e.g. from job retries or race conditions)
        $exists = Notification::where('user_id', $video->user_id)
            ->where('type', 'video_processed')
            ->where('data->video_id', $video->id)
            ->exists();

        if ($exists) {
            return;
        }

        Notification::create([
            'user_id' => $video->user_id,
            'type' => 'video_processed',
            'title' => 'Video Published',
            'message' => "Your video \"{$video->title}\" has been Published.",
            'data' => [
                'video_id' => $video->id,
                'video_slug' => $video->slug,
                'url' => "/{$video->slug}",
            ],
        ]);
    }
}

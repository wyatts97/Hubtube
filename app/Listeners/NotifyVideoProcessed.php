<?php

namespace App\Listeners;

use App\Events\VideoProcessed;
use App\Models\Notification;

class NotifyVideoProcessed
{
    public function handle(VideoProcessed $event): void
    {
        Notification::create([
            'user_id' => $event->video->user_id,
            'type' => 'video_processed',
            'title' => 'Video Ready',
            'message' => "Your video \"{$event->video->title}\" has been processed and is now live!",
            'data' => [
                'video_id' => $event->video->id,
                'video_slug' => $event->video->slug,
            ],
        ]);
    }
}

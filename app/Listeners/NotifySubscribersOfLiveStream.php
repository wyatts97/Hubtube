<?php

namespace App\Listeners;

use App\Events\LiveStreamStarted;
use App\Models\Notification;
use App\Models\Subscription;

class NotifySubscribersOfLiveStream
{
    public function handle(LiveStreamStarted $event): void
    {
        $subscribers = Subscription::where('channel_id', $event->liveStream->user_id)
            ->where('notifications_enabled', true)
            ->pluck('subscriber_id');

        $notifications = $subscribers->map(fn($subscriberId) => [
            'user_id' => $subscriberId,
            'from_user_id' => $event->liveStream->user_id,
            'type' => Notification::TYPE_LIVE_STARTED,
            'title' => 'Live Now!',
            'message' => "{$event->liveStream->user->username} is live: {$event->liveStream->title}",
            'data' => json_encode([
                'live_stream_id' => $event->liveStream->id,
            ]),
            'created_at' => now(),
            'updated_at' => now(),
        ])->toArray();

        Notification::insert($notifications);
    }
}

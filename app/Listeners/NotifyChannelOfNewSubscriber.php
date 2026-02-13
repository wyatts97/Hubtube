<?php

namespace App\Listeners;

use App\Events\NewSubscriber;
use App\Models\Notification;

class NotifyChannelOfNewSubscriber
{
    public function handle(NewSubscriber $event): void
    {
        // Prevent duplicate notifications (e.g. from event re-broadcast or race conditions)
        $exists = Notification::where('user_id', $event->subscription->channel_id)
            ->where('type', Notification::TYPE_NEW_SUBSCRIBER)
            ->where('data->subscriber_id', $event->subscription->subscriber_id)
            ->exists();

        if ($exists) {
            return;
        }

        Notification::create([
            'user_id' => $event->subscription->channel_id,
            'from_user_id' => $event->subscription->subscriber_id,
            'type' => Notification::TYPE_NEW_SUBSCRIBER,
            'title' => 'New Subscriber!',
            'message' => "{$event->subscription->subscriber->username} subscribed to your channel!",
            'data' => [
                'subscriber_id' => $event->subscription->subscriber_id,
            ],
        ]);
    }
}

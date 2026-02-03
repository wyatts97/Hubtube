<?php

namespace App\Listeners;

use App\Events\NewSubscriber;
use App\Models\Notification;

class NotifyChannelOfNewSubscriber
{
    public function handle(NewSubscriber $event): void
    {
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

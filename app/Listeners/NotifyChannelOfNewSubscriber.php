<?php

namespace App\Listeners;

use App\Events\NewSubscriber;
use App\Models\Notification;
use App\Models\User;
use App\Services\EmailService;

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

        $event->subscription->loadMissing(['subscriber', 'channel']);
        $subscriberName = $event->subscription->subscriber->username ?? 'Someone';

        Notification::create([
            'user_id' => $event->subscription->channel_id,
            'from_user_id' => $event->subscription->subscriber_id,
            'type' => Notification::TYPE_NEW_SUBSCRIBER,
            'title' => 'New Subscriber!',
            'message' => "{$subscriberName} subscribed to your channel!",
            'data' => [
                'subscriber_id' => $event->subscription->subscriber_id,
            ],
        ]);

        // Send email notification to channel owner
        $channelOwner = $event->subscription->channel ?? User::find($event->subscription->channel_id);
        if ($channelOwner) {
            EmailService::sendToUser('new-subscriber', $channelOwner->email, [
                'username' => $channelOwner->username,
                'subscriber_name' => $subscriberName,
                'channel_url' => url("/channel/{$channelOwner->username}"),
            ]);
        }
    }
}

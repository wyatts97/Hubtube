<?php

namespace App\Notifications;

use App\Models\Notification as NotificationModel;
use App\Models\Subscription;
use App\Notifications\Channels\CustomDatabaseChannel;
use App\Notifications\Channels\EmailServiceChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class NewSubscriberNotification extends Notification
{
    use Queueable;

    public function __construct(protected Subscription $subscription)
    {
    }

    public function via(object $notifiable): array
    {
        return [CustomDatabaseChannel::class, EmailServiceChannel::class];
    }

    public function toDatabase(object $notifiable): array
    {
        $subscriberName = $this->subscription->subscriber->username ?? 'Someone';

        return [
            'from_user_id' => $this->subscription->subscriber_id,
            'type' => NotificationModel::TYPE_NEW_SUBSCRIBER,
            'title' => 'New Subscriber!',
            'message' => "{$subscriberName} subscribed to your channel!",
            'data' => [
                'subscriber_id' => $this->subscription->subscriber_id,
            ],
            'dedupe' => ['data->subscriber_id', $this->subscription->subscriber_id],
        ];
    }

    public function toEmailService(object $notifiable): ?array
    {
        return [
            'template' => 'new-subscriber',
            'to' => $notifiable->email,
            'data' => [
                'username' => $notifiable->username,
                'subscriber_name' => $this->subscription->subscriber->username ?? 'Someone',
                'channel_url' => url("/channel/{$notifiable->username}"),
            ],
        ];
    }
}

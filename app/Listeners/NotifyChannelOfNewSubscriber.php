<?php

namespace App\Listeners;

use App\Events\NewSubscriber;
use App\Models\User;
use App\Notifications\NewSubscriberNotification;

class NotifyChannelOfNewSubscriber
{
    public function handle(NewSubscriber $event): void
    {
        $event->subscription->loadMissing(['subscriber', 'channel']);

        $channelOwner = $event->subscription->channel ?? User::find($event->subscription->channel_id);

        if ($channelOwner) {
            $channelOwner->notify(new NewSubscriberNotification($event->subscription));
        }
    }
}

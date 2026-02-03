<?php

namespace App\Listeners;

use App\Events\GiftSent;
use App\Models\Notification;

class ProcessGiftTransaction
{
    public function handle(GiftSent $event): void
    {
        Notification::create([
            'user_id' => $event->transaction->receiver_id,
            'from_user_id' => $event->transaction->sender_id,
            'type' => Notification::TYPE_GIFT_RECEIVED,
            'title' => 'Gift Received!',
            'message' => "{$event->transaction->sender->username} sent you a {$event->transaction->gift->name}!",
            'data' => [
                'gift_id' => $event->transaction->gift_id,
                'amount' => $event->transaction->receiver_amount,
                'live_stream_id' => $event->transaction->live_stream_id,
            ],
        ]);
    }
}

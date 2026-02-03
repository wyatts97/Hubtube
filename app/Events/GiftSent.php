<?php

namespace App\Events;

use App\Models\GiftTransaction;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class GiftSent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public GiftTransaction $transaction
    ) {}

    public function broadcastOn(): array
    {
        return [
            new Channel('live-stream.' . $this->transaction->live_stream_id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'gift.sent';
    }

    public function broadcastWith(): array
    {
        return [
            'gift' => $this->transaction->gift,
            'sender' => [
                'id' => $this->transaction->sender->id,
                'username' => $this->transaction->sender->username,
                'avatar' => $this->transaction->sender->avatar,
            ],
            'amount' => $this->transaction->amount,
        ];
    }
}

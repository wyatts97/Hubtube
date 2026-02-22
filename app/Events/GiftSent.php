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

    public int $tries = 1;
    public int $maxExceptions = 1;
    public bool $afterCommit = true;

    public function __construct(
        public GiftTransaction $transaction
    ) {}

    public function broadcastWhen(): bool
    {
        $host = config('broadcasting.connections.reverb.options.host',
                config('broadcasting.connections.pusher.options.host', 'localhost'));

        if (app()->environment('production') && in_array($host, ['localhost', '127.0.0.1'])) {
            return false;
        }

        return true;
    }

    public function failed(\Throwable $e): void
    {
        \Illuminate\Support\Facades\Log::warning('GiftSent broadcast failed (Reverb may be down): ' . $e->getMessage());
    }

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

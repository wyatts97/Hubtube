<?php

namespace App\Events;

use App\Models\Subscription;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class NewSubscriber implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Subscription $subscription
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('user.' . $this->subscription->channel_id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'new.subscriber';
    }

    public function broadcastWith(): array
    {
        return [
            'subscriber' => [
                'id' => $this->subscription->subscriber->id,
                'username' => $this->subscription->subscriber->username,
                'avatar' => $this->subscription->subscriber->avatar,
            ],
        ];
    }
}

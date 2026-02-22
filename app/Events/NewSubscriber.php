<?php

namespace App\Events;

use App\Models\Subscription;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class NewSubscriber implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public int $tries = 1;
    public int $maxExceptions = 1;
    public bool $afterCommit = true;

    public function __construct(
        public Subscription $subscription
    ) {}

    /**
     * Determine if the event should be broadcast.
     * Skip broadcasting entirely when Reverb/Pusher is not configured for production.
     */
    public function broadcastWhen(): bool
    {
        $host = config('broadcasting.connections.reverb.options.host',
                config('broadcasting.connections.pusher.options.host', 'localhost'));

        // Don't attempt to broadcast if still pointing at localhost in production
        if (app()->environment('production') && in_array($host, ['localhost', '127.0.0.1'])) {
            return false;
        }

        return true;
    }

    public function failed(\Throwable $e): void
    {
        Log::warning('NewSubscriber broadcast failed (Reverb may be down): ' . $e->getMessage());
    }

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
                'avatar' => $this->subscription->subscriber->avatar_url,
            ],
        ];
    }
}

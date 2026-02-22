<?php

namespace App\Events;

use App\Models\LiveStream;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class LiveStreamStarted implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public int $tries = 1;
    public int $maxExceptions = 1;
    public bool $afterCommit = true;

    public function __construct(
        public LiveStream $liveStream
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
        \Illuminate\Support\Facades\Log::warning('LiveStreamStarted broadcast failed (Reverb may be down): ' . $e->getMessage());
    }

    public function broadcastOn(): array
    {
        return [
            new Channel('live-streams'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'stream.started';
    }

    public function broadcastWith(): array
    {
        return [
            'stream' => [
                'id' => $this->liveStream->id,
                'title' => $this->liveStream->title,
                'thumbnail' => $this->liveStream->thumbnail,
                'user' => [
                    'id' => $this->liveStream->user->id,
                    'username' => $this->liveStream->user->username,
                    'avatar' => $this->liveStream->user->avatar,
                ],
            ],
        ];
    }
}

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

    public function __construct(
        public LiveStream $liveStream
    ) {}

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

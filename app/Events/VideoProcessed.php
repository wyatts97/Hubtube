<?php

namespace App\Events;

use App\Models\Video;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class VideoProcessed implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public int $tries = 1;
    public bool $afterCommit = true;

    public function __construct(
        public Video $video
    ) {}

    public function failed(\Throwable $e): void
    {
        \Illuminate\Support\Facades\Log::warning('VideoProcessed broadcast failed (Reverb may be down): ' . $e->getMessage());
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('user.' . $this->video->user_id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'video.processed';
    }
}

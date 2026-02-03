<?php

namespace App\Events;

use App\Models\Video;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class VideoUploaded
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Video $video
    ) {}
}

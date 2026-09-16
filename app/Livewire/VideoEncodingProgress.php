<?php

namespace App\Livewire;

use App\Models\Video;
use Livewire\Component;

/**
 * Live encoding progress on the admin video page.
 *
 * Polls only while there is work in progress, so a finished video's edit
 * page makes no background requests.
 */
class VideoEncodingProgress extends Component
{
    public int $videoId;

    public function mount(int $videoId): void
    {
        $this->videoId = $videoId;
    }

    public function render()
    {
        $video = Video::with('encodings.profile')->find($this->videoId);

        return view('livewire.video-encoding-progress', [
            'video' => $video,
            'active' => $video?->isEncoding() ?? false,
            'hasFailures' => $video?->encodings->contains('status', 'failed') ?? false,
        ]);
    }
}

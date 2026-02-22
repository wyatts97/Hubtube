<?php

namespace App\Console\Commands;

use App\Models\Video;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class PublishScheduledVideos extends Command
{
    protected $signature = 'videos:publish-scheduled';
    protected $description = 'Publish videos that have reached their scheduled publish time';

    public function handle(): int
    {
        $videos = Video::whereNotNull('scheduled_at')
            ->where('scheduled_at', '<=', now())
            ->where('status', 'processed')
            ->where('is_approved', false)
            ->get();

        if ($videos->isEmpty()) {
            $this->info('No scheduled videos to publish.');
            return self::SUCCESS;
        }

        $count = 0;
        foreach ($videos as $video) {
            $video->update([
                'is_approved' => true,
                'published_at' => $video->scheduled_at,
                'scheduled_at' => null,
            ]);
            $count++;
            Log::info("Published scheduled video: {$video->title} (ID: {$video->id})");
        }

        $this->info("Published {$count} scheduled video(s).");
        return self::SUCCESS;
    }
}

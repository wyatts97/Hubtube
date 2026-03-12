<?php

namespace App\Console\Commands;

use App\Events\VideoProcessed;
use App\Models\Notification;
use App\Models\Video;
use App\Services\VideoService;
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
            ->whereNull('published_at')
            ->get();

        if ($videos->isEmpty()) {
            $this->info('No scheduled videos to publish.');
            return self::SUCCESS;
        }

        $count = 0;
        foreach ($videos as $video) {
            $video->update([
                'is_approved' => true,
                'published_at' => $video->scheduled_at ?? now(),
                'scheduled_at' => null,
                'queue_order' => null,
                'requires_schedule' => false,
            ]);
            $count++;
            Log::info("Published scheduled video: {$video->title} (ID: {$video->id})");

            // Now that the video is actually live, send the "published" notification.
            // This was deferred from ProcessVideoJob to avoid premature emails for scheduled videos.
            $alreadyNotified = Notification::where('user_id', $video->user_id)
                ->where('type', 'video_processed')
                ->where('data->video_id', $video->id)
                ->exists();

            if (!$alreadyNotified) {
                event(new VideoProcessed($video));
            }
        }

        // It is possible publishing a video left a gap at the top of the queue order,
        // so we can trigger a recalculation to shift the remaining ones up.
        app(VideoService::class)->recalculateScheduleQueue();

        $this->info("Published {$count} scheduled video(s).");
        return self::SUCCESS;
    }
}

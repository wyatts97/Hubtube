<?php

namespace App\Notifications;

use App\Models\Notification as NotificationModel;
use App\Models\User;
use App\Models\Video;
use App\Notifications\Channels\CustomDatabaseChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * In-app only (no email — too high-frequency to justify inbox noise).
 */
class VideoLikeNotification extends Notification
{
    use Queueable;

    public function __construct(protected Video $video, protected User $liker)
    {
    }

    public function via(object $notifiable): array
    {
        return [CustomDatabaseChannel::class];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'from_user_id' => $this->liker->id,
            'type' => NotificationModel::TYPE_VIDEO_LIKE,
            'title' => 'New Like',
            'message' => "{$this->liker->username} liked your video \"{$this->video->title}\"",
            'data' => [
                'video_id' => $this->video->id,
                'video_slug' => $this->video->slug,
                'liker_id' => $this->liker->id,
            ],
            // Avoid spamming duplicate rows for the same liker+video combo
            'dedupe' => [
                ['data->video_id', $this->video->id],
                ['data->liker_id', $this->liker->id],
            ],
        ];
    }
}

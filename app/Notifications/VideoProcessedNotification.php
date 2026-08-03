<?php

namespace App\Notifications;

use App\Models\Notification as NotificationModel;
use App\Models\Video;
use App\Notifications\Channels\CustomDatabaseChannel;
use App\Notifications\Channels\EmailServiceChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class VideoProcessedNotification extends Notification
{
    use Queueable;

    public function __construct(protected Video $video)
    {
    }

    public function via(object $notifiable): array
    {
        return [CustomDatabaseChannel::class, EmailServiceChannel::class];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => NotificationModel::TYPE_VIDEO_PROCESSED,
            'title' => 'Video Published',
            'message' => "Your video \"{$this->video->title}\" has been Published.",
            'data' => [
                'video_id' => $this->video->id,
                'video_slug' => $this->video->slug,
                'url' => "/{$this->video->slug}",
            ],
            'dedupe' => ['data->video_id', $this->video->id],
        ];
    }

    public function toEmailService(object $notifiable): ?array
    {
        return [
            'template' => 'video-published',
            'to' => $notifiable->email,
            'data' => [
                'username' => $notifiable->username,
                'video_title' => $this->video->title,
                'video_url' => url("/{$this->video->slug}"),
            ],
        ];
    }
}

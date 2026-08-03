<?php

namespace App\Notifications;

use App\Models\Notification as NotificationModel;
use App\Models\Video;
use App\Notifications\Channels\CustomDatabaseChannel;
use App\Notifications\Channels\EmailServiceChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class VideoRejectedNotification extends Notification
{
    use Queueable;

    public function __construct(protected Video $video, protected ?string $reason = null)
    {
    }

    public function via(object $notifiable): array
    {
        return [CustomDatabaseChannel::class, EmailServiceChannel::class];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => NotificationModel::TYPE_VIDEO_REJECTED,
            'title' => 'Video Rejected',
            'message' => "Your video \"{$this->video->title}\" was rejected" . ($this->reason ? ": {$this->reason}" : '.'),
            'data' => [
                'video_id' => $this->video->id,
                'reason' => $this->reason,
            ],
        ];
    }

    public function toEmailService(object $notifiable): ?array
    {
        return [
            'template' => 'video-rejected',
            'to' => $notifiable->email,
            'data' => [
                'username' => $notifiable->username,
                'video_title' => $this->video->title,
                'reason' => $this->reason ?? 'Please review our community guidelines.',
            ],
        ];
    }
}

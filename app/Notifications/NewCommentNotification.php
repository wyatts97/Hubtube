<?php

namespace App\Notifications;

use App\Models\Comment;
use App\Models\Notification as NotificationModel;
use App\Notifications\Channels\CustomDatabaseChannel;
use App\Notifications\Channels\EmailServiceChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class NewCommentNotification extends Notification
{
    use Queueable;

    public function __construct(protected Comment $comment)
    {
    }

    public function via(object $notifiable): array
    {
        return [CustomDatabaseChannel::class, EmailServiceChannel::class];
    }

    public function toDatabase(object $notifiable): array
    {
        $video = $this->comment->video;
        $commenterName = $this->comment->user->username ?? 'Someone';

        return [
            'from_user_id' => $this->comment->user_id,
            'type' => NotificationModel::TYPE_NEW_COMMENT,
            'title' => 'New Comment',
            'message' => "{$commenterName} commented on your video \"{$video->title}\"",
            'data' => [
                'video_id' => $video->id,
                'video_slug' => $video->slug,
                'comment_id' => $this->comment->id,
            ],
        ];
    }

    public function toEmailService(object $notifiable): ?array
    {
        $video = $this->comment->video;

        return [
            'template' => 'new-comment',
            'to' => $notifiable->email,
            'data' => [
                'username' => $notifiable->username,
                'commenter_name' => $this->comment->user->username ?? 'Someone',
                'video_title' => $video->title,
                'comment_content' => $this->comment->content,
                'video_url' => url("/{$video->slug}"),
            ],
        ];
    }
}

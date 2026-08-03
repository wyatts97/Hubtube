<?php

namespace App\Notifications;

use App\Models\Comment;
use App\Models\Notification as NotificationModel;
use App\Notifications\Channels\CustomDatabaseChannel;
use App\Notifications\Channels\EmailServiceChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class CommentReplyNotification extends Notification
{
    use Queueable;

    public function __construct(protected Comment $reply, protected Comment $parentComment)
    {
    }

    public function via(object $notifiable): array
    {
        return [CustomDatabaseChannel::class, EmailServiceChannel::class];
    }

    public function toDatabase(object $notifiable): array
    {
        $video = $this->reply->video;
        $replierName = $this->reply->user->username ?? 'Someone';

        return [
            'from_user_id' => $this->reply->user_id,
            'type' => NotificationModel::TYPE_COMMENT_REPLY,
            'title' => 'New Reply',
            'message' => "{$replierName} replied to your comment on \"{$video->title}\"",
            'data' => [
                'video_id' => $video->id,
                'video_slug' => $video->slug,
                'comment_id' => $this->parentComment->id,
                'reply_id' => $this->reply->id,
            ],
        ];
    }

    public function toEmailService(object $notifiable): ?array
    {
        $video = $this->reply->video;

        return [
            'template' => 'comment-reply',
            'to' => $notifiable->email,
            'data' => [
                'username' => $notifiable->username,
                'replier_name' => $this->reply->user->username ?? 'Someone',
                'video_title' => $video->title,
                'reply_content' => $this->reply->content,
                'video_url' => url("/{$video->slug}"),
            ],
        ];
    }
}

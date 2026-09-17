<?php

namespace App\Notifications;

use App\Models\Comment;
use App\Models\Notification as NotificationModel;
use App\Notifications\Channels\CustomDatabaseChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Someone wrote @you in a comment.
 *
 * In-app only, deliberately: mentions are the easiest notification to send in
 * bulk, and there is no admin-editable email template for them.
 */
class CommentMentionNotification extends Notification
{
    use Queueable;

    public function __construct(protected Comment $comment) {}

    public function via(object $notifiable): array
    {
        return [CustomDatabaseChannel::class];
    }

    public function toDatabase(object $notifiable): array
    {
        $video = $this->comment->video;
        $authorName = $this->comment->user->username ?? 'Someone';

        return [
            'from_user_id' => $this->comment->user_id,
            'type' => NotificationModel::TYPE_COMMENT_MENTION,
            'title' => 'You were mentioned',
            'message' => "{$authorName} mentioned you in a comment on \"{$video->title}\"",
            'data' => [
                'video_id' => $video->id,
                'video_slug' => $video->slug,
                'comment_id' => $this->comment->id,
            ],
        ];
    }
}

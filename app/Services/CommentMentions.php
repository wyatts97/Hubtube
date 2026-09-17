<?php

namespace App\Services;

use App\Models\Comment;
use App\Models\User;
use App\Notifications\CommentMentionNotification;

/**
 * @username mentions inside comment text.
 *
 * Only mentions that resolve to a real account notify anyone, and the front end
 * links exactly the same set: it renders the usernames this class returns, so
 * an @ in front of any other word stays plain text on both sides.
 */
class CommentMentions
{
    /**
     * Usernames are letters, digits, dots, dashes and underscores.
     *
     * Kept in step with the username rules in the registration request; the
     * front end has the same expression in useMentions.js.
     */
    public const PATTERN = '/@([A-Za-z0-9._-]{3,30})/';

    /** No more than this many people are notified per comment. */
    public const MAX_PER_COMMENT = 5;

    /** Distinct, lower-cased usernames mentioned in $content. */
    public function usernames(string $content): array
    {
        preg_match_all(self::PATTERN, $content, $matches);

        $usernames = array_map('mb_strtolower', $matches[1] ?? []);

        return array_values(array_unique($usernames));
    }

    /**
     * Notify everyone mentioned in $comment.
     *
     * $skipUserIds carries whoever was already told about this comment by
     * another route — its author, the video's owner, the parent comment's
     * author — so a mention cannot produce a second notification for the same
     * comment.
     *
     * Mentions notify in-app only: there is no admin-editable email template
     * for them, and an email per mention is the noisiest possible default.
     *
     * @param  array<int>  $skipUserIds
     * @return int how many people were notified
     */
    public function notify(Comment $comment, array $skipUserIds = []): int
    {
        $usernames = $this->usernames($comment->content);

        if ($usernames === []) {
            return 0;
        }

        $users = User::query()
            ->where(function ($query) use ($usernames) {
                foreach ($usernames as $username) {
                    $query->orWhereRaw('LOWER(username) = ?', [$username]);
                }
            })
            ->whereNotIn('id', array_filter($skipUserIds))
            ->limit(self::MAX_PER_COMMENT)
            ->get();

        foreach ($users as $user) {
            $user->notify(new CommentMentionNotification($comment));
        }

        return $users->count();
    }
}

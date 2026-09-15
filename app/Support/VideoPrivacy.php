<?php

namespace App\Support;

use App\Models\Setting;
use App\Models\User;
use App\Models\Video;

/**
 * Which privacy values a user may give a video.
 *
 * Public is always available. Unlisted and private are each behind their own
 * admin toggle, both off by default, so a fresh install behaves exactly as it
 * did before users could choose: everything they upload is public. Admins are
 * never restricted.
 *
 * A video's current value always stays selectable, so switching a toggle off
 * later never forces an owner to change a video they already made private just
 * to save an unrelated edit.
 */
class VideoPrivacy
{
    public const PUBLIC = 'public';

    public const UNLISTED = 'unlisted';

    public const PRIVATE = 'private';

    public const ALL = [self::PUBLIC, self::UNLISTED, self::PRIVATE];

    /** @return list<string> */
    public static function allowedFor(?User $user, ?Video $video = null): array
    {
        if ($user?->is_admin) {
            return self::ALL;
        }

        $allowed = [self::PUBLIC];

        if ((bool) Setting::get('allow_unlisted_uploads', false)) {
            $allowed[] = self::UNLISTED;
        }

        if ((bool) Setting::get('allow_private_uploads', false)) {
            $allowed[] = self::PRIVATE;
        }

        if ($video && in_array($video->privacy, self::ALL, true) && ! in_array($video->privacy, $allowed, true)) {
            $allowed[] = $video->privacy;
        }

        // Keep a stable public → unlisted → private order for the select.
        return array_values(array_intersect(self::ALL, $allowed));
    }
}

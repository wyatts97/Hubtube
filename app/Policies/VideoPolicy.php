<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Video;

class VideoPolicy
{
    public function view(?User $user, Video $video): bool
    {
        return $video->isAccessibleBy($user);
    }

    public function create(User $user): bool
    {
        return $user->canUpload();
    }

    /**
     * Only admin/pro users can edit videos (change title, description, thumbnails, etc.).
     * Default users see a read-only status page after upload.
     */
    public function update(User $user, Video $video): bool
    {
        if ($user->is_admin) {
            return true;
        }

        return $user->id === $video->user_id && $user->canEditVideo();
    }

    /**
     * Any user can view the status of their own video (processing/moderation status).
     * Admins can view any video's status.
     */
    public function viewStatus(User $user, Video $video): bool
    {
        return $user->id === $video->user_id || $user->is_admin;
    }

    public function delete(User $user, Video $video): bool
    {
        return $user->id === $video->user_id || $user->is_admin;
    }
}

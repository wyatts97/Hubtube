<?php

namespace App\Policies;

use App\Models\Gallery;
use App\Models\User;

class GalleryPolicy
{
    public function view(?User $user, Gallery $gallery): bool
    {
        return $gallery->isAccessibleBy($user);
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Gallery $gallery): bool
    {
        return $user->id === $gallery->user_id || $user->is_admin;
    }

    public function delete(User $user, Gallery $gallery): bool
    {
        return $user->id === $gallery->user_id || $user->is_admin;
    }
}

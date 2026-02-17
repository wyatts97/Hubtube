<?php

namespace App\Policies;

use App\Models\Image;
use App\Models\User;

class ImagePolicy
{
    public function view(?User $user, Image $image): bool
    {
        return $image->isAccessibleBy($user);
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Image $image): bool
    {
        return $user->id === $image->user_id || $user->is_admin;
    }

    public function delete(User $user, Image $image): bool
    {
        return $user->id === $image->user_id || $user->is_admin;
    }
}

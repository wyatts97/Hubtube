<?php

namespace App\Policies;

use App\Models\Playlist;
use App\Models\User;

class PlaylistPolicy
{
    public function view(?User $user, Playlist $playlist): bool
    {
        return true;
    }

    public function update(User $user, Playlist $playlist): bool
    {
        return $user->id === $playlist->user_id;
    }

    public function delete(User $user, Playlist $playlist): bool
    {
        return $user->id === $playlist->user_id && !$playlist->is_default;
    }
}

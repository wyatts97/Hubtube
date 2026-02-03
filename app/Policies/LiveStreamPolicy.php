<?php

namespace App\Policies;

use App\Models\LiveStream;
use App\Models\User;

class LiveStreamPolicy
{
    public function create(User $user): bool
    {
        return $user->canGoLive() && $user->isAgeVerified();
    }

    public function update(User $user, LiveStream $liveStream): bool
    {
        return $user->id === $liveStream->user_id;
    }

    public function delete(User $user, LiveStream $liveStream): bool
    {
        return $user->id === $liveStream->user_id || $user->is_admin;
    }
}

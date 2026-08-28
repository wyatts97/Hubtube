<?php

namespace App\Observers;

use App\Models\User;
use App\Services\ChannelService;

class UserObserver
{
    /**
     * Every user is a channel on this site, so every user needs a channels
     * row. RegisterController and SocialLoginController already call
     * ChannelService::createForUser(), but users created by InstallController,
     * seeders, factories or the Filament admin panel did not — leaving
     * $user->channel null.
     *
     * That null was not harmless: SubscriptionController used
     * $user->channel?->incrementSubscribers(), so the null-safe operator
     * silently discarded subscriber increments for those users.
     */
    public function created(User $user): void
    {
        if ($user->channel()->exists()) {
            return;
        }

        ChannelService::createForUser($user);
    }
}

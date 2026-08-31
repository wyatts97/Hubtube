<?php

namespace App\Observers;

use App\Models\User;
use App\Services\AltTextService;
use App\Services\ChannelService;

class UserObserver
{
    public function __construct(private readonly AltTextService $altText) {}

    /**
     * Fill the avatar alt text when it is blank.
     *
     * Kept on saving so a user who uploads an avatar after registration still
     * gets alt text — SettingsController::updateAvatar() writes users.avatar on
     * an existing row. A non-empty value is never overwritten.
     */
    public function saving(User $user): void
    {
        if (filled($user->avatar_alt_text) || blank($user->username)) {
            return;
        }

        $user->avatar_alt_text = $this->altText->forUserAvatar($user);
    }

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

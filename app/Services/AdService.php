<?php

namespace App\Services;

use App\Models\Setting;
use App\Models\User;

/**
 * Single source of truth for "should this viewer see ads, and what are they
 * targeted as".
 *
 * Both answers used to be copy-pasted per caller. `shouldSuppressAds()` existed
 * five times (four controllers plus an inline copy in the root Blade view), and
 * two ad surfaces — the footer slot and the interstitial payload — were added
 * without any of them, so Pro users kept receiving those ads. Role resolution
 * fared worse: only VideoAdController got it right, and every other caller used
 * `auth()->user()?->role`, which is always null because User has no `role`
 * column (it uses Spatie's HasRoles). That silently pinned every viewer to
 * 'guest' and made role-targeted sponsored cards unreachable.
 *
 * Anything that decides whether to emit an ad must go through here.
 */
class AdService
{
    /**
     * True when the viewer has paid for an ad-free experience.
     *
     * Callers must apply this on the server: withholding the ad markup is the
     * only real gate, since a client-side check still ships the creative — and
     * the ad code itself — in the page payload.
     */
    public function shouldSuppress(?User $user): bool
    {
        return $user !== null
            && $user->is_pro
            && (bool) Setting::get('pro_ad_free', true);
    }

    /**
     * Targeting role for a viewer: admin > pro > default > guest.
     *
     * Matches the `target_roles` values offered by VideoAdResource and
     * SponsoredCardResource. Guests are 'guest'; any signed-in user is at
     * least 'default'.
     */
    public function resolveRole(?User $user): string
    {
        if ($user === null) {
            return 'guest';
        }

        if ($user->is_admin) {
            return 'admin';
        }

        if ($user->is_pro) {
            return 'pro';
        }

        return 'default';
    }
}

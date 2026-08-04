<?php

namespace App\Console\Commands;

use App\Models\PointsRedemption;
use App\Models\User;
use Illuminate\Console\Command;

class ExpirePointsProCommand extends Command
{
    protected $signature = 'points:expire-pro';

    protected $description = 'Revoke points-granted Pro status from users whose period has expired';

    public function handle(): int
    {
        $expiredUsers = User::where('pro_source', 'points')
            ->whereNotNull('pro_expires_at')
            ->where('pro_expires_at', '<', now())
            ->get();

        $revoked = 0;

        foreach ($expiredUsers as $user) {
            // Defensive: never revoke if the user also has a paid subscription active.
            $hasPaidPro = false;
            try {
                $hasPaidPro = $user->subscribed('pro') || $user->fresh()->hasActiveCCBillSubscription();
            } catch (\Throwable) {
                // Cashier not configured / no stripe customer — ignore.
            }

            if ($hasPaidPro) {
                // Paid sub is active; just clear the points tracking so the expiry job stops touching this user.
                $user->forceFill(['pro_expires_at' => null, 'pro_source' => 'stripe'])->save();
                continue;
            }

            $user->forceFill([
                'is_pro' => false,
                'pro_expires_at' => null,
                'pro_source' => null,
            ])->save();

            PointsRedemption::where('user_id', $user->id)
                ->active()
                ->where('ends_at', '<', now())
                ->update(['status' => PointsRedemption::STATUS_EXPIRED]);

            $revoked++;
        }

        if ($revoked > 0) {
            $this->info("Revoked expired points-granted Pro from {$revoked} user(s).");
        }

        return self::SUCCESS;
    }
}

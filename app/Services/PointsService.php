<?php

namespace App\Services;

use App\Models\PointsRedemption;
use App\Models\PointsTransaction;
use App\Models\Setting;
use App\Models\User;
use Carbon\Carbon;
use Exception;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class PointsService
{
    /**
     * Award points to a user for a given action. Idempotent per reference model
     * (e.g. a video/image/comment will only ever earn points once).
     */
    public function award(
        User $user,
        string $type,
        int $points,
        ?Model $reference = null,
        ?string $description = null
    ): ?PointsTransaction {
        if ($points <= 0) {
            return null;
        }

        if (!Setting::get('points_enabled', true)) {
            return null;
        }

        return DB::transaction(function () use ($user, $type, $points, $reference, $description) {
            // Idempotency guard: never award twice for the same reference + type
            if ($reference) {
                $alreadyAwarded = PointsTransaction::where('type', $type)
                    ->where('reference_type', get_class($reference))
                    ->where('reference_id', $reference->id)
                    ->exists();

                if ($alreadyAwarded) {
                    return null;
                }
            }

            $user->lockForUpdate();
            $freshUser = User::lockForUpdate()->find($user->id);

            $newBalance = $freshUser->points_balance + $points;
            $freshUser->forceFill(['points_balance' => $newBalance])->save();
            $user->points_balance = $newBalance;

            return PointsTransaction::create([
                'user_id' => $user->id,
                'type' => $type,
                'points' => $points,
                'balance_after' => $newBalance,
                'reference_type' => $reference ? get_class($reference) : null,
                'reference_id' => $reference?->id,
                'description' => $description,
            ]);
        });
    }

    /**
     * Award points for an approved comment, respecting the configurable daily cap.
     */
    public function awardCommentPoints(User $user, Model $comment): ?PointsTransaction
    {
        if (!Setting::get('points_enabled', true) || !Setting::get('points_comment_enabled', true)) {
            return null;
        }

        $perComment = (int) Setting::get('points_per_comment', 5);
        $dailyCap = (int) Setting::get('points_comment_daily_cap', 50);

        if ($perComment <= 0) {
            return null;
        }

        if ($dailyCap > 0) {
            $earnedToday = PointsTransaction::where('user_id', $user->id)
                ->where('type', PointsTransaction::TYPE_COMMENT)
                ->whereDate('created_at', Carbon::today())
                ->sum('points');

            if ($earnedToday >= $dailyCap) {
                return null;
            }

            $perComment = min($perComment, $dailyCap - $earnedToday);
            if ($perComment <= 0) {
                return null;
            }
        }

        return $this->award($user, PointsTransaction::TYPE_COMMENT, $perComment, $comment, 'Comment posted');
    }

    /**
     * Deduct points from a user's balance (e.g. for a redemption).
     */
    public function spend(
        User $user,
        int $points,
        string $type,
        ?string $description = null,
        ?Model $reference = null
    ): PointsTransaction {
        return DB::transaction(function () use ($user, $points, $type, $description, $reference) {
            $freshUser = User::lockForUpdate()->find($user->id);

            if ($freshUser->points_balance < $points) {
                throw new Exception('Insufficient points balance');
            }

            $newBalance = $freshUser->points_balance - $points;
            $freshUser->forceFill(['points_balance' => $newBalance])->save();
            $user->points_balance = $newBalance;

            return PointsTransaction::create([
                'user_id' => $user->id,
                'type' => $type,
                'points' => -$points,
                'balance_after' => $newBalance,
                'reference_type' => $reference ? get_class($reference) : null,
                'reference_id' => $reference?->id,
                'description' => $description,
            ]);
        });
    }

    /**
     * Redeem points for a temporary Pro period. Extends any existing
     * points-granted Pro period rather than overwriting it.
     */
    public function redeemForPro(User $user): PointsRedemption
    {
        if (!Setting::get('points_enabled', true) || !Setting::get('points_redemption_enabled', true)) {
            throw new Exception('Point redemption is currently disabled.');
        }

        $cost = (int) Setting::get('points_per_redemption_cost', 3000);
        $days = (int) Setting::get('points_pro_grant_days', 30);

        if ($user->points_balance < $cost) {
            throw new Exception('Insufficient points balance.');
        }

        return DB::transaction(function () use ($user, $cost, $days) {
            $transaction = $this->spend(
                $user,
                $cost,
                PointsTransaction::TYPE_REDEMPTION,
                "Redeemed {$days} days of Ad-Free Pro"
            );

            $freshUser = User::lockForUpdate()->find($user->id);

            $now = Carbon::now();
            $currentExpiry = $freshUser->pro_source === 'points' && $freshUser->pro_expires_at?->isFuture()
                ? $freshUser->pro_expires_at
                : $now;

            $newExpiry = $currentExpiry->copy()->addDays($days);

            $update = [
                'pro_expires_at' => $newExpiry,
            ];

            // Only claim is_pro/pro_source if user isn't already Pro via a paid gateway.
            if (!in_array($freshUser->pro_source, ['stripe', 'ccbill'], true)) {
                $update['is_pro'] = true;
                $update['pro_source'] = 'points';
            }

            $freshUser->forceFill($update)->save();

            $redemption = PointsRedemption::create([
                'user_id' => $user->id,
                'points_transaction_id' => $transaction->id,
                'points_spent' => $cost,
                'days_granted' => $days,
                'starts_at' => $now,
                'ends_at' => $newExpiry,
                'status' => PointsRedemption::STATUS_ACTIVE,
            ]);

            $user->points_balance = $freshUser->points_balance;
            $user->is_pro = $freshUser->is_pro;
            $user->pro_expires_at = $freshUser->pro_expires_at;
            $user->pro_source = $freshUser->pro_source;

            return $redemption;
        });
    }
}

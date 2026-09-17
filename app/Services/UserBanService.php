<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Bans, suspensions and their reversal.
 *
 * Ban columns are deliberately outside the user model's $fillable, alongside
 * the other privilege fields, so an account can only be blocked through here
 * or the admin panel — never by a stray mass assignment.
 */
class UserBanService
{
    /** Ban permanently, until an admin lifts it. */
    public function ban(User $user, ?string $reason = null, ?User $actor = null): void
    {
        $user->forceFill([
            'banned_at' => now(),
            'suspended_until' => null,
            'ban_reason' => $reason,
            'banned_by' => $actor?->id,
        ])->save();

        $this->endSessions($user);

        AdminLogger::log(
            "Banned user {$user->username}".($reason ? ": {$reason}" : ''),
            'admin',
            ['user_id' => $user->id, 'reason' => $reason],
            $user
        );
    }

    /** Suspend until a moment in time; the block lifts by itself. */
    public function suspend(User $user, Carbon $until, ?string $reason = null, ?User $actor = null): void
    {
        $user->forceFill([
            'banned_at' => null,
            'suspended_until' => $until,
            'ban_reason' => $reason,
            'banned_by' => $actor?->id,
        ])->save();

        $this->endSessions($user);

        AdminLogger::log(
            "Suspended user {$user->username} until {$until->toDateTimeString()}".($reason ? ": {$reason}" : ''),
            'admin',
            ['user_id' => $user->id, 'until' => $until->toIso8601String(), 'reason' => $reason],
            $user
        );
    }

    public function lift(User $user, ?User $actor = null): void
    {
        $user->forceFill([
            'banned_at' => null,
            'suspended_until' => null,
            'ban_reason' => null,
            'banned_by' => $actor?->id,
        ])->save();

        AdminLogger::log("Lifted the block on user {$user->username}", 'admin', ['user_id' => $user->id], $user);
    }

    /**
     * Drop the user's database-backed sessions so a ban takes effect at once.
     *
     * EnsureUserIsNotBanned already logs a blocked user out on their next
     * request; this closes any other session immediately where the session
     * driver keeps them in the database. Other drivers are a no-op.
     */
    protected function endSessions(User $user): void
    {
        if (config('session.driver') !== 'database') {
            return;
        }

        try {
            DB::table(config('session.table', 'sessions'))->where('user_id', $user->id)->delete();
        } catch (\Throwable) {
            // The sessions table may not exist; the middleware still covers us.
        }
    }
}

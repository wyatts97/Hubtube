<?php

use App\Models\User;
use App\Services\ChannelService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Every user is a channel here, but only RegisterController and
 * SocialLoginController ever created the channels row. Users created by
 * InstallController, seeders, factories or the Filament admin panel have
 * $user->channel === null.
 *
 * UserObserver now covers new users; this backfills the existing ones so
 * nothing downstream has to defend against a null channel.
 */
return new class extends Migration
{
    public function up(): void
    {
        User::query()
            ->whereDoesntHave('channel')
            ->chunkById(500, function ($users) {
                foreach ($users as $user) {
                    ChannelService::createForUser($user);
                }
            });

        // subscriptions.channel_id is a foreign key to users.id, and
        // SubscriptionController incremented through a null-safe operator, so
        // any user who lacked a channel row silently lost every subscriber
        // increment. Recount from the source of truth now that rows exist.
        DB::table('channels')->update([
            'subscriber_count' => DB::raw(
                '(select count(*) from subscriptions where subscriptions.channel_id = channels.user_id)'
            ),
        ]);
    }

    public function down(): void
    {
        // Backfilled profile rows are not distinguishable from hand-created
        // ones, and deleting them would destroy user data. Intentionally a
        // no-op.
    }
};

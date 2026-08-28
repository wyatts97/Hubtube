<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Makes `channels` the canonical home for channel profile data.
 *
 * Two fields had two homes, and in both cases the half users could edit was
 * not the half the channel page rendered:
 *
 *  - Settings edited users.bio, but the channel page displayed
 *    channels.description. Editing your bio changed nothing.
 *  - Filament's "Verified Channel" toggle wrote channels.is_verified, but the
 *    page read users.is_verified. The admin toggle did nothing.
 *
 * users.is_verified wins: it is cast, activity-logged, admin-managed through
 * UserResource, and already drives the badge. channels.is_verified had no
 * writer outside the hidden ChannelResource.
 *
 * users.bio is deliberately NOT dropped here — it stays one release as a
 * rollback path, and ChannelProfileResource still falls back to it.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Correlated subqueries rather than a join-update: UPDATE ... JOIN is
        // MySQL-specific and SQLite rejects it, and this project runs both.
        DB::table('channels')
            ->where(function ($q) {
                $q->whereNull('description')->orWhere('description', '');
            })
            ->whereExists(function ($q) {
                $q->selectRaw('1')
                    ->from('users')
                    ->whereColumn('users.id', 'channels.user_id')
                    ->whereNotNull('users.bio')
                    ->where('users.bio', '!=', '');
            })
            ->update([
                'description' => DB::raw('(select bio from users where users.id = channels.user_id)'),
            ]);

        // Preserve any verification an admin granted through the channel
        // toggle before dropping that column.
        DB::table('users')
            ->whereIn('id', function ($q) {
                $q->select('user_id')->from('channels')->where('is_verified', true);
            })
            ->update(['is_verified' => true]);

        Schema::table('channels', function (Blueprint $table) {
            $table->dropIndex(['is_verified']);
            $table->dropColumn('is_verified');
        });
    }

    public function down(): void
    {
        Schema::table('channels', function (Blueprint $table) {
            $table->boolean('is_verified')->default(false)->after('total_views');
            $table->index('is_verified');
        });

        // Mirror the user flag back so the restored column is not simply empty.
        DB::table('channels')
            ->whereIn('user_id', function ($q) {
                $q->select('id')->from('users')->where('is_verified', true);
            })
            ->update(['is_verified' => true]);

        // Descriptions are intentionally left in place: users.bio was never
        // cleared, so nothing has been lost.
    }
};

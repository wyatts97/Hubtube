<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bans and temporary suspensions.
 *
 * There was previously no way to stop an account at all: a spammer could only
 * have their uploads deleted, and could keep signing in. A banned or suspended
 * user can no longer sign in, and is signed out of any session they still have.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('banned_at')->nullable()->after('age_verified_at');
            // Set for a temporary suspension; the block lifts by itself.
            $table->timestamp('suspended_until')->nullable()->after('banned_at');
            $table->string('ban_reason', 500)->nullable()->after('suspended_until');
            $table->foreignId('banned_by')->nullable()->after('ban_reason')->constrained('users')->nullOnDelete();

            $table->index('banned_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['banned_by']);
            $table->dropIndex(['banned_at']);
            $table->dropColumn(['banned_at', 'suspended_until', 'ban_reason', 'banned_by']);
        });
    }
};

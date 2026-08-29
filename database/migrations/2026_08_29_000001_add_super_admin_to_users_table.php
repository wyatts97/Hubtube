<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Introduce a super-admin tier.
 *
 * Previously `is_admin` was the only gate on the Filament panel, so every admin
 * could reach the settings pages that shell out to ffmpeg, read and write payment
 * credentials, and grant `is_admin` to any account. Those pages now require
 * super-admin.
 *
 * Existing admins are promoted on upgrade: locking a buyer out of their own panel
 * would be a far worse failure than the status quo. New admins created after this
 * migration default to false and must be promoted explicitly.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_super_admin')->default(false)->after('is_admin');
        });

        DB::table('users')->where('is_admin', true)->update(['is_super_admin' => true]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('is_super_admin');
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedInteger('points_balance')->default(0)->after('wallet_balance');
            $table->timestamp('pro_expires_at')->nullable()->after('is_pro');
            $table->string('pro_source')->nullable()->after('pro_expires_at'); // stripe, ccbill, points, admin
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['points_balance', 'pro_expires_at', 'pro_source']);
        });
    }
};

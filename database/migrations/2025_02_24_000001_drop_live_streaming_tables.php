<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Drop live streaming tables (live_streams, gifts, gift_transactions).
     * Run after removing live streaming functionality.
     */
    public function up(): void
    {
        Schema::dropIfExists('gift_transactions');
        Schema::dropIfExists('gifts');
        Schema::dropIfExists('live_streams');
    }

    public function down(): void
    {
        // Tables cannot be recreated via this migration.
        // Run: php artisan migrate:rollback on the original migrations if needed.
    }
};

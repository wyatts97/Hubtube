<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('video_ads', function (Blueprint $table) {
            // Outstream ads target the video grid rather than the player
            if (!Schema::hasColumn('video_ads', 'outstream_thumbnail')) {
                $table->string('outstream_thumbnail')->nullable()->after('file_path');
            }
        });

        // Add interstitial settings columns to settings table if needed
        // (Settings are stored as key-value pairs in the settings table — no schema change needed)
    }

    public function down(): void
    {
        Schema::table('video_ads', function (Blueprint $table) {
            $table->dropColumn('outstream_thumbnail');
        });
    }
};

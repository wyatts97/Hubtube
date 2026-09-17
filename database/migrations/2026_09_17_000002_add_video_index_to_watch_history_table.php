<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('watch_history', function (Blueprint $table) {
            // Watch time in the creator studio aggregates a video's rows. The
            // table only had user-scoped indexes, so that was a table scan.
            $table->index(['video_id', 'updated_at'], 'watch_history_video_updated_index');
        });
    }

    public function down(): void
    {
        Schema::table('watch_history', function (Blueprint $table) {
            $table->dropIndex('watch_history_video_updated_index');
        });
    }
};

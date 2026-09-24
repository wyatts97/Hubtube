<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The daily model:prune deletes video_views by created_at alone, which the
 * existing (video_id, created_at) index can't serve, so it scanned the table.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasIndex('video_views', ['created_at'])) {
            Schema::table('video_views', function (Blueprint $table) {
                $table->index('created_at');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasIndex('video_views', ['created_at'])) {
            Schema::table('video_views', function (Blueprint $table) {
                $table->dropIndex(['created_at']);
            });
        }
    }
};

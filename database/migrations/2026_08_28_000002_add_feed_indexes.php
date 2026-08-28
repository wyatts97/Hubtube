<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The subscription feed filters videos by a set of user_ids and orders by
 * published_at. videos already has (user_id, created_at) and a standalone
 * published_at index, but not the pair this query actually needs — so it
 * filtered on one index then sorted the result set.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('videos', function (Blueprint $table) {
            $table->index(['user_id', 'published_at'], 'videos_user_published_idx');
        });

        Schema::table('playlists', function (Blueprint $table) {
            $table->index(['user_id', 'created_at'], 'playlists_user_created_idx');
        });
    }

    public function down(): void
    {
        Schema::table('videos', function (Blueprint $table) {
            $table->dropIndex('videos_user_published_idx');
        });

        Schema::table('playlists', function (Blueprint $table) {
            $table->dropIndex('playlists_user_created_idx');
        });
    }
};

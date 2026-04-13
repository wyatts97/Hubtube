<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── Ad impression & click tracking ──
        Schema::table('video_ads', function (Blueprint $table) {
            $table->unsignedBigInteger('impressions_count')->default(0)->after('weight');
            $table->unsignedBigInteger('clicks_count')->default(0)->after('impressions_count');
        });

        // ── Sponsored card click tracking ──
        Schema::table('sponsored_cards', function (Blueprint $table) {
            $table->unsignedBigInteger('clicks_count')->default(0)->after('weight');
        });

        // ── Composite indexes for hot video listing queries ──
        Schema::table('videos', function (Blueprint $table) {
            // Public processed approved by date — used by home, trending, category pages
            $table->index(['privacy', 'status', 'is_approved', 'published_at'], 'idx_videos_public_listing');
            // Category filter queries
            $table->index(['category_id', 'privacy', 'status', 'is_approved'], 'idx_videos_category_public');
            // Featured flag
            $table->index(['is_featured', 'privacy', 'status', 'is_approved'], 'idx_videos_featured');
            // User's own videos (dashboard, channel page)
            $table->index(['user_id', 'status', 'published_at'], 'idx_videos_user_published');
        });

        // ── Watch history: user + date (history page, feed) ──
        Schema::table('watch_histories', function (Blueprint $table) {
            $table->index(['user_id', 'created_at'], 'idx_watch_history_user_date');
        });

        // ── Likes: user + video lookups ──
        Schema::table('likes', function (Blueprint $table) {
            $table->index(['user_id', 'video_id', 'type'], 'idx_likes_user_video_type');
        });

        // ── Subscriptions: subscriber feed queries ──
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->index(['subscriber_id', 'created_at'], 'idx_subscriptions_subscriber');
        });
    }

    public function down(): void
    {
        Schema::table('video_ads', function (Blueprint $table) {
            $table->dropColumn(['impressions_count', 'clicks_count']);
        });

        Schema::table('sponsored_cards', function (Blueprint $table) {
            $table->dropColumn('clicks_count');
        });

        Schema::table('videos', function (Blueprint $table) {
            $table->dropIndex('idx_videos_public_listing');
            $table->dropIndex('idx_videos_category_public');
            $table->dropIndex('idx_videos_featured');
            $table->dropIndex('idx_videos_user_published');
        });

        Schema::table('watch_histories', function (Blueprint $table) {
            $table->dropIndex('idx_watch_history_user_date');
        });

        Schema::table('likes', function (Blueprint $table) {
            $table->dropIndex('idx_likes_user_video_type');
        });

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropIndex('idx_subscriptions_subscriber');
        });
    }
};

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
            if (!Schema::hasColumn('video_ads', 'impressions_count')) {
                $table->unsignedBigInteger('impressions_count')->default(0)->after('weight');
            }
            if (!Schema::hasColumn('video_ads', 'clicks_count')) {
                $table->unsignedBigInteger('clicks_count')->default(0)->after('impressions_count');
            }
        });

        // ── Sponsored card click tracking ──
        Schema::table('sponsored_cards', function (Blueprint $table) {
            if (!Schema::hasColumn('sponsored_cards', 'clicks_count')) {
                $table->unsignedBigInteger('clicks_count')->default(0)->after('weight');
            }
        });

        // ── Composite indexes for hot video listing queries ──
        Schema::table('videos', function (Blueprint $table) {
            if (!$this->indexExists('videos', 'idx_videos_public_listing')) {
                $table->index(['privacy', 'status', 'is_approved', 'published_at'], 'idx_videos_public_listing');
            }
            if (!$this->indexExists('videos', 'idx_videos_category_public')) {
                $table->index(['category_id', 'privacy', 'status', 'is_approved'], 'idx_videos_category_public');
            }
            if (!$this->indexExists('videos', 'idx_videos_featured')) {
                $table->index(['is_featured', 'privacy', 'status', 'is_approved'], 'idx_videos_featured');
            }
            if (!$this->indexExists('videos', 'idx_videos_user_published')) {
                $table->index(['user_id', 'status', 'published_at'], 'idx_videos_user_published');
            }
        });

        // ── Watch history: user + date (history page, feed) ──
        Schema::table('watch_history', function (Blueprint $table) {
            if (!$this->indexExists('watch_history', 'idx_watch_history_user_date')) {
                $table->index(['user_id', 'created_at'], 'idx_watch_history_user_date');
            }
        });

        // ── Likes: user + video lookups ──
        Schema::table('likes', function (Blueprint $table) {
            if (!$this->indexExists('likes', 'idx_likes_user_video_type')) {
                $table->index(['user_id', 'video_id', 'type'], 'idx_likes_user_video_type');
            }
        });

        // ── Subscriptions: subscriber feed queries ──
        Schema::table('subscriptions', function (Blueprint $table) {
            if (!$this->indexExists('subscriptions', 'idx_subscriptions_subscriber')) {
                $table->index(['subscriber_id', 'created_at'], 'idx_subscriptions_subscriber');
            }
        });
    }

    protected function indexExists(string $table, string $indexName): bool
    {
        $db = Schema::getConnection()->getDatabaseName();
        $count = \Illuminate\Support\Facades\DB::selectOne(
            "SELECT COUNT(*) as cnt FROM information_schema.STATISTICS
             WHERE table_schema = ? AND table_name = ? AND index_name = ?",
            [$db, $table, $indexName]
        );
        return ($count->cnt ?? 0) > 0;
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

        Schema::table('watch_history', function (Blueprint $table) {
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

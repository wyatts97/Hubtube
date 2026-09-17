<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Draft videos: finished processing, but private to their uploader.
 *
 * Scheduled videos used to be approved with a null published_at, which kept
 * them out of every listing but left them openable by anyone who had the URL.
 * Anything waiting on the publishing queue is now a draft as well, so it is
 * only reachable by its owner and admins until its scheduled time arrives.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('videos', function (Blueprint $table) {
            $table->boolean('is_draft')->default(false)->after('privacy');
            $table->index(['is_draft', 'published_at']);
        });

        // Everything currently queued for scheduled publishing.
        DB::table('videos')
            ->whereNotNull('queue_order')
            ->whereNull('published_at')
            ->update(['is_draft' => true]);
    }

    public function down(): void
    {
        Schema::table('videos', function (Blueprint $table) {
            $table->dropIndex(['is_draft', 'published_at']);
            $table->dropColumn('is_draft');
        });
    }
};

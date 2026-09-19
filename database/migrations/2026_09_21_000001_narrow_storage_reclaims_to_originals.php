<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Narrow storage reclaim to original uploads only.
 *
 * The feature shipped able to re-encode renditions and to discard the
 * `processed/hls` segment tree as well, on the reasoning that HLS is a second
 * copy of each rendition. It is a second *format*, not a spare copy: the
 * player is handed the HLS manifest whenever one exists (see VideoPlayer.vue)
 * and only falls back to the progressive MP4s. Discarding it costs adaptive
 * bitrate switching across the library — a real capability, not recovered
 * waste — and re-encoding a rendition trades picture quality on the files
 * people actually stream.
 *
 * So both targets are gone, along with everything that existed to support
 * them:
 *
 *  - `videos.media_version` cache-busted files replaced under their own name.
 *    A re-compressed original lands at a new filename, so nothing needs it.
 *  - `video_encodings.settings_overrides` forced one rendition to be redone
 *    with different encoder settings.
 *  - `storage_reclaims.quality` named which rendition an attempt targeted.
 *  - `storage_reclaims.kept_is_hardlink` recorded how a rendition's old file
 *    was held aside during its re-encode; an original is never moved at all.
 *  - `storage_reclaims.keep_until` drove a nightly sweep that auto-accepted
 *    unreviewed attempts. Accepting deletes an upload permanently, so it now
 *    only ever happens because a person pressed the button.
 *
 * Any attempt still in flight for a removed target is closed out rather than
 * left looking actionable, and its held file is named in the row so it can be
 * dealt with by hand.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Close out work for targets that no longer have a worker. These rows
        // may have left a file aside; record where, because no code path will
        // clean it up now.
        if (Schema::hasColumn('storage_reclaims', 'quality')) {
            DB::table('storage_reclaims')
                ->where('target', '!=', 'original')
                ->whereIn('status', ['pending', 'running', 'awaiting_review'])
                ->update([
                    'status' => 'skipped',
                    'error' => DB::raw("CONCAT('Cancelled: reclaim is now limited to original uploads. Any file held aside is at: ', COALESCE(kept_path, 'nothing held'))"),
                    'finished_at' => now(),
                ]);
        }

        Schema::table('storage_reclaims', function (Blueprint $table) {
            // The sweep's cursor index has to go before the column it covers:
            // both SQLite and MySQL refuse to drop a column an index still
            // names.
            if (Schema::hasColumn('storage_reclaims', 'keep_until')) {
                $table->dropIndex('storage_reclaims_status_keep_until_index');
            }

            foreach (['quality', 'kept_is_hardlink', 'keep_until'] as $column) {
                if (Schema::hasColumn('storage_reclaims', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        if (Schema::hasColumn('video_encodings', 'settings_overrides')) {
            Schema::table('video_encodings', function (Blueprint $table) {
                $table->dropColumn('settings_overrides');
            });
        }

        if (Schema::hasColumn('videos', 'media_version')) {
            Schema::table('videos', function (Blueprint $table) {
                $table->dropColumn('media_version');
            });
        }
    }

    public function down(): void
    {
        Schema::table('storage_reclaims', function (Blueprint $table) {
            $table->string('quality', 32)->nullable()->after('target');
            $table->boolean('kept_is_hardlink')->default(false)->after('kept_path');
            $table->timestamp('keep_until')->nullable()->after('error');
            $table->index(['status', 'keep_until']);
        });

        Schema::table('video_encodings', function (Blueprint $table) {
            $table->json('settings_overrides')->nullable()->after('run_id');
        });

        Schema::table('videos', function (Blueprint $table) {
            $table->unsignedInteger('media_version')->default(0)->after('storage_disk');
        });
    }
};

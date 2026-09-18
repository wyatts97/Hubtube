<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per attempt to reclaim storage from a video's files.
 *
 * Deliberately not tracked on `video_encodings`: that table has a
 * unique(video_id, quality), so it holds exactly one row per rendition and
 * `plan()` refills it and nulls `size` on every re-plan — which destroys the
 * before-size a reclaim has to compare against, and leaves no attempt history
 * at all. A non-terminal row there would also make Video::isEncoding() true
 * forever, hiding "encode missing renditions" for good.
 *
 * The row is the record of a bargain: we produced a smaller file, we are still
 * holding the old one, and nothing about the video changes until someone
 * accepts or `keep_until` passes. before/after bytes and the exact encoder
 * settings are kept so a result is still interpretable months later.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('storage_reclaims', function (Blueprint $table) {
            $table->id();
            $table->foreignId('video_id')->constrained()->cascadeOnDelete();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();

            // original | rendition | hls
            $table->string('target', 16);
            // The rendition label for target=rendition; null otherwise.
            $table->string('quality', 32)->nullable();

            // pending → running → awaiting_review → accepted | reverted | expired
            // plus the terminals failed, skipped and revert_failed.
            $table->string('status', 24)->default('pending');

            // Changes on every attempt, so a job from an abandoned run can
            // recognise it is stale and stand down — same fencing idea as
            // video_encodings.run_id.
            $table->uuid('run_id');

            // The preset, CRF and rate control actually used. Site settings
            // drift; a saving is meaningless without knowing what produced it.
            $table->json('settings_snapshot')->nullable();

            $table->string('source_path', 1024)->nullable();
            $table->string('new_path', 1024)->nullable();
            // Where the old bytes are being held until this is accepted.
            $table->string('kept_path', 1024)->nullable();
            // A hardlink costs nothing; a copy costs the file's size again.
            // Which one we got changes what "space held pending review" means.
            $table->boolean('kept_is_hardlink')->default(false);

            $table->unsignedBigInteger('before_bytes')->nullable();
            $table->unsignedBigInteger('after_bytes')->nullable();
            // Milliseconds: the acceptance tolerance is sub-second.
            $table->unsignedBigInteger('before_duration_ms')->nullable();
            $table->unsignedBigInteger('after_duration_ms')->nullable();

            $table->text('error')->nullable();
            // Passing this unreviewed counts as acceptance; see the sweep.
            $table->timestamp('keep_until')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index(['video_id', 'status']);
            // The sweep cursor.
            $table->index(['status', 'keep_until']);
            $table->index(['target', 'status']);

            // No unique on (video_id, target, quality): the real constraint is
            // "at most one *live* attempt", which MySQL cannot express without
            // partial indexes. It is enforced in StorageReclaimService by a
            // lock plus an active-row check, and by ShouldBeUnique on the jobs.
        });

        Schema::table('videos', function (Blueprint $table) {
            // Bumped whenever a file is replaced in place, and appended as
            // ?v={n} by the URL accessors. nginx sets `expires 30d` on mp4
            // under /storage and Cloudflare sits in front with no purge
            // integration, so without this a replaced rendition serves stale
            // bytes for a month.
            $table->unsignedInteger('media_version')->default(0)->after('storage_disk');
        });

        Schema::table('video_encodings', function (Blueprint $table) {
            // Set to force a rendition to be re-encoded with different
            // settings than the site default. Its presence is also the signal
            // that routes the job to the niced reclaim queue instead of
            // jumping ahead of live uploads.
            $table->json('settings_overrides')->nullable()->after('run_id');
        });
    }

    public function down(): void
    {
        Schema::table('video_encodings', function (Blueprint $table) {
            $table->dropColumn('settings_overrides');
        });

        Schema::table('videos', function (Blueprint $table) {
            $table->dropColumn('media_version');
        });

        Schema::dropIfExists('storage_reclaims');
    }
};

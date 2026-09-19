<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Retire the storage reclaim ledger.
 *
 * Re-compressing is now a Media Library action (MediaCompressService): each
 * encode writes a new file beside its source, and a video's original is
 * swapped for a copy only when an admin presses "Replace original". There is
 * no queued review step left for a ledger to record, so the table, its review
 * page and its four settings are gone.
 *
 * An attempt still awaiting review when this runs leaves its re-encoded file
 * on disk beside the original — it shows up in the library as an ordinary
 * file, to keep or delete.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('storage_reclaims');

        DB::table('settings')
            ->whereIn('key', ['reclaim_preset', 'reclaim_crf_delta', 'reclaim_min_saving_percent', 'reclaim_keep_days'])
            ->delete();
    }

    public function down(): void
    {
        Schema::create('storage_reclaims', function (Blueprint $table) {
            $table->id();
            $table->foreignId('video_id')->constrained()->cascadeOnDelete();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('target', 16)->default('original');
            $table->string('status', 24)->default('pending')->index();
            $table->uuid('run_id')->nullable();
            $table->json('settings_snapshot')->nullable();
            $table->string('source_path', 1024)->nullable();
            $table->string('new_path', 1024)->nullable();
            $table->string('kept_path', 1024)->nullable();
            $table->unsignedBigInteger('before_bytes')->nullable();
            $table->unsignedBigInteger('after_bytes')->nullable();
            $table->unsignedBigInteger('before_duration_ms')->nullable();
            $table->unsignedBigInteger('after_duration_ms')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });
    }
};

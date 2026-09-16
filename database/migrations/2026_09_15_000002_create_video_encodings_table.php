<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per rendition of a video, tracking its encode from plan to finish.
 *
 * Previously the only record of processing was the video's overall status,
 * so a failed 720p, a slow 1080p and a finished 360p all looked the same.
 * These rows drive the chunked encoder (see RenditionCoordinator) and the
 * admin progress bars.
 *
 * `quality` is 'original' for the watermarked full-resolution copy that
 * replaces the upload when a watermark is configured.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('video_encodings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('video_id')->constrained()->cascadeOnDelete();
            $table->foreignId('encode_profile_id')->nullable()->constrained()->nullOnDelete();
            $table->string('quality', 32);
            $table->unsignedSmallInteger('height')->default(0);
            // The upload's display size, which watermark layout is based on.
            $table->unsignedSmallInteger('source_width')->default(0);
            $table->unsignedSmallInteger('source_height')->default(0);
            // pending → queued → processing → finalizing → completed | failed
            $table->string('status', 16)->default('pending');
            $table->unsignedTinyInteger('progress')->default(0);
            $table->boolean('is_priority')->default(false);
            $table->boolean('apply_watermark')->default(false);
            $table->unsignedSmallInteger('chunks_total')->default(1);
            $table->unsignedSmallInteger('chunks_completed')->default(0);
            $table->unsignedInteger('chunk_seconds')->nullable();
            // Changes on every re-plan so jobs from an abandoned run stand down.
            $table->uuid('run_id');
            $table->text('error')->nullable();
            $table->unsignedBigInteger('size')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique(['video_id', 'quality']);
            $table->index(['status']);
        });

        Schema::table('videos', function (Blueprint $table) {
            $table->string('processing_stage', 32)->nullable()->after('processing_fallback_reason');
        });
    }

    public function down(): void
    {
        Schema::table('videos', function (Blueprint $table) {
            $table->dropColumn('processing_stage');
        });

        Schema::dropIfExists('video_encodings');
    }
};

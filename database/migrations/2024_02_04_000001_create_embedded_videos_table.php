<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('embedded_videos', function (Blueprint $table) {
            $table->id();
            $table->string('source_site', 50)->index();
            $table->string('source_video_id')->index();
            $table->string('title');
            $table->text('description')->nullable();
            $table->integer('duration')->default(0);
            $table->string('duration_formatted', 20)->nullable();
            $table->string('thumbnail_url', 500)->nullable();
            $table->string('thumbnail_preview_url', 500)->nullable();
            $table->string('source_url', 500);
            $table->string('embed_url', 500);
            $table->text('embed_code');
            $table->bigInteger('views_count')->default(0);
            $table->integer('rating')->default(0);
            $table->json('tags')->nullable();
            $table->json('actors')->nullable();
            $table->string('category_id')->nullable();
            $table->boolean('is_published')->default(false);
            $table->boolean('is_featured')->default(false);
            $table->timestamp('source_upload_date')->nullable();
            $table->timestamp('imported_at')->nullable();
            $table->timestamps();

            $table->unique(['source_site', 'source_video_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('embedded_videos');
    }
};

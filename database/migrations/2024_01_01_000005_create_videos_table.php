<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('videos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->uuid('uuid')->unique();
            $table->string('title', 200);
            $table->string('slug', 220)->unique();
            $table->text('description')->nullable();
            $table->string('thumbnail')->nullable();
            $table->string('video_path', 500)->nullable();
            $table->string('trailer_path', 500)->nullable();
            $table->unsignedInteger('duration')->default(0);
            $table->unsignedBigInteger('size')->default(0);
            $table->enum('privacy', ['public', 'private', 'unlisted'])->default('public');
            $table->enum('status', ['pending', 'processing', 'processed', 'failed'])->default('pending');
            $table->boolean('is_short')->default(false);
            $table->boolean('is_featured')->default(false);
            $table->boolean('is_approved')->default(false);
            $table->boolean('age_restricted')->default(true);
            $table->boolean('monetization_enabled')->default(false);
            $table->decimal('price', 10, 2)->nullable();
            $table->decimal('rent_price', 10, 2)->nullable();
            $table->unsignedBigInteger('views_count')->default(0);
            $table->unsignedInteger('likes_count')->default(0);
            $table->unsignedInteger('dislikes_count')->default(0);
            $table->unsignedInteger('comments_count')->default(0);
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->json('qualities_available')->nullable();
            $table->json('geo_blocked_countries')->nullable();
            $table->json('tags')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamp('processing_started_at')->nullable();
            $table->timestamp('processing_completed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['privacy', 'status', 'is_approved']);
            $table->index(['user_id', 'created_at']);
            $table->index('views_count');
            $table->index('is_short');
            $table->index('is_featured');
            $table->index('published_at');
            if (in_array(DB::getDriverName(), ['mysql', 'mariadb'])) {
                $table->fullText(['title', 'description']);
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('videos');
    }
};

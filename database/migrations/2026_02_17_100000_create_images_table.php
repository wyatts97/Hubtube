<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->ulid('uuid')->unique();
            $table->string('title')->nullable();
            $table->text('description')->nullable();
            $table->string('file_path');
            $table->string('thumbnail_path')->nullable();
            $table->string('storage_disk')->default('public');
            $table->string('mime_type');
            $table->unsignedInteger('width');
            $table->unsignedInteger('height');
            $table->unsignedBigInteger('file_size');
            $table->boolean('is_animated')->default(false);
            $table->string('blurhash')->nullable();
            $table->enum('privacy', ['public', 'private', 'unlisted'])->default('public');
            $table->boolean('is_approved')->default(true);
            $table->unsignedInteger('views_count')->default(0);
            $table->unsignedInteger('likes_count')->default(0);
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->json('tags')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'privacy', 'is_approved']);
            $table->index('published_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('images');
    }
};

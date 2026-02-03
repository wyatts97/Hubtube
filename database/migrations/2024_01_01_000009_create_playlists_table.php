<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('playlists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title', 150);
            $table->string('slug', 170)->unique();
            $table->text('description')->nullable();
            $table->string('thumbnail')->nullable();
            $table->enum('privacy', ['public', 'private', 'unlisted'])->default('public');
            $table->boolean('is_default')->default(false);
            $table->unsignedInteger('video_count')->default(0);
            $table->timestamps();

            $table->index(['user_id', 'privacy']);
        });

        Schema::create('playlist_videos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('playlist_id')->constrained()->cascadeOnDelete();
            $table->foreignId('video_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->unique(['playlist_id', 'video_id']);
            $table->index(['playlist_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('playlist_videos');
        Schema::dropIfExists('playlists');
    }
};

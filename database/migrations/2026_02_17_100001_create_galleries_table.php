<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('galleries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->foreignId('cover_image_id')->nullable()->constrained('images')->nullOnDelete();
            $table->enum('privacy', ['public', 'private', 'unlisted'])->default('public');
            $table->unsignedInteger('images_count')->default(0);
            $table->unsignedInteger('views_count')->default(0);
            $table->string('sort_order')->default('newest');
            $table->timestamps();

            $table->index(['user_id', 'privacy']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('galleries');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('channels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('slug', 100)->unique();
            $table->text('description')->nullable();
            $table->string('banner_image')->nullable();
            $table->string('custom_url', 50)->unique()->nullable();
            $table->unsignedInteger('subscriber_count')->default(0);
            $table->unsignedBigInteger('total_views')->default(0);
            $table->boolean('is_verified')->default(false);
            $table->decimal('subscription_price', 10, 2)->nullable();
            $table->boolean('subscription_enabled')->default(false);
            $table->json('social_links')->nullable();
            $table->foreignId('featured_video_id')->nullable();
            $table->timestamps();

            $table->index('subscriber_count');
            $table->index('is_verified');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('channels');
    }
};

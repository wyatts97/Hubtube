<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('live_streams', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title', 200);
            $table->text('description')->nullable();
            $table->string('thumbnail')->nullable();
            $table->string('channel_name', 100)->unique();
            $table->text('agora_token')->nullable();
            $table->text('agora_resource_id')->nullable();
            $table->string('agora_sid', 500)->nullable();
            $table->enum('status', ['pending', 'live', 'ended'])->default('pending');
            $table->unsignedInteger('viewer_count')->default(0);
            $table->unsignedInteger('peak_viewers')->default(0);
            $table->decimal('total_gifts_amount', 12, 2)->default(0);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->foreignId('recorded_video_id')->nullable();
            $table->boolean('chat_enabled')->default(true);
            $table->boolean('gifts_enabled')->default(true);
            $table->timestamps();

            $table->index(['status', 'started_at']);
            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('live_streams');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gifts', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50);
            $table->string('slug', 50)->unique();
            $table->string('icon', 255);
            $table->string('animation_type', 50)->nullable();
            $table->json('animation_data')->nullable();
            $table->decimal('price', 10, 2);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['is_active', 'sort_order']);
        });

        Schema::create('gift_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gift_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sender_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('receiver_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('live_stream_id')->nullable();
            $table->decimal('amount', 10, 2);
            $table->decimal('platform_cut', 10, 2);
            $table->decimal('receiver_amount', 10, 2);
            $table->timestamps();

            $table->index(['receiver_id', 'created_at']);
            $table->index(['live_stream_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gift_transactions');
        Schema::dropIfExists('gifts');
    }
};

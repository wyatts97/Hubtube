<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('video_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('video_id')->constrained()->cascadeOnDelete();
            $table->foreignId('buyer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('seller_id')->constrained('users')->cascadeOnDelete();
            $table->enum('type', ['purchase', 'rental']);
            $table->decimal('amount', 10, 2);
            $table->decimal('platform_cut', 10, 2);
            $table->decimal('seller_amount', 10, 2);
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['buyer_id', 'video_id']);
            $table->index(['seller_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('video_transactions');
    }
};

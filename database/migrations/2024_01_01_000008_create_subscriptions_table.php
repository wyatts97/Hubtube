<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subscriber_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('channel_id')->constrained('users')->cascadeOnDelete();
            $table->boolean('is_paid')->default(false);
            $table->decimal('amount', 10, 2)->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->boolean('notifications_enabled')->default(true);
            $table->timestamps();

            $table->unique(['subscriber_id', 'channel_id']);
            $table->index(['channel_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};

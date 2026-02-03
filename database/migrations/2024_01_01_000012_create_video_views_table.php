<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('video_views', function (Blueprint $table) {
            $table->id();
            $table->foreignId('video_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->string('country', 2)->nullable();
            $table->string('referrer', 500)->nullable();
            $table->timestamps();

            $table->index(['video_id', 'created_at']);
            $table->index(['video_id', 'country']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('video_views');
    }
};

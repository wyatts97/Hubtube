<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('video_tweets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('video_id')->constrained()->cascadeOnDelete();
            $table->string('tweet_id')->nullable();
            $table->enum('tweet_type', ['new', 'scheduled'])->default('new');
            $table->timestamp('tweeted_at')->nullable();
            $table->string('tweet_url')->nullable();
            $table->timestamps();

            $table->index('video_id');
            $table->index('tweeted_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('video_tweets');
    }
};

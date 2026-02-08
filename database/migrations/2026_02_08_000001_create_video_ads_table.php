<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('video_ads', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->enum('type', ['vast', 'vpaid', 'mp4', 'html'])->default('mp4');
            $table->enum('placement', ['pre_roll', 'mid_roll', 'post_roll']);
            $table->text('content');
            $table->unsignedInteger('weight')->default(1);
            $table->boolean('is_active')->default(true);
            $table->json('category_ids')->nullable();
            $table->json('target_roles')->nullable();
            $table->timestamps();

            $table->index(['placement', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('video_ads');
    }
};

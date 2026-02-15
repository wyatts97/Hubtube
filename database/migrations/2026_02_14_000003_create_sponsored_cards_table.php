<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('sponsored_cards')) {
            return;
        }

        Schema::create('sponsored_cards', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('thumbnail_url');
            $table->string('click_url', 2048);
            $table->string('description')->nullable();
            $table->json('target_pages')->nullable();
            $table->integer('frequency')->default(8);
            $table->integer('weight')->default(1);
            $table->boolean('is_active')->default(true);
            $table->json('category_ids')->nullable();
            $table->json('target_roles')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sponsored_cards');
    }
};

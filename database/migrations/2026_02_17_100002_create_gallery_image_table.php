<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gallery_image', function (Blueprint $table) {
            $table->foreignId('gallery_id')->constrained()->cascadeOnDelete();
            $table->foreignId('image_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->primary(['gallery_id', 'image_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gallery_image');
    }
};

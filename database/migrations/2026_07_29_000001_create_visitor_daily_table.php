<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('visitor_daily', function (Blueprint $table) {
            $table->id();
            $table->string('visitor_hash', 64);
            $table->date('date');
            $table->unsignedInteger('visit_count')->default(1);
            $table->timestamps();

            $table->unique(['visitor_hash', 'date']);
            $table->index('date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('visitor_daily');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // MySQL enum does not allow easy additions; convert to a constrained string column.
        // Existing rows keep their current values, and the app/form validate allowed placements.
        Schema::table('video_ads', function (Blueprint $table) {
            $table->string('placement')->default('pre_roll')->change();
        });
    }

    public function down(): void
    {
        Schema::table('video_ads', function (Blueprint $table) {
            $table->enum('placement', ['pre_roll', 'mid_roll', 'post_roll', 'outstream'])->default('pre_roll')->change();
        });
    }
};

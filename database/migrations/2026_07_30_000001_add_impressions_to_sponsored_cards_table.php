<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sponsored_cards', function (Blueprint $table) {
            if (!Schema::hasColumn('sponsored_cards', 'impressions_count')) {
                $table->unsignedBigInteger('impressions_count')->default(0)->after('clicks_count');
            }
        });
    }

    public function down(): void
    {
        Schema::table('sponsored_cards', function (Blueprint $table) {
            $table->dropColumn('impressions_count');
        });
    }
};

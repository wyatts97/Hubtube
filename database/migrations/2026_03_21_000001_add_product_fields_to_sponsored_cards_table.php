<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sponsored_cards', function (Blueprint $table) {
            $table->decimal('price', 10, 2)->nullable()->after('description');
            $table->decimal('sale_price', 10, 2)->nullable()->after('price');
            $table->string('ribbon_text')->nullable()->after('sale_price');
            $table->json('preview_images')->nullable()->after('ribbon_text');
            $table->string('external_id')->nullable()->after('id');
            $table->string('studio')->nullable()->after('description');
            $table->integer('duration')->nullable()->after('studio');
        });
    }

    public function down(): void
    {
        Schema::table('sponsored_cards', function (Blueprint $table) {
            $table->dropColumn([
                'price',
                'sale_price',
                'ribbon_text',
                'preview_images',
                'external_id',
                'studio',
                'duration',
            ]);
        });
    }
};

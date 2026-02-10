<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('translations', function (Blueprint $table) {
            $table->id();
            $table->string('translatable_type');  // e.g. App\Models\Video
            $table->unsignedBigInteger('translatable_id');
            $table->string('field');              // e.g. title, description
            $table->string('locale', 10);         // e.g. es, fr, de
            $table->text('value');                 // translated text
            $table->string('translated_slug')->nullable()->index(); // SEO slug
            $table->timestamps();

            $table->unique(['translatable_type', 'translatable_id', 'field', 'locale'], 'translations_unique');
            $table->index(['translatable_type', 'translatable_id', 'locale'], 'translations_morph_locale');
            $table->index(['locale', 'translated_slug'], 'translations_slug_lookup');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('translations');
    }
};

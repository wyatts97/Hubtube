<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Records which engine produced each translation, and what it translated from.
 *
 * Without `provider`, switching engines leaves no way to answer "re-translate
 * the old rows" except truncating the table — which, now that translation is
 * scheduled-only, would revert the site to source language until the next run.
 *
 * `source_locale` matters because `default_language` is admin-changeable: today
 * a row translated from English is indistinguishable from one translated from
 * Spanish, so changing the default silently invalidates the table undetectably.
 *
 * Both nullable and additive — existing rows read as NULL, meaning "legacy,
 * engine unknown". The unique index on (type, id, field, locale) is untouched.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('translations', function (Blueprint $table) {
            $table->string('provider', 32)->nullable()->after('locale');
            $table->string('source_locale', 12)->nullable()->after('provider');

            $table->index(['provider', 'locale'], 'translations_provider_locale');
        });
    }

    public function down(): void
    {
        Schema::table('translations', function (Blueprint $table) {
            $table->dropIndex('translations_provider_locale');
            $table->dropColumn(['provider', 'source_locale']);
        });
    }
};

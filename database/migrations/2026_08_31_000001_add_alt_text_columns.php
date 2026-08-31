<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add persisted alt text columns for every public-facing image.
 *
 * Alt text was previously improvised in the Vue layer (or omitted entirely),
 * so nothing reached crawlers or the image sitemap. AltTextService generates
 * these from existing metadata; the columns let a human override the generated
 * value and let the sitemap read alt text without instantiating a model.
 *
 * Nullable throughout: a null column means "not generated yet" and the model
 * accessors fall back to generating on the fly, so the app is correct before
 * seo:backfill-alt-text has ever run.
 */
return new class extends Migration
{
    /**
     * table => [column, after]
     */
    private const COLUMNS = [
        'videos' => ['thumbnail_alt_text', 'thumbnail'],
        'images' => ['alt_text', 'description'],
        'galleries' => ['cover_alt_text', 'description'],
        'users' => ['avatar_alt_text', 'avatar'],
        'channels' => ['banner_alt_text', 'banner_image'],
    ];

    public function up(): void
    {
        foreach (self::COLUMNS as $table => [$column, $after]) {
            if (!Schema::hasTable($table) || Schema::hasColumn($table, $column)) {
                continue;
            }

            Schema::table($table, function (Blueprint $t) use ($table, $column, $after) {
                $definition = $t->string($column, 255)->nullable();

                // ->after() is a MySQL-only positional hint; SQLite (used by the
                // test suite) ignores it, but guarding on the anchor column keeps
                // the migration safe on installs where it was renamed or dropped.
                if (Schema::hasColumn($table, $after)) {
                    $definition->after($after);
                }
            });
        }
    }

    public function down(): void
    {
        foreach (self::COLUMNS as $table => [$column, $after]) {
            if (!Schema::hasTable($table) || !Schema::hasColumn($table, $column)) {
                continue;
            }

            Schema::table($table, function (Blueprint $t) use ($column) {
                $t->dropColumn($column);
            });
        }
    }
};

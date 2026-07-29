<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('images', function (Blueprint $table) {
            $table->string('slug')->nullable()->after('uuid');
        });

        DB::table('images')->whereNull('slug')->chunkById(200, function ($images) {
            foreach ($images as $image) {
                DB::table('images')->where('id', $image->id)->update([
                    'slug' => $this->uniqueSlug($image->title, $image->id),
                ]);
            }
        });

        Schema::table('images', function (Blueprint $table) {
            $table->string('slug')->nullable(false)->unique()->change();
        });
    }

    public function down(): void
    {
        Schema::table('images', function (Blueprint $table) {
            $table->dropUnique(['slug']);
            $table->dropColumn('slug');
        });
    }

    protected function uniqueSlug(?string $title, int $ignoreId): string
    {
        $baseSlug = Str::slug((string) $title) ?: 'image-' . Str::lower(Str::random(6));
        $baseSlug = Str::limit($baseSlug, 200, '');

        $slug = $baseSlug;
        $suffix = 2;
        while (
            DB::table('images')->where('slug', $slug)->where('id', '!=', $ignoreId)->exists()
        ) {
            $slug = $baseSlug . '-' . $suffix;
            $suffix++;
        }

        return $slug;
    }
};

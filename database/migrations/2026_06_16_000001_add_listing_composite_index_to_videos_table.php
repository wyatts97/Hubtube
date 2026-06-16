<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds a composite covering index for the core public video listing query:
 *   WHERE privacy='public' AND status='processed' AND is_approved=1
 *   ORDER BY published_at DESC
 *
 * Without this, MySQL uses (privacy, status, is_approved) for the WHERE clause
 * but then must do a filesort for ORDER BY published_at, since the standalone
 * `published_at` index cannot also be used. The composite index allows the
 * optimizer to satisfy the full WHERE + ORDER BY in a single index scan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('videos', function (Blueprint $table) {
            $table->index(
                ['privacy', 'status', 'is_approved', 'published_at'],
                'videos_listing_composite_index'
            );
        });
    }

    public function down(): void
    {
        Schema::table('videos', function (Blueprint $table) {
            $table->dropIndex('videos_listing_composite_index');
        });
    }
};

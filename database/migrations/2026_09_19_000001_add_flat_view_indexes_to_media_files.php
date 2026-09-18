<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Indexes for browsing the whole library flat, rather than one folder.
 *
 * Every index the original media-index migration added is prefixed with
 * `directory_hash`, because everything browsed one folder at a time. The flat
 * view drops that predicate entirely — "the biggest files anywhere", "the
 * oldest videos" — and the only cross-root index was `(root, name_lower)`, so
 * a library-wide `ORDER BY size DESC` filesorted the whole table.
 *
 * An InnoDB secondary index implicitly carries the primary key, so `KEY (size)`
 * is really `(size, id)` and already satisfies the `ORDER BY size DESC, id DESC`
 * tiebreak the listing uses. That is why these are one and two columns wide
 * rather than spelling the tiebreak out.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('media_files', function (Blueprint $table) {
            // "Biggest files anywhere", and the storage report's top-N list.
            $table->index('size', 'media_files_size_index');

            // "Oldest files anywhere" — the age sort.
            $table->index('modified_at', 'media_files_modified_index');

            // "Videos only, biggest first": the view this whole feature is for.
            $table->index(['type', 'size'], 'media_files_type_size_index');

            // The storage report's originals figure: videos root, depth 2
            // (videos/{slug}/clip.mp4), type video, summed by size.
            $table->index(['root', 'depth', 'type', 'size'], 'media_files_root_depth_type_index');
        });
    }

    public function down(): void
    {
        Schema::table('media_files', function (Blueprint $table) {
            $table->dropIndex('media_files_size_index');
            $table->dropIndex('media_files_modified_index');
            $table->dropIndex('media_files_type_size_index');
            $table->dropIndex('media_files_root_depth_type_index');
        });
    }
};

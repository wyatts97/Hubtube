<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * An index of the files the admin Media Library browses.
 *
 * The page used to read storage/app/public live on every render: it listed a
 * directory, then stat'd, thumbnailed, ffprobed and ran two reference queries
 * for every file in it, and only then sliced down to one page. Cost scaled
 * with folder size rather than page size, so a folder of a few thousand files
 * took tens of seconds to open.
 *
 * These rows make browsing, searching, sorting and paginating indexed SQL.
 * The filesystem stays the source of truth — MediaIndexService keeps the index
 * in step, and `media:index` rebuilds it from scratch at any time.
 *
 * Every column an index is built on is an md5 hash, not a path. Paths can run
 * past InnoDB's 3072-byte key limit (768 utf8mb4 characters), which rules out
 * indexing `path` directly and makes a composite (directory, name) index
 * impossible at realistic column widths.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media_files', function (Blueprint $table) {
            $table->id();

            // One cheap column so a second disk is a WHERE rather than a
            // migration. Only 'public' is used today.
            $table->string('disk', 32)->default('public');

            $table->string('path', 1024);
            $table->char('path_hash', 32)->unique();

            // The containing directory, for exact-folder browsing.
            $table->string('directory', 1024);
            $table->char('directory_hash', 32);

            // Top-level allowed path ('videos', 'images', 'media'…). Recursive
            // search scopes on this, since a LIKE prefix cannot use
            // directory_hash.
            $table->string('root', 64);
            $table->unsignedTinyInteger('depth')->default(0);

            $table->string('name', 255);
            // Required, not a nicety: MySQL collation is case-insensitive but
            // SQLite's ORDER BY is case-sensitive, so without a folded column
            // sort order and search hits differ between the test suite and
            // production.
            $table->string('name_lower', 255);
            $table->string('extension', 16)->nullable();
            // image | video | audio | document | other
            $table->string('type', 16)->default('other');

            $table->unsignedBigInteger('size')->default(0);
            $table->timestamp('modified_at')->nullable();

            // Replaces an ffprobe subprocess per video per page render.
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->unsignedSmallInteger('width')->nullable();
            $table->unsignedSmallInteger('height')->nullable();

            $table->string('thumbnail_path', 1024)->nullable();
            // pending → queued → ready | unsupported | unavailable | failed
            $table->string('thumbnail_state', 16)->default('pending');
            $table->unsignedTinyInteger('thumbnail_attempts')->default(0);
            $table->string('thumbnail_error', 512)->nullable();
            $table->timestamp('thumbnail_generated_at')->nullable();

            // Denormalised so the "In use" badge costs no queries. Display and
            // button-disable only — MediaGuard re-checks live before deleting,
            // so a stale index can never authorise data loss.
            $table->boolean('is_referenced')->default(false);
            $table->json('references')->nullable();
            $table->timestamp('references_synced_at')->nullable();

            // Mirrors the videos/{slug} rules so the UI can disable rename and
            // delete without running a regex per row.
            $table->boolean('is_protected')->default(false);

            // Stamped with the pass start time on every scan; rows older than
            // it have vanished from disk.
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamp('indexed_at')->nullable();

            $table->timestamps();

            // Browse one folder, sorted each of the four ways.
            $table->index(['directory_hash', 'name_lower'], 'media_files_dir_name_index');
            $table->index(['directory_hash', 'modified_at'], 'media_files_dir_modified_index');
            $table->index(['directory_hash', 'size'], 'media_files_dir_size_index');
            // Type filter plus name sort; its (directory_hash, type) prefix
            // also serves the per-type counts.
            $table->index(['directory_hash', 'type', 'name_lower'], 'media_files_dir_type_index');
            // Recursive search within a root.
            $table->index(['root', 'name_lower'], 'media_files_root_name_index');
            // The thumbnail backfill worker's cursor.
            $table->index(['thumbnail_state', 'id'], 'media_files_thumb_state_index');
            $table->index('is_referenced');
            $table->index('last_seen_at');
        });

        Schema::create('media_folders', function (Blueprint $table) {
            $table->id();
            $table->string('disk', 32)->default('public');

            $table->string('path', 1024);
            $table->char('path_hash', 32)->unique();
            // Null for a root. The tree is assembled from this in one query.
            $table->char('parent_path_hash', 32)->nullable();

            $table->string('name', 255);
            $table->string('name_lower', 255);
            $table->string('root', 64);
            $table->unsignedTinyInteger('depth')->default(0);

            // Files directly in this folder.
            $table->unsignedInteger('direct_file_count')->default(0);
            $table->unsignedBigInteger('direct_size')->default(0);
            // Including every descendant. Maintained by MediaFolderRollup —
            // deriving these needed a LIKE 'path/%' aggregate per node, which
            // is the filesystem walk this table exists to remove.
            $table->unsignedInteger('total_file_count')->default(0);
            $table->unsignedBigInteger('total_size')->default(0);
            // Lets the tree draw an expand caret without loading children.
            $table->unsignedInteger('child_folder_count')->default(0);

            $table->timestamp('last_seen_at')->nullable();
            $table->timestamp('indexed_at')->nullable();

            $table->timestamps();

            $table->index(['parent_path_hash', 'name_lower'], 'media_folders_parent_name_index');
            $table->index(['root', 'depth'], 'media_folders_root_depth_index');
            $table->index('last_seen_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media_files');
        Schema::dropIfExists('media_folders');
    }
};

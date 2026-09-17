<?php

namespace App\Services\Media;

use App\Models\MediaFile;
use App\Models\MediaFolder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Keeps media_files and media_folders in step with the disk.
 *
 * The filesystem stays the source of truth. Files arrive without going through
 * the Media Library all the time — the encoder writes renditions into
 * videos/{slug}, ImageService writes variants into images/{uuid}, avatars land
 * from the settings controller — so this is called from those producers as
 * well as from `media:index` and from the page's own actions.
 *
 * Everything here works one directory at a time. That keeps memory flat on a
 * large library and makes a scan resumable, and it is the unit the page's
 * "rescan this folder" button needs.
 */
class MediaIndexService
{
    public function __construct(
        protected MediaPathGuard $paths,
        protected MediaFolderRollup $rollup,
        protected MediaReferenceResolver $references,
    ) {}

    /** How many files are upserted per query batch. */
    public const CHUNK = 500;

    /**
     * Index one file, creating or refreshing its row.
     *
     * Returns null when the path is not indexable (outside the allowed roots,
     * excluded, or gone from disk).
     */
    public function indexPath(string $path): ?MediaFile
    {
        $path = $this->paths->sanitize($path);

        if (! $this->paths->isAllowed($path)) {
            return null;
        }

        $disk = Storage::disk('public');

        try {
            if (! $disk->exists($path)) {
                $this->forgetPath($path);

                return null;
            }

            $size = $disk->size($path);
            $modified = $disk->lastModified($path);
        } catch (Throwable) {
            return null;
        }

        $file = $this->writeFile($path, $size, $modified, now()->startOfSecond());

        $this->references->syncForPaths([$path]);
        $this->touchFolder(dirname($path));
        $this->rollup->recompute([$this->directoryOf($path)]);

        return $file->refresh();
    }

    /**
     * Index a directory's files, optionally recursing.
     *
     * @return array{added: int, updated: int, removed: int, directories: int}
     */
    public function indexDirectory(
        string $directory,
        bool $recursive = false,
        ?Carbon $passStartedAt = null,
        bool $force = true,
    ): array {
        $directory = $this->paths->sanitize($directory);
        // Whole seconds: the column stores seconds, and the sweep below compares
        // this value for equality.
        $passStartedAt ??= now()->startOfSecond();

        $result = ['added' => 0, 'updated' => 0, 'removed' => 0, 'directories' => 0];

        if (! $this->paths->isAllowed($directory)) {
            return $result;
        }

        $disk = Storage::disk('public');

        if (! $disk->exists($directory)) {
            $this->forgetDirectory($directory);

            return $result;
        }

        // An unchanged directory can be skipped without listing it, but its
        // rows still have to be stamped or the prune sweep would delete them.
        // Children are visited either way: a directory's mtime says nothing
        // about its subdirectories.
        if (! $force && ! $this->isStale($directory)) {
            // toBase(): this is bookkeeping, so it must not move updated_at —
            // that column means "this row's data changed".
            MediaFile::query()
                ->where('directory_hash', md5($directory))
                ->toBase()
                ->update(['last_seen_at' => $passStartedAt]);

            MediaFolder::query()
                ->where('path_hash', md5($directory))
                ->toBase()
                ->update(['last_seen_at' => $passStartedAt]);

            return $this->indexChildren($directory, $recursive, $passStartedAt, $force, $result);
        }

        $this->touchFolder($directory, $passStartedAt);
        $result['directories']++;

        $seenHashes = [];
        $paths = [];

        try {
            $paths = $disk->files($directory);
        } catch (Throwable) {
            return $result;
        }

        $existing = MediaFile::query()
            ->where('directory_hash', md5($directory))
            ->get(['id', 'path', 'path_hash', 'size', 'modified_at'])
            ->keyBy('path_hash');

        foreach (array_chunk($paths, self::CHUNK) as $chunk) {
            $touched = [];

            foreach ($chunk as $path) {
                if ($this->paths->isExcluded($path)) {
                    continue;
                }

                try {
                    $size = $disk->size($path);
                    $modified = $disk->lastModified($path);
                } catch (Throwable) {
                    continue;
                }

                $hash = md5($path);
                $seenHashes[] = $hash;
                $previous = $existing->get($hash);

                // Only write when something actually changed, so a repeat scan
                // is cheap and leaves updated_at alone.
                $unchanged = $previous
                    && (int) $previous->size === (int) $size
                    && $previous->modified_at?->getTimestamp() === $modified;

                if ($unchanged) {
                    continue;
                }

                $this->writeFile($path, $size, $modified, $passStartedAt);
                $touched[] = $path;
                $previous ? $result['updated']++ : $result['added']++;
            }

            if ($touched !== []) {
                $this->references->syncForPaths($touched);
            }
        }

        // Stamp everything still present, in one query per chunk. toBase() so
        // this bookkeeping does not move updated_at.
        foreach (array_chunk($seenHashes, self::CHUNK) as $chunk) {
            MediaFile::query()
                ->whereIn('path_hash', $chunk)
                ->toBase()
                ->update(['last_seen_at' => $passStartedAt]);
        }

        // Rows for files that are no longer on disk, worked out as an exact set
        // difference between what was indexed for this directory and what the
        // listing just returned.
        //
        // Deliberately not "rows this pass did not stamp": two passes inside
        // the same second carry the same timestamp, and a `timestamp` column
        // has no sub-second resolution to tell them apart, so a comparison
        // against the stamp is wrong in one direction or the other.
        $vanished = $existing->keys()->diff($seenHashes)->values();

        foreach ($vanished->chunk(self::CHUNK) as $chunk) {
            $result['removed'] += MediaFile::query()
                ->whereIn('path_hash', $chunk->all())
                ->delete();
        }

        $result = $this->indexChildren($directory, $recursive, $passStartedAt, $force, $result);

        $this->rollup->recompute([$directory]);

        return $result;
    }

    /**
     * Recurse into a directory's subdirectories, accumulating their results.
     *
     * @param  array{added: int, updated: int, removed: int, directories: int}  $result
     * @return array{added: int, updated: int, removed: int, directories: int}
     */
    protected function indexChildren(
        string $directory,
        bool $recursive,
        Carbon $passStartedAt,
        bool $force,
        array $result,
    ): array {
        if (! $recursive) {
            return $result;
        }

        try {
            foreach (Storage::disk('public')->directories($directory) as $child) {
                if ($this->paths->isExcluded($child)) {
                    continue;
                }

                $childResult = $this->indexDirectory($child, true, $passStartedAt, $force);

                foreach ($childResult as $key => $value) {
                    $result[$key] += $value;
                }
            }
        } catch (Throwable) {
        }

        return $result;
    }

    /**
     * Index every allowed root.
     *
     * $full re-reads every directory; without it, directories whose mtime has
     * not moved since they were indexed are skipped.
     */
    public function indexAll(bool $prune = false, bool $full = true): array
    {
        $passStartedAt = now()->startOfSecond();
        $result = ['added' => 0, 'updated' => 0, 'removed' => 0, 'directories' => 0];

        foreach ($this->paths->allowedRoots() as $root) {
            $rootResult = $this->indexDirectory($root, true, $passStartedAt, $full);

            foreach ($rootResult as $key => $value) {
                $result[$key] += $value;
            }
        }

        if ($prune) {
            $result['removed'] += $this->prune();
        }

        $this->rollup->recomputeAll();

        return $result;
    }

    /**
     * Drop index rows whose file or directory is no longer on disk.
     *
     * Separate from the per-directory sweep, which can only see directories it
     * visited: a directory that vanished entirely leaves rows no scan will ever
     * walk into again.
     *
     * Checked against the filesystem row by row rather than against a pass
     * timestamp. That is one stat per indexed row, which is why this is a
     * weekly scheduled job and an explicit `--prune` rather than something the
     * request path does.
     */
    public function prune(): int
    {
        $removed = 0;
        $touchedFolders = [];

        MediaFile::query()
            ->select(['id', 'path', 'directory'])
            ->chunkById(self::CHUNK, function ($files) use (&$removed, &$touchedFolders) {
                $gone = [];

                foreach ($files as $file) {
                    if (! Storage::disk('public')->exists($file->path)) {
                        $gone[] = $file->id;
                        $touchedFolders[$file->directory] = true;
                    }
                }

                if ($gone !== []) {
                    $removed += MediaFile::query()->whereIn('id', $gone)->delete();
                }
            });

        MediaFolder::query()
            ->select(['id', 'path'])
            ->chunkById(self::CHUNK, function ($folders) use (&$touchedFolders) {
                $gone = [];

                foreach ($folders as $folder) {
                    if (! Storage::disk('public')->exists($folder->path)) {
                        $gone[] = $folder->id;
                        unset($touchedFolders[$folder->path]);
                    }
                }

                if ($gone !== []) {
                    MediaFolder::query()->whereIn('id', $gone)->delete();
                }
            });

        if ($touchedFolders !== []) {
            $this->rollup->recompute(array_keys($touchedFolders));
        }

        return $removed;
    }

    public function forgetPath(string $path): void
    {
        $path = $this->paths->sanitize($path);

        MediaFile::query()->where('path_hash', md5($path))->delete();
        $this->rollup->recompute([$this->directoryOf($path)]);
    }

    /** Remove a directory and everything indexed under it. */
    public function forgetDirectory(string $directory): void
    {
        $directory = $this->paths->sanitize($directory);
        $prefix = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $directory);

        MediaFile::query()
            ->where('directory_hash', md5($directory))
            ->orWhere('path', 'like', $prefix.'/%')
            ->delete();

        MediaFolder::query()
            ->where('path_hash', md5($directory))
            ->orWhere('path', 'like', $prefix.'/%')
            ->delete();

        $parent = $this->directoryOf($directory);

        if ($parent !== '') {
            $this->rollup->recompute([$parent]);
        }
    }

    /** Follow a file that moved, keeping one row rather than two. */
    public function movePath(string $oldPath, string $newPath): void
    {
        $this->forgetPath($oldPath);
        $this->indexPath($newPath);
    }

    /**
     * Whether the directory on disk has changed since it was last indexed.
     *
     * One filemtime() — not a walk. A directory's mtime moves when an entry is
     * added, removed or renamed, which covers everything that would make a
     * listing wrong. It does *not* move when a file's contents change, which is
     * why a periodic full scan still matters.
     *
     * PHP's filemtime() rather than Flysystem's lastModified(), which is
     * specified for files and throws for a directory. This is the one place the
     * local disk is assumed, and it is assumed deliberately.
     */
    public function isStale(string $directory): bool
    {
        $directory = $this->paths->sanitize($directory);

        if (! $this->paths->isAllowed($directory)) {
            return false;
        }

        $folder = MediaFolder::query()->where('path_hash', md5($directory))->first(['indexed_at']);

        if (! $folder || $folder->indexed_at === null) {
            return true;
        }

        try {
            $absolute = Storage::disk('public')->path($directory);
            $modified = is_dir($absolute) ? @filemtime($absolute) : false;
        } catch (Throwable) {
            return false;
        }

        return $modified !== false && $modified > $folder->indexed_at->getTimestamp();
    }

    /** Whether this directory has ever been indexed. */
    public function isIndexed(string $directory): bool
    {
        $directory = $this->paths->sanitize($directory);

        return MediaFolder::query()->where('path_hash', md5($directory))->exists();
    }

    /**
     * Create or refresh one file row.
     *
     * Thumbnail state resets to pending whenever the file's bytes changed, so
     * a replaced image gets a new thumbnail without anyone asking.
     */
    protected function writeFile(string $path, int $size, int $modified, Carbon $seenAt): MediaFile
    {
        $name = basename($path);
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $directory = $this->directoryOf($path);

        $attributes = [
            'disk' => 'public',
            'path' => $path,
            'directory' => $directory,
            'directory_hash' => md5($directory),
            'root' => $this->paths->rootOf($path),
            'depth' => substr_count($path, '/'),
            'name' => $name,
            'name_lower' => mb_strtolower($name),
            'extension' => $extension !== '' ? $extension : null,
            'type' => MediaType::forExtension($extension),
            'size' => $size,
            'modified_at' => Carbon::createFromTimestamp($modified),
            'is_protected' => $this->paths->isProtected($path),
            'last_seen_at' => $seenAt,
            'indexed_at' => $seenAt,
        ];

        $file = MediaFile::query()->where('path_hash', md5($path))->first();

        if (! $file) {
            return MediaFile::query()->create($attributes + [
                'path_hash' => md5($path),
                'thumbnail_state' => MediaFile::THUMB_PENDING,
            ]);
        }

        $bytesChanged = (int) $file->size !== $size
            || $file->modified_at?->getTimestamp() !== $modified;

        if ($bytesChanged) {
            $attributes['thumbnail_state'] = MediaFile::THUMB_PENDING;
            $attributes['thumbnail_attempts'] = 0;
            $attributes['thumbnail_error'] = null;
            $attributes['duration_seconds'] = null;
        }

        $file->update($attributes);

        return $file;
    }

    /** Create or refresh a folder row, and its ancestors' rows. */
    protected function touchFolder(string $directory, ?Carbon $seenAt = null): void
    {
        $directory = $this->paths->sanitize($directory);

        if ($directory === '' || ! $this->paths->isAllowed($directory)) {
            return;
        }

        $seenAt ??= now();

        foreach (array_merge([$directory], MediaFolder::ancestorPaths($directory)) as $path) {
            $name = basename($path);
            $parent = $this->directoryOf($path);

            MediaFolder::query()->updateOrCreate(
                ['path_hash' => md5($path)],
                [
                    'disk' => 'public',
                    'path' => $path,
                    'parent_path_hash' => $parent !== '' ? md5($parent) : null,
                    'name' => $name,
                    'name_lower' => mb_strtolower($name),
                    'root' => $this->paths->rootOf($path),
                    'depth' => substr_count($path, '/'),
                    'last_seen_at' => $seenAt,
                    'indexed_at' => $seenAt,
                ]
            );
        }
    }

    /** dirname() that yields '' rather than '.' for a top-level path. */
    protected function directoryOf(string $path): string
    {
        $directory = str_contains($path, '/') ? dirname($path) : '';

        return $directory === '.' ? '' : $directory;
    }
}

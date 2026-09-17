<?php

namespace App\Services\Media;

use App\Models\Image;
use App\Models\MediaFile;
use App\Models\Video;

/**
 * Which database records point at a file on disk.
 *
 * This replaces the Media Library's per-file lookups. The page called
 * Video::findByFilePath() and Image::findByFilePath() once per file while
 * building a listing — two `SELECT *` queries with a five-column OR each — so
 * a 500-file folder ran 1,000 queries per render. Here the whole batch is
 * answered in two.
 *
 * It also closes a hole those helpers had: neither used withTrashed(), so a
 * soft-deleted video's files looked unreferenced and were deletable from the
 * library, silently breaking the 30-day restore window that
 * `videos:prune-deleted` provides.
 */
class MediaReferenceResolver
{
    /** Video columns that hold a path on the media disk. */
    public const VIDEO_PATH_COLUMNS = [
        'video_path',
        'thumbnail',
        'preview_path',
        'trailer_path',
        'scrubber_vtt_path',
    ];

    /** Image columns that hold a path on the media disk. */
    public const IMAGE_PATH_COLUMNS = [
        'file_path',
        'thumbnail_path',
    ];

    /**
     * References for many paths at once.
     *
     * @param  list<string>  $paths
     * @return array<string, list<array{model: string, id: int, field: string}>>
     *                                                                           keyed by path; paths with no references are absent
     */
    public function forPaths(array $paths): array
    {
        $paths = array_values(array_unique(array_filter($paths)));

        if ($paths === []) {
            return [];
        }

        $found = [];

        // withTrashed(): a soft-deleted video still owns its files.
        $videos = Video::withTrashed()
            ->select(array_merge(['id'], self::VIDEO_PATH_COLUMNS))
            ->where(function ($query) use ($paths) {
                foreach (self::VIDEO_PATH_COLUMNS as $column) {
                    $query->orWhereIn($column, $paths);
                }
            })
            ->get();

        foreach ($videos as $video) {
            foreach (self::VIDEO_PATH_COLUMNS as $column) {
                $value = $video->getAttribute($column);

                if ($value !== null && in_array($value, $paths, true)) {
                    $found[$value][] = ['model' => 'Video', 'id' => $video->id, 'field' => $column];
                }
            }
        }

        $images = Image::query()
            ->select(array_merge(['id'], self::IMAGE_PATH_COLUMNS))
            ->where(function ($query) use ($paths) {
                foreach (self::IMAGE_PATH_COLUMNS as $column) {
                    $query->orWhereIn($column, $paths);
                }
            })
            ->get();

        foreach ($images as $image) {
            foreach (self::IMAGE_PATH_COLUMNS as $column) {
                $value = $image->getAttribute($column);

                if ($value !== null && in_array($value, $paths, true)) {
                    $found[$value][] = ['model' => 'Image', 'id' => $image->id, 'field' => $column];
                }
            }
        }

        return $found;
    }

    /**
     * References for a single path.
     *
     * This is the authoritative check — MediaGuard calls it live at the moment
     * of deletion rather than trusting the indexed flag.
     *
     * @return list<array{model: string, id: int, field: string}>
     */
    public function forPath(string $path): array
    {
        return $this->forPaths([$path])[$path] ?? [];
    }

    /**
     * Write the reference state of these paths onto their index rows.
     *
     * Two queries for the lookup plus two updates, whatever the batch size.
     *
     * @param  list<string>  $paths
     */
    public function syncForPaths(array $paths): void
    {
        $paths = array_values(array_unique(array_filter($paths)));

        if ($paths === []) {
            return;
        }

        $found = $this->forPaths($paths);
        $now = now();

        // Clear the whole batch first, so a reference that was removed since
        // the last sync does not linger.
        MediaFile::query()
            ->whereIn('path_hash', array_map('md5', $paths))
            ->update([
                'is_referenced' => false,
                'references' => null,
                'references_synced_at' => $now,
            ]);

        foreach ($found as $path => $references) {
            MediaFile::query()
                ->where('path_hash', md5($path))
                ->update([
                    'is_referenced' => true,
                    'references' => $references,
                    'references_synced_at' => $now,
                ]);
        }
    }

    /**
     * The referencing records for one file, hydrated for the details panel.
     *
     * Only ever called for the single selected file, so the two extra queries
     * buy a real title and a working link to the record instead of the bare
     * "Video #17 (thumbnail)" the panel used to show.
     *
     * @return list<array{model: string, id: int, field: string, title: ?string}>
     */
    public function describe(MediaFile $file): array
    {
        $references = $this->forPath($file->path);

        if ($references === []) {
            return [];
        }

        $videoIds = collect($references)->where('model', 'Video')->pluck('id')->all();
        $imageIds = collect($references)->where('model', 'Image')->pluck('id')->all();

        $videoTitles = $videoIds === []
            ? collect()
            : Video::withTrashed()->whereIn('id', $videoIds)->pluck('title', 'id');

        $imageTitles = $imageIds === []
            ? collect()
            : Image::whereIn('id', $imageIds)->pluck('title', 'id');

        return collect($references)
            ->map(fn (array $reference) => $reference + [
                'title' => $reference['model'] === 'Video'
                    ? $videoTitles->get($reference['id'])
                    : $imageTitles->get($reference['id']),
            ])
            ->values()
            ->all();
    }
}

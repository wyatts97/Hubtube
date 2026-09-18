<?php

namespace App\Services\Media;

use App\Models\MediaFile;
use App\Models\MediaFolder;
use App\Support\Bytes;
use Illuminate\Support\Facades\Cache;

/**
 * Where the disk is actually going.
 *
 * The Media Library could tell you what was in a folder but never what was
 * eating the drive, which is the question that prompts anyone to go looking in
 * the first place. Every figure here comes from the index or from the folder
 * rollups MediaFolderRollup already maintains — no filesystem walk, and no
 * `LIKE 'path/%'` aggregate, which is the recursive walk this index exists to
 * remove.
 *
 * Memoised as a whole, because it is rendered on every page load and the
 * numbers only move when the index does.
 */
class MediaStorageReport
{
    public const CACHE_KEY = 'media:storage-report';

    public function all(): array
    {
        return Cache::remember(
            self::CACHE_KEY,
            (int) config('hubtube.media_library.cache_ttl', 300),
            fn () => [
                'roots' => $this->perRoot(),
                'total_bytes' => $this->totalBytes(),
                'total_files' => $this->totalFiles(),
                'videos' => $this->videoBreakdown(),
                'reclaimable_hls' => $this->reclaimableHls(),
                'indexed_at' => $this->indexedAt(),
            ]
        );
    }

    public static function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * One row per allowed root, from the maintained rollups.
     *
     * @return array<int, array{path: string, files: int, bytes: int, formatted: string}>
     */
    public function perRoot(): array
    {
        return MediaFolder::query()
            ->roots()
            ->orderByDesc('total_size')
            ->get(['path', 'total_file_count', 'total_size'])
            ->map(fn (MediaFolder $folder) => [
                'path' => $folder->path,
                'files' => $folder->total_file_count,
                'bytes' => $folder->total_size,
                'formatted' => Bytes::format($folder->total_size),
            ])
            ->all();
    }

    public function totalBytes(): int
    {
        return (int) MediaFolder::query()->roots()->sum('total_size');
    }

    public function totalFiles(): int
    {
        return (int) MediaFolder::query()->roots()->sum('total_file_count');
    }

    /**
     * The bytes held by duplicate HLS copies.
     *
     * `generate_hls` defaults to on, so `videos/{slug}/processed/hls` holds a
     * complete second copy of every rendition as .ts segments. That directory
     * sits at depth 3, which is exactly what MediaIndexService writes, so this
     * is one indexed aggregate over media_folders_root_depth_index — and it is
     * the one number on this page that can be reclaimed with no re-encoding at
     * all.
     */
    public function reclaimableHls(): int
    {
        return (int) MediaFolder::query()
            ->where('root', 'videos')
            ->where('depth', 3)
            ->where('name_lower', 'hls')
            ->sum('total_size');
    }

    /**
     * How the videos root splits between originals, renditions and artwork.
     *
     * Originals are the files at `videos/{slug}/clip.mp4` — depth 2, type
     * video — which is what the (root, depth, type, size) index is for.
     * Renditions are inferred as the `processed` total minus the HLS total,
     * because `processed/` also holds the master playlist and transient chunk
     * directories and enumerating those would cost a walk.
     */
    public function videoBreakdown(): array
    {
        $rootTotal = (int) MediaFolder::query()
            ->where('path', 'videos')
            ->value('total_size');

        $processed = (int) MediaFolder::query()
            ->where('root', 'videos')
            ->where('depth', 2)
            ->where('name_lower', 'processed')
            ->sum('total_size');

        $hls = $this->reclaimableHls();

        $originals = (int) MediaFile::query()
            ->where('root', 'videos')
            ->where('depth', 2)
            ->where('type', 'video')
            ->sum('size');

        return [
            'total' => $rootTotal,
            'originals' => $originals,
            'renditions' => max(0, $processed - $hls),
            'hls' => $hls,
            // Posters, animated previews, sprite sheets and the scrubber VTT.
            'artwork' => max(0, $rootTotal - $processed - $originals),
        ];
    }

    /**
     * The biggest files in the library.
     *
     * @return array<int, array{path: string, name: string, type: string, bytes: int, formatted: string}>
     */
    public function biggestFiles(int $limit = 20, ?string $type = null): array
    {
        return MediaFile::query()
            ->ofType($type)
            ->orderByDesc('size')
            ->limit($limit)
            ->get(['path', 'name', 'type', 'size'])
            ->map(fn (MediaFile $file) => [
                'path' => $file->path,
                'name' => $file->name,
                'type' => $file->type,
                'bytes' => (int) $file->size,
                'formatted' => Bytes::format((int) $file->size),
            ])
            ->all();
    }

    /**
     * When the least recently indexed root was last scanned.
     *
     * Surfaced so the panel can say "as of 14:05" rather than presenting
     * rollup figures as live truth.
     */
    public function indexedAt(): ?string
    {
        $oldest = MediaFolder::query()->roots()->min('indexed_at');

        return $oldest ? (string) $oldest : null;
    }
}

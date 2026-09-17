<?php

namespace App\Services\Media;

use App\Models\MediaFile;
use App\Models\MediaFolder;
use Illuminate\Support\Facades\DB;

/**
 * Keeps media_folders' file counts and sizes correct.
 *
 * Direct counts come from one grouped query over media_files. Totals are then
 * folded upwards, deepest folder first, so each level only has to add its own
 * direct figures to the totals its children already hold.
 *
 * The alternative — computing recursive counts on demand — needs a
 * `LIKE 'path/%'` aggregate per node, which is the recursive filesystem walk
 * the index was built to remove, rewritten in SQL.
 */
class MediaFolderRollup
{
    /**
     * Recompute direct counts for these folders, then totals for them and
     * every ancestor.
     *
     * @param  list<string>  $folderPaths
     */
    public function recompute(array $folderPaths): void
    {
        $folderPaths = array_values(array_unique(array_filter($folderPaths)));

        if ($folderPaths === []) {
            return;
        }

        // Ancestors are affected by any change below them.
        $affected = $folderPaths;

        foreach ($folderPaths as $path) {
            foreach (MediaFolder::ancestorPaths($path) as $ancestor) {
                $affected[] = $ancestor;
            }
        }

        $this->recomputeDirect($folderPaths);
        $this->recomputeTotals(array_values(array_unique($affected)));
    }

    /** Recompute everything, deepest first. Used by a full scan. */
    public function recomputeAll(): void
    {
        $paths = MediaFolder::query()->pluck('path')->all();

        if ($paths === []) {
            return;
        }

        $this->recomputeDirect($paths);
        $this->recomputeTotals($paths);
    }

    /**
     * Files sitting directly in each folder — one grouped query for the lot.
     *
     * @param  list<string>  $folderPaths
     */
    protected function recomputeDirect(array $folderPaths): void
    {
        foreach (array_chunk($folderPaths, 200) as $chunk) {
            $hashes = array_map('md5', $chunk);

            $counts = MediaFile::query()
                ->whereIn('directory_hash', $hashes)
                ->groupBy('directory_hash')
                ->selectRaw('directory_hash, COUNT(*) as file_count, COALESCE(SUM(size), 0) as total_size')
                ->get()
                ->keyBy('directory_hash');

            $childCounts = MediaFolder::query()
                ->whereIn('parent_path_hash', $hashes)
                ->groupBy('parent_path_hash')
                ->selectRaw('parent_path_hash, COUNT(*) as child_count')
                ->pluck('child_count', 'parent_path_hash');

            foreach ($chunk as $path) {
                $hash = md5($path);
                $row = $counts->get($hash);

                MediaFolder::query()
                    ->where('path_hash', $hash)
                    ->update([
                        'direct_file_count' => (int) ($row->file_count ?? 0),
                        'direct_size' => (int) ($row->total_size ?? 0),
                        'child_folder_count' => (int) ($childCounts[$hash] ?? 0),
                    ]);
            }
        }
    }

    /**
     * Fold direct figures upward. Ordered deepest-first so a parent always
     * reads totals its children have already settled.
     *
     * @param  list<string>  $folderPaths
     */
    protected function recomputeTotals(array $folderPaths): void
    {
        $folders = MediaFolder::query()
            ->whereIn('path_hash', array_map('md5', $folderPaths))
            ->orderByDesc('depth')
            ->get(['id', 'path', 'path_hash', 'depth', 'direct_file_count', 'direct_size']);

        foreach ($folders as $folder) {
            $children = DB::table('media_folders')
                ->where('parent_path_hash', $folder->path_hash)
                ->selectRaw('COALESCE(SUM(total_file_count), 0) as file_count, COALESCE(SUM(total_size), 0) as total_size')
                ->first();

            MediaFolder::query()
                ->whereKey($folder->id)
                ->update([
                    'total_file_count' => $folder->direct_file_count + (int) ($children->file_count ?? 0),
                    'total_size' => $folder->direct_size + (int) ($children->total_size ?? 0),
                ]);
        }
    }
}

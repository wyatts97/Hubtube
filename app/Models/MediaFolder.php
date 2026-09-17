<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * One directory on the media disk, with its file counts and sizes rolled up.
 *
 * A real table rather than a derived view: recursive counts computed on demand
 * need a `LIKE 'path/%'` aggregate per node, which is precisely the recursive
 * filesystem walk the index exists to remove. With the rollups maintained (see
 * App\Services\Media\MediaFolderRollup) the whole sidebar tree is one query.
 */
class MediaFolder extends Model
{
    use HasFactory;

    protected $fillable = [
        'disk',
        'path',
        'path_hash',
        'parent_path_hash',
        'name',
        'name_lower',
        'root',
        'depth',
        'direct_file_count',
        'direct_size',
        'total_file_count',
        'total_size',
        'child_folder_count',
        'last_seen_at',
        'indexed_at',
    ];

    protected function casts(): array
    {
        return [
            'depth' => 'integer',
            'direct_file_count' => 'integer',
            'direct_size' => 'integer',
            'total_file_count' => 'integer',
            'total_size' => 'integer',
            'child_folder_count' => 'integer',
            'last_seen_at' => 'datetime',
            'indexed_at' => 'datetime',
        ];
    }

    public function scopeRoots($query)
    {
        return $query->whereNull('parent_path_hash');
    }

    public function scopeChildrenOf($query, string $path)
    {
        return $query->where('parent_path_hash', md5($path));
    }

    public function hasChildren(): bool
    {
        return $this->child_folder_count > 0;
    }

    /**
     * Every ancestor directory path of $path, nearest first, stopping before
     * the root segment's parent (there is none).
     *
     * 'media/2026/01' → ['media/2026', 'media']
     *
     * @return list<string>
     */
    public static function ancestorPaths(string $path): array
    {
        $segments = array_values(array_filter(explode('/', trim($path, '/')), fn ($s) => $s !== ''));
        $ancestors = [];

        while (count($segments) > 1) {
            array_pop($segments);
            $ancestors[] = implode('/', $segments);
        }

        return $ancestors;
    }

    /** The top-level segment of a path, which is its allowed root. */
    public static function rootOf(string $path): string
    {
        $segments = explode('/', trim($path, '/'));

        return $segments[0] ?? '';
    }
}

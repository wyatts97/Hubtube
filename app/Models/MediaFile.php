<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * One file on the media disk, as the admin Media Library sees it.
 *
 * The filesystem remains the source of truth; this is an index of it (see
 * App\Services\Media\MediaIndexService). Nothing here is authoritative for
 * permission or deletion decisions — `is_referenced` drives a badge, while
 * MediaGuard re-checks references live before anything is removed.
 */
class MediaFile extends Model
{
    use HasFactory;

    /** Thumbnail lifecycle. */
    public const THUMB_PENDING = 'pending';

    public const THUMB_QUEUED = 'queued';

    public const THUMB_READY = 'ready';

    /** The format cannot be rendered at all (bmp, ico, …) — never retried. */
    public const THUMB_UNSUPPORTED = 'unsupported';

    /** No ffmpeg on the box. Retryable once one is installed. */
    public const THUMB_UNAVAILABLE = 'unavailable';

    public const THUMB_FAILED = 'failed';

    /** States that will never change without the file or the box changing. */
    public const THUMB_SETTLED = [
        self::THUMB_READY,
        self::THUMB_UNSUPPORTED,
        self::THUMB_FAILED,
    ];

    public const TYPES = ['image', 'video', 'audio', 'document', 'other'];

    protected $fillable = [
        'disk',
        'path',
        'path_hash',
        'directory',
        'directory_hash',
        'root',
        'depth',
        'name',
        'name_lower',
        'extension',
        'type',
        'size',
        'modified_at',
        'duration_seconds',
        'width',
        'height',
        'thumbnail_path',
        'thumbnail_state',
        'thumbnail_attempts',
        'thumbnail_error',
        'thumbnail_generated_at',
        'is_referenced',
        'references',
        'references_synced_at',
        'is_protected',
        'last_seen_at',
        'indexed_at',
    ];

    protected function casts(): array
    {
        return [
            'modified_at' => 'datetime',
            'thumbnail_generated_at' => 'datetime',
            'references_synced_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'indexed_at' => 'datetime',
            'is_referenced' => 'boolean',
            'is_protected' => 'boolean',
            'references' => 'array',
            'size' => 'integer',
            'depth' => 'integer',
            'duration_seconds' => 'integer',
        ];
    }

    /** Files directly inside one directory. */
    public function scopeInDirectory($query, string $directory)
    {
        return $query->where('directory_hash', md5($directory));
    }

    /** Files anywhere under one root, for a library-wide search. */
    public function scopeInRoot($query, string $root)
    {
        return $query->where('root', $root);
    }

    /**
     * Files whose path begins with $directory, i.e. a folder and everything
     * under it. Falls back to a prefix LIKE because no hash can express
     * "descendant of".
     */
    public function scopeUnderDirectory($query, string $directory)
    {
        $prefix = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $directory);

        return $query->where(function ($q) use ($directory, $prefix) {
            $q->where('directory_hash', md5($directory))
                ->orWhere('path', 'like', $prefix.'/%');
        });
    }

    public function scopeOfType($query, ?string $type)
    {
        return in_array($type, self::TYPES, true) ? $query->where('type', $type) : $query;
    }

    /** Case-insensitively match part of a filename. */
    public function scopeMatchingName($query, ?string $term)
    {
        if ($term === null || trim($term) === '') {
            return $query;
        }

        $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], mb_strtolower(trim($term)));

        return $query->where('name_lower', 'like', '%'.$escaped.'%');
    }

    /** Rows whose thumbnail is still worth attempting. */
    public function scopeAwaitingThumbnail($query)
    {
        return $query->whereIn('thumbnail_state', [self::THUMB_PENDING, self::THUMB_UNAVAILABLE]);
    }

    public function hasThumbnail(): bool
    {
        return $this->thumbnail_state === self::THUMB_READY && $this->thumbnail_path !== null;
    }

    /** Whether a thumbnail is still coming, so the tile shows a placeholder. */
    public function thumbnailPending(): bool
    {
        return ! in_array($this->thumbnail_state, self::THUMB_SETTLED, true);
    }
}

<?php

namespace App\Models;

use App\Services\AltTextService;
use App\Services\StorageManager;
use App\Traits\Translatable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Scout\Searchable;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Throwable;

class Video extends Model
{
    use HasFactory, LogsActivity, Searchable, SoftDeletes, Translatable;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['is_approved', 'is_featured', 'status', 'privacy', 'age_restricted'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('admin');
    }

    protected $fillable = [
        'user_id',
        'uuid',
        'title',
        'slug',
        'description',
        'thumbnail',
        'thumbnail_alt_text',
        'preview_path',
        'video_path',
        'storage_disk',
        'trailer_path',
        'duration',
        'size',
        'privacy',
        'status',
        'failure_reason',
        'processing_fallback_reason',
        'is_featured',
        'is_approved',
        'age_restricted',
        'is_portrait',
        'monetization_enabled',
        'price',
        'rent_price',
        'views_count',
        'likes_count',
        'dislikes_count',
        'comments_count',
        'category_id',
        'qualities_available',
        'geo_blocked_countries',
        'tags',
        'published_at',
        'scheduled_at',
        'requires_schedule',
        'suppress_notifications',
        'queue_order',
        'processing_started_at',
        'processing_completed_at',
        'scrubber_vtt_path',
        'is_embedded',
        'embed_url',
        'embed_code',
        'external_thumbnail_url',
        'external_preview_url',
        'source_site',
        'source_video_id',
        'source_url',
    ];

    protected $appends = [
        'video_url',
        'thumbnail_url',
        'thumbnail_alt',
        'preview_url',
        'preview_thumbnails_url',
        'formatted_duration',
        'rating_percent',
        'quality_label',
    ];

    protected function casts(): array
    {
        return [
            'is_embedded' => 'boolean',
            'is_featured' => 'boolean',
            'is_approved' => 'boolean',
            'age_restricted' => 'boolean',
            'is_portrait' => 'boolean',
            'monetization_enabled' => 'boolean',
            'price' => 'decimal:2',
            'rent_price' => 'decimal:2',
            'qualities_available' => 'array',
            'geo_blocked_countries' => 'array',
            'tags' => 'array',
            'published_at' => 'datetime',
            'scheduled_at' => 'datetime',
            'processing_started_at' => 'datetime',
            'processing_completed_at' => 'datetime',
            'requires_schedule' => 'boolean',
            'suppress_notifications' => 'boolean',
        ];
    }

    public static function normalizeTagsInput(mixed $tags): array
    {
        if ($tags === null) {
            return [];
        }

        if (is_string($tags)) {
            $decoded = json_decode($tags, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $tags = $decoded;
            }
        }

        if (is_string($tags)) {
            $tags = preg_split('/[\r\n,]+/', $tags) ?: [];
        }

        if (! is_array($tags)) {
            return [];
        }

        // Recover legacy corruption where tags arrived as a character array
        // (e.g. ['T','w','i','n','k',',','S','e','l','f']).
        $singleCharCount = count(array_filter(
            $tags,
            fn ($tag) => is_string($tag) && mb_strlen(trim($tag)) <= 1
        ));

        if (count($tags) >= 3 && ($singleCharCount / count($tags)) >= 0.5) {
            $tags = preg_split('/[\r\n,]+/', implode('', $tags)) ?: [];
        }

        $normalized = [];
        foreach ($tags as $tag) {
            if (! is_scalar($tag)) {
                continue;
            }

            foreach ((preg_split('/[\r\n,]+/', (string) $tag) ?: []) as $part) {
                $part = ltrim(trim($part), '#');
                if ($part !== '') {
                    $normalized[] = $part;
                }
            }
        }

        return array_values(array_unique($normalized));
    }

    public function getTagsAttribute($value): array
    {
        return static::normalizeTagsInput($value);
    }

    protected static function booted(): void
    {
        // Flush homepage, trending, category, and related-video caches when a video changes
        $flushCaches = function (Video $video) {
            $cache = Cache::class;
            // Home caches
            $cache::forget('home:featured');
            $cache::forget('home:popular');
            // Category page caches (flush all pages for this category)
            if ($video->category_id) {
                for ($p = 1; $p <= 20; $p++) {
                    $cache::forget("category:{$video->category_id}:page:{$p}");
                }
                // Also bust categories list (video count changed)
                $cache::forget('categories:active:with_thumbs');
            }
            // Related videos on the video's own show page
            $cache::forget("video:{$video->id}:related");
            // Trending page caches (all periods, first 5 pages)
            foreach (['today', 'week', 'month', 'year', 'all'] as $period) {
                for ($p = 1; $p <= 5; $p++) {
                    $cache::forget("trending:{$period}:page:{$p}");
                }
            }
        };

        static::created($flushCaches);
        static::updated($flushCaches);
        static::deleted($flushCaches);

        static::saving(function (Video $video) {
            $normalizedTags = static::normalizeTagsInput($video->getAttribute('tags'));
            $video->setAttribute('tags', empty($normalizedTags) ? null : $normalizedTags);
        });

        // Invalidate cached translations when title or description changes so
        // stale translated slugs/text don't keep serving the old value.
        static::updated(function (Video $video) {
            $stale = [];
            if ($video->wasChanged('title')) {
                $stale[] = 'title';
            }
            if ($video->wasChanged('description')) {
                $stale[] = 'description';
            }
            if (! empty($stale)) {
                try {
                    Translation::where('translatable_type', static::class)
                        ->where('translatable_id', $video->id)
                        ->whereIn('field', $stale)
                        ->delete();
                } catch (Throwable $e) {
                    Log::debug('Translation invalidation failed', [
                        'video_id' => $video->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        });

        static::deleting(function (Video $video) {
            // Skip storage cleanup for embedded videos (no local files)
            if ($video->is_embedded) {
                return;
            }

            $disk = $video->storage_disk ?? 'public';

            // Delete the video directory and all contents (original + processed/ + hls/ + sprites/)
            $videoDir = "videos/{$video->slug}";
            if ($video->slug && StorageManager::exists($videoDir, $disk)) {
                StorageManager::deleteDirectory($videoDir, $disk);
            }

            // Legacy path cleanup (older uploads used user_id/uuid structure)
            if ($video->uuid) {
                $legacyDir = "videos/{$video->user_id}/{$video->uuid}";
                if (StorageManager::exists($legacyDir, $disk)) {
                    StorageManager::deleteDirectory($legacyDir, $disk);
                }
            }

            // Delete thumbnail if stored outside video dir (legacy path)
            if ($video->thumbnail && ! str_starts_with($video->thumbnail, 'videos/')) {
                StorageManager::delete($video->thumbnail, $disk);
            }
        });
    }

    public function shouldBeSearchable(): bool
    {
        // Only index if using a real search driver (not database or null)
        $driver = config('scout.driver');
        if (in_array($driver, ['database', 'null', null])) {
            return false;
        }

        return $this->status === 'processed'
            && $this->is_approved
            && $this->privacy === 'public';
    }

    public function toSearchableArray(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'tags' => $this->tags,
            'user_id' => $this->user_id,
            'category_id' => $this->category_id,
            'views_count' => (int) $this->views_count,
            'likes_count' => (int) $this->likes_count,
            'created_at' => $this->created_at?->timestamp,
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }

    public function likes(): HasMany
    {
        return $this->hasMany(Like::class);
    }

    public function views(): HasMany
    {
        return $this->hasMany(VideoView::class);
    }

    public function playlists(): BelongsToMany
    {
        return $this->belongsToMany(Playlist::class, 'playlist_videos')
            ->withPivot('position')
            ->withTimestamps();
    }

    public function hashtags(): BelongsToMany
    {
        return $this->belongsToMany(Hashtag::class, 'video_hashtags')
            ->withTimestamps();
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(VideoTransaction::class);
    }

    public function scopePublic($query)
    {
        return $query->where('privacy', 'public');
    }

    public function scopeApproved($query)
    {
        return $query->where('is_approved', true)->whereNotNull('published_at');
    }

    public function scopeProcessed($query)
    {
        return $query->where('status', 'processed');
    }

    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true);
    }

    public function scopeShorts($query)
    {
        return $query->where('is_portrait', true)->where('duration', '<', 60);
    }

    /**
     * Minimum combined votes before a rating is shown. Without a floor a single
     * like reads as "100% liked", which is both meaningless and easy to game.
     */
    public const RATING_MIN_VOTES = 5;

    /** Duration buckets used by the browse and search filter rail, in seconds. */
    public const DURATION_BUCKETS = [
        'short'  => [0, 300],
        'medium' => [300, 1200],
        'long'   => [1200, null],
    ];

    /** Minimum vertical resolution for each quality filter band. */
    public const QUALITY_BANDS = [
        'hd'  => 720,
        'fhd' => 1080,
        'uhd' => 2160,
    ];

    /**
     * Percentage of votes that were positive, or null when there aren't enough
     * votes to be meaningful. Derived from the existing likes_count and
     * dislikes_count columns — there is no separate ratings table.
     */
    public function getRatingPercentAttribute(): ?int
    {
        $likes = (int) $this->likes_count;
        $dislikes = (int) $this->dislikes_count;
        $total = $likes + $dislikes;

        if ($total < self::RATING_MIN_VOTES) {
            return null;
        }

        return (int) round(($likes / $total) * 100);
    }

    /**
     * Highest resolution band available, as a short badge label ('4K', 'HD') or
     * null. Reads the qualities_available JSON the transcoder writes, whose
     * entries look like '1080p' / '720p' / 'original'.
     */
    public function getQualityLabelAttribute(): ?string
    {
        $heights = $this->qualityHeights();

        if (empty($heights)) {
            return null;
        }

        $max = max($heights);

        if ($max >= self::QUALITY_BANDS['uhd']) {
            return '4K';
        }

        return $max >= self::QUALITY_BANDS['hd'] ? 'HD' : null;
    }

    /** Numeric heights parsed out of qualities_available, e.g. ['1080p'] => [1080]. */
    protected function qualityHeights(): array
    {
        $qualities = $this->qualities_available;

        if (! is_array($qualities)) {
            return [];
        }

        return array_values(array_filter(array_map(
            fn ($quality) => (int) filter_var((string) $quality, FILTER_SANITIZE_NUMBER_INT),
            $qualities
        )));
    }

    /** Filter by one of the DURATION_BUCKETS keys. Unknown keys are ignored. */
    public function scopeOfDuration($query, ?string $bucket)
    {
        if (! isset(self::DURATION_BUCKETS[$bucket])) {
            return $query;
        }

        [$min, $max] = self::DURATION_BUCKETS[$bucket];

        $query->where('duration', '>=', $min);

        return $max === null ? $query : $query->where('duration', '<', $max);
    }

    /**
     * Filter by minimum resolution.
     *
     * qualities_available is JSON, and portable JSON querying across MySQL and
     * SQLite is not worth the complexity here: matching the rendered strings
     * ('1080p', '2160p') covers every value the transcoder actually writes.
     */
    public function scopeOfQuality($query, ?string $band)
    {
        if (! isset(self::QUALITY_BANDS[$band])) {
            return $query;
        }

        $minHeight = self::QUALITY_BANDS[$band];
        $labels = array_filter(
            ['2160p', '1440p', '1080p', '720p'],
            fn ($label) => (int) $label >= $minHeight
        );

        return $query->where(function ($q) use ($labels) {
            foreach ($labels as $label) {
                $q->orWhere('qualities_available', 'like', '%"' . $label . '"%');
            }
        });
    }

    /** Filter by upload recency: today | week | month | year. */
    public function scopeUploadedWithin($query, ?string $period)
    {
        $since = match ($period) {
            'today' => now()->subDay(),
            'week' => now()->subWeek(),
            'month' => now()->subMonth(),
            'year' => now()->subYear(),
            default => null,
        };

        return $since ? $query->where('published_at', '>=', $since) : $query;
    }

    /**
     * Apply one of the browse/search sort orders.
     *
     * 'rating' orders by the same like ratio as getRatingPercentAttribute, with
     * the vote floor applied in SQL so barely-voted videos can't top the list.
     * Computed rather than stored: a generated column is only worth adding if
     * this shows up as a slow query.
     */
    public function scopeSortedBy($query, ?string $sort)
    {
        return match ($sort) {
            'popular' => $query->orderByDesc('views_count'),
            'oldest' => $query->orderBy('published_at'),
            'longest' => $query->orderByDesc('duration'),
            'rating' => $query
                ->whereRaw('(likes_count + dislikes_count) >= ?', [self::RATING_MIN_VOTES])
                ->orderByRaw('(likes_count * 1.0 / NULLIF(likes_count + dislikes_count, 0)) DESC')
                ->orderByDesc('likes_count'),
            default => $query->orderByDesc('published_at'),
        };
    }

    public function isAccessibleBy(?User $user): bool
    {
        if ($this->privacy === 'public') {
            return true;
        }

        if (! $user) {
            return false;
        }

        if ($this->user_id === $user->id) {
            return true;
        }

        if ($this->privacy === 'private') {
            return false;
        }

        return true;
    }

    public function isPaid(): bool
    {
        return $this->price > 0 || $this->rent_price > 0;
    }

    public function incrementViews(): void
    {
        $this->increment('views_count');
    }

    public function getFormattedDurationAttribute(): string
    {
        $seconds = $this->duration;
        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        $secs = $seconds % 60;

        if ($hours > 0) {
            return sprintf('%d:%02d:%02d', $hours, $minutes, $secs);
        }

        return sprintf('%d:%02d', $minutes, $secs);
    }

    public function getThumbnailUrlAttribute(): ?string
    {
        // Prefer local thumbnail over external URL — migrated videos may have
        // stale Bunny CDN URLs in external_thumbnail_url even though the
        // thumbnail was downloaded locally during migration.
        if ($this->thumbnail) {
            return StorageManager::url($this->thumbnail, $this->storage_disk ?? 'public');
        }

        if ($this->external_thumbnail_url) {
            return $this->external_thumbnail_url;
        }

        return null;
    }

    /**
     * Alt text for this video's thumbnail.
     *
     * Prefers the persisted column and only generates when it is null, so rows
     * that predate seo:backfill-alt-text still render a real alt attribute.
     * Generation is query-free (AltTextService reads loaded relations only),
     * which keeps this safe to append on a paginated list.
     */
    public function getThumbnailAltAttribute(): string
    {
        return $this->attributes['thumbnail_alt_text'] ?? null
            ?: app(AltTextService::class)->forVideo($this);
    }

    public function getPreviewUrlAttribute(): ?string
    {
        if ($this->preview_path) {
            return StorageManager::url($this->preview_path, $this->storage_disk ?? 'public');
        }

        if ($this->external_preview_url) {
            return $this->external_preview_url;
        }

        return null;
    }

    public function getPreviewThumbnailsUrlAttribute(): ?string
    {
        if (! $this->scrubber_vtt_path) {
            return null;
        }

        return StorageManager::url($this->scrubber_vtt_path, $this->storage_disk ?? 'public');
    }

    public function getHlsPlaylistUrlAttribute(): ?string
    {
        if (! $this->video_path) {
            return null;
        }

        $disk = $this->storage_disk ?? 'public';
        if ($disk !== 'public' && ! Setting::get('cloud_storage_public_bucket', false)) {
            return null;
        }
        $baseDir = dirname($this->video_path);
        $masterPath = $baseDir.'/processed/master.m3u8';

        if (! StorageManager::exists($masterPath, $disk)) {
            return null;
        }

        return StorageManager::url($masterPath, $disk);
    }

    public function getQualityUrlsAttribute(): array
    {
        if (! $this->video_path || ! $this->qualities_available) {
            return [];
        }

        $disk = $this->storage_disk ?? 'public';
        $baseDir = dirname($this->video_path);
        $urls = [];

        foreach ($this->qualities_available as $quality) {
            if ($quality === 'original') {
                $urls['original'] = StorageManager::url($this->video_path, $disk);
            } else {
                $path = $baseDir.'/processed/'.$quality.'.mp4';
                if (StorageManager::exists($path, $disk)) {
                    $urls[$quality] = StorageManager::url($path, $disk);
                }
            }
        }

        return $urls;
    }

    public function getVideoUrlAttribute(): ?string
    {
        if ($this->is_embedded && $this->embed_url) {
            return $this->embed_url;
        }

        if (! $this->video_path) {
            return null;
        }

        return StorageManager::url($this->video_path, $this->storage_disk ?? 'public');
    }

    /**
     * Find all videos that reference the given file path in any stored path column.
     */
    public static function findByFilePath(string $path): array
    {
        $results = [];
        $fields = ['video_path', 'thumbnail', 'preview_path', 'trailer_path', 'scrubber_vtt_path'];

        $videos = static::query()
            ->where(function ($query) use ($fields, $path) {
                foreach ($fields as $field) {
                    $query->orWhere($field, $path);
                }
            })
            ->get();

        foreach ($videos as $video) {
            foreach ($fields as $field) {
                if ($video->{$field} === $path) {
                    $results[] = [
                        'model' => 'Video',
                        'id' => $video->id,
                        'field' => $field,
                        'record' => $video,
                    ];
                }
            }
        }

        return $results;
    }

    /**
     * Update stored paths that match $oldPath to $newPath.
     */
    public static function updateFilePath(string $oldPath, string $newPath): int
    {
        $count = 0;

        foreach (static::findByFilePath($oldPath) as $match) {
            $record = $match['record'];
            $field = $match['field'];
            $record->{$field} = $newPath;
            $record->saveQuietly();
            $count++;
        }

        return $count;
    }

    /**
     * Get all available thumbnail URLs for this video (generated during processing).
     */
    public function getAvailableThumbnails(): array
    {
        if (! $this->slug) {
            return [];
        }

        $disk = $this->storage_disk ?? 'public';
        $videoDir = "videos/{$this->slug}";
        $slugTitle = Str::slug($this->title, '_') ?: 'video';
        $thumbnails = [];

        // Check for numbered thumbnails (_thumb_0, _thumb_1, etc.)
        for ($i = 0; $i < 10; $i++) {
            $path = "{$videoDir}/{$slugTitle}_thumb_{$i}.jpg";
            if (StorageManager::exists($path, $disk)) {
                $thumbnails[] = [
                    'path' => $path,
                    'url' => StorageManager::url($path, $disk),
                    'is_active' => $this->thumbnail === $path,
                ];
            } else {
                break;
            }
        }

        // Include custom thumbnail if it exists and isn't already in the list
        if ($this->thumbnail && ! collect($thumbnails)->pluck('path')->contains($this->thumbnail)) {
            if (StorageManager::exists($this->thumbnail, $disk)) {
                array_unshift($thumbnails, [
                    'path' => $this->thumbnail,
                    'url' => StorageManager::url($this->thumbnail, $disk),
                    'is_active' => true,
                ]);
            }
        }

        return $thumbnails;
    }

    public function hasPurchasedBy(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        return $this->transactions()
            ->where('buyer_id', $user->id)
            ->where(function ($query) {
                $query->where('type', 'purchase')
                    ->orWhere(function ($q) {
                        $q->where('type', 'rental')
                            ->where('expires_at', '>', now());
                    });
            })
            ->exists();
    }
}

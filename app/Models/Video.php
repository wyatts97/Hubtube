<?php

namespace App\Models;

use App\Services\StorageManager;
use App\Traits\Translatable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Scout\Searchable;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

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
        'preview_path',
        'video_path',
        'storage_disk',
        'trailer_path',
        'duration',
        'size',
        'privacy',
        'status',
        'failure_reason',
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
        'preview_url',
        'preview_thumbnails_url',
        'hls_playlist_url',
        'formatted_duration',
        'quality_urls',
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
        ];
    }

    protected static function booted(): void
    {
        // Flush homepage caches when a video is created, updated, or deleted
        // so stale/phantom entries don't persist in cached sections.
        $flushHomeCache = function () {
            \Illuminate\Support\Facades\Cache::forget('home:featured');
            \Illuminate\Support\Facades\Cache::forget('home:popular');
        };

        static::created($flushHomeCache);
        static::updated($flushHomeCache);
        static::deleted($flushHomeCache);

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
            if ($video->thumbnail && !str_starts_with($video->thumbnail, 'videos/')) {
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

    public function tweets(): HasMany
    {
        return $this->hasMany(VideoTweet::class);
    }

    public function scopePublic($query)
    {
        return $query->where('privacy', 'public');
    }

    public function scopeApproved($query)
    {
        return $query->where('is_approved', true);
    }

    public function scopeProcessed($query)
    {
        return $query->where('status', 'processed');
    }

    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true);
    }

    public function isAccessibleBy(?User $user): bool
    {
        if ($this->privacy === 'public') {
            return true;
        }

        if (!$user) {
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
        if (!$this->scrubber_vtt_path) {
            return null;
        }

        return StorageManager::url($this->scrubber_vtt_path, $this->storage_disk ?? 'public');
    }

    public function getHlsPlaylistUrlAttribute(): ?string
    {
        if (!$this->video_path) {
            return null;
        }

        $disk = $this->storage_disk ?? 'public';
        if ($disk !== 'public' && !Setting::get('cloud_storage_public_bucket', false)) {
            return null;
        }
        $baseDir = dirname($this->video_path);
        $masterPath = $baseDir . '/processed/master.m3u8';

        if (!StorageManager::exists($masterPath, $disk)) {
            return null;
        }

        return StorageManager::url($masterPath, $disk);
    }

    public function getQualityUrlsAttribute(): array
    {
        if (!$this->video_path || !$this->qualities_available) {
            return [];
        }

        $disk = $this->storage_disk ?? 'public';
        $baseDir = dirname($this->video_path);
        $urls = [];

        foreach ($this->qualities_available as $quality) {
            if ($quality === 'original') {
                $urls['original'] = StorageManager::url($this->video_path, $disk);
            } else {
                $path = $baseDir . '/processed/' . $quality . '.mp4';
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

        if (!$this->video_path) {
            return null;
        }

        return StorageManager::url($this->video_path, $this->storage_disk ?? 'public');
    }

    /**
     * Get all available thumbnail URLs for this video (generated during processing).
     */
    public function getAvailableThumbnails(): array
    {
        if (!$this->slug) {
            return [];
        }

        $disk = $this->storage_disk ?? 'public';
        $videoDir = "videos/{$this->slug}";
        $slugTitle = \Illuminate\Support\Str::slug($this->title, '_') ?: 'video';
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
        if ($this->thumbnail && !collect($thumbnails)->pluck('path')->contains($this->thumbnail)) {
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
        if (!$user) {
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

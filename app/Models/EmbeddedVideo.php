<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class EmbeddedVideo extends Model
{
    use HasFactory;

    protected $fillable = [
        'source_site',
        'source_video_id',
        'title',
        'description',
        'duration',
        'duration_formatted',
        'thumbnail_url',
        'thumbnail_preview_url',
        'source_url',
        'embed_url',
        'embed_code',
        'views_count',
        'rating',
        'tags',
        'actors',
        'category_id',
        'is_published',
        'is_featured',
        'source_upload_date',
        'imported_at',
    ];

    protected $casts = [
        'tags' => 'array',
        'actors' => 'array',
        'is_published' => 'boolean',
        'is_featured' => 'boolean',
        'views_count' => 'integer',
        'rating' => 'integer',
        'duration' => 'integer',
        'source_upload_date' => 'datetime',
        'imported_at' => 'datetime',
    ];

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true);
    }

    public function scopeFeatured(Builder $query): Builder
    {
        return $query->where('is_featured', true);
    }

    public function scopeFromSite(Builder $query, string $site): Builder
    {
        return $query->where('source_site', $site);
    }

    public static function isAlreadyImported(string $sourceSite, string $sourceVideoId): bool
    {
        return static::where('source_site', $sourceSite)
            ->where('source_video_id', $sourceVideoId)
            ->exists();
    }

    public static function getImportedIds(string $sourceSite, array $sourceVideoIds): array
    {
        return static::where('source_site', $sourceSite)
            ->whereIn('source_video_id', $sourceVideoIds)
            ->pluck('source_video_id')
            ->toArray();
    }

    public function getFormattedViewsAttribute(): string
    {
        $views = $this->views_count;
        if ($views >= 1000000) {
            return round($views / 1000000, 1) . 'M';
        } elseif ($views >= 1000) {
            return round($views / 1000, 1) . 'K';
        }
        return (string) $views;
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * Get a proxied thumbnail URL to avoid hotlink blocking from external sites.
     */
    public function getProxiedThumbnailUrlAttribute(): ?string
    {
        if (!$this->thumbnail_url) {
            return null;
        }

        return '/api/thumb-proxy?url=' . urlencode($this->thumbnail_url);
    }

    public function toVideoFormat(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => 'embedded-' . $this->id,
            'description' => $this->description,
            'thumbnail' => $this->proxied_thumbnail_url,
            'thumbnail_url' => $this->proxied_thumbnail_url,
            'original_thumbnail_url' => $this->thumbnail_url,
            'duration' => $this->duration,
            'duration_formatted' => $this->duration_formatted,
            'views_count' => $this->views_count,
            'formatted_views' => $this->formatted_views,
            'likes_count' => 0,
            'dislikes_count' => 0,
            'published_at' => $this->imported_at,
            'created_at' => $this->created_at,
            'is_embedded' => true,
            'embed_url' => $this->embed_url,
            'embed_code' => $this->embed_code,
            'source_site' => $this->source_site,
            'source_url' => $this->source_url,
            'user' => [
                'id' => 0,
                'name' => ucfirst($this->source_site),
                'username' => $this->source_site,
                'avatar' => null,
            ],
        ];
    }
}

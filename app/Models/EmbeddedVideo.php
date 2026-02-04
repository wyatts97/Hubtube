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
}

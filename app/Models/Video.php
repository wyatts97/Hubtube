<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Scout\Searchable;

class Video extends Model
{
    use HasFactory, Searchable, SoftDeletes;

    protected $fillable = [
        'user_id',
        'uuid',
        'title',
        'slug',
        'description',
        'thumbnail',
        'video_path',
        'trailer_path',
        'duration',
        'size',
        'privacy',
        'status',
        'is_short',
        'is_featured',
        'is_approved',
        'age_restricted',
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
        'processing_started_at',
        'processing_completed_at',
    ];

    protected function casts(): array
    {
        return [
            'is_short' => 'boolean',
            'is_featured' => 'boolean',
            'is_approved' => 'boolean',
            'age_restricted' => 'boolean',
            'monetization_enabled' => 'boolean',
            'price' => 'decimal:2',
            'rent_price' => 'decimal:2',
            'qualities_available' => 'array',
            'geo_blocked_countries' => 'array',
            'tags' => 'array',
            'published_at' => 'datetime',
            'processing_started_at' => 'datetime',
            'processing_completed_at' => 'datetime',
        ];
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
        return $query->where('is_approved', true);
    }

    public function scopeProcessed($query)
    {
        return $query->where('status', 'processed');
    }

    public function scopeShorts($query)
    {
        return $query->where('is_short', true);
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

    public function getThumbnailUrlAttribute(): string
    {
        if (config('hubtube.storage.cdn_enabled')) {
            return config('hubtube.storage.cdn_url') . '/' . $this->thumbnail;
        }

        return asset('storage/' . $this->thumbnail);
    }
}

<?php

namespace App\Models;

use App\Services\AltTextService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Gallery extends Model
{
    use HasFactory, LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['title', 'privacy'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('admin');
    }

    protected $fillable = [
        'user_id',
        'title',
        'slug',
        'description',
        'cover_alt_text',
        'cover_image_id',
        'privacy',
        'images_count',
        'views_count',
        'sort_order',
    ];

    protected $appends = [
        'cover_url',
        'cover_alt',
    ];

    protected function casts(): array
    {
        return [
            'images_count' => 'integer',
            'views_count' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function coverImage(): BelongsTo
    {
        return $this->belongsTo(Image::class, 'cover_image_id');
    }

    public function images(): BelongsToMany
    {
        return $this->belongsToMany(Image::class, 'gallery_image')
            ->withPivot('sort_order')
            ->withTimestamps()
            ->orderBy('gallery_image.sort_order');
    }

    public function scopePublic($query)
    {
        return $query->where('privacy', 'public');
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

    public function incrementViews(): void
    {
        $this->increment('views_count');
    }

    /**
     * Alt text for this gallery's cover image.
     *
     * Prefers the persisted column and only generates when it is null, so rows
     * that predate seo:backfill-alt-text still render a real alt attribute.
     * Generation is query-free (AltTextService reads loaded relations only),
     * which keeps this safe to append on a paginated list.
     */
    public function getCoverAltAttribute(): string
    {
        return $this->attributes['cover_alt_text'] ?? null
            ?: app(AltTextService::class)->forGallery($this);
    }

    public function getCoverUrlAttribute(): ?string
    {
        if ($this->relationLoaded('coverImage') && $this->coverImage) {
            return $this->coverImage->thumbnail_url;
        }

        // Fallback: get first image
        $firstImage = $this->images()->first();
        return $firstImage?->thumbnail_url;
    }
}

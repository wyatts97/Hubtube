<?php

namespace App\Models;

use App\Services\AltTextService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Channel extends Model
{
    use HasFactory;

    /**
     * Note: there is no is_verified here. Verification lives on
     * users.is_verified — the column on this table was dropped by the
     * consolidate_channel_profile_fields migration because nothing read it
     * (the frontend badge has always read the user flag).
     */
    protected $fillable = [
        'user_id',
        'name',
        'slug',
        'description',
        'banner_image',
        'banner_alt_text',
        'custom_url',
        'subscriber_count',
        'total_views',
        'subscription_price',
        'subscription_enabled',
        'social_links',
        'featured_video_id',
    ];

    protected function casts(): array
    {
        return [
            'subscription_enabled' => 'boolean',
            'subscription_price' => 'decimal:2',
            'social_links' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function featuredVideo(): BelongsTo
    {
        return $this->belongsTo(Video::class, 'featured_video_id');
    }

    /**
     * Alt text for this channel's banner.
     *
     * Prefers the persisted column and only generates when it is null, so rows
     * that predate seo:backfill-alt-text still render a real alt attribute.
     * Generation is query-free (AltTextService reads loaded relations only),
     * which keeps this safe to append on a paginated list.
     */
    public function getBannerAltAttribute(): string
    {
        return $this->attributes['banner_alt_text'] ?? null
            ?: app(AltTextService::class)->forChannelBanner($this);
    }

    public function incrementSubscribers(): void
    {
        $this->increment('subscriber_count');
    }

    public function decrementSubscribers(): void
    {
        $this->decrement('subscriber_count');
    }

    public function incrementViews(int $count = 1): void
    {
        $this->increment('total_views', $count);
    }
}

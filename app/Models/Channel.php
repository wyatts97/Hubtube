<?php

namespace App\Models;

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

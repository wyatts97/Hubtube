<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LiveStream extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'title',
        'description',
        'thumbnail',
        'channel_name',
        'agora_token',
        'agora_resource_id',
        'agora_sid',
        'status',
        'viewer_count',
        'peak_viewers',
        'total_gifts_amount',
        'started_at',
        'ended_at',
        'recorded_video_id',
        'chat_enabled',
        'gifts_enabled',
    ];

    protected function casts(): array
    {
        return [
            'viewer_count' => 'integer',
            'peak_viewers' => 'integer',
            'total_gifts_amount' => 'decimal:2',
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
            'chat_enabled' => 'boolean',
            'gifts_enabled' => 'boolean',
        ];
    }

    const STATUS_PENDING = 'pending';
    const STATUS_LIVE = 'live';
    const STATUS_ENDED = 'ended';

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function giftTransactions(): HasMany
    {
        return $this->hasMany(GiftTransaction::class);
    }

    public function recordedVideo(): BelongsTo
    {
        return $this->belongsTo(Video::class, 'recorded_video_id');
    }

    public function scopeLive($query)
    {
        return $query->where('status', self::STATUS_LIVE);
    }

    public function scopeEnded($query)
    {
        return $query->where('status', self::STATUS_ENDED);
    }

    public function isLive(): bool
    {
        return $this->status === self::STATUS_LIVE;
    }

    public function start(): void
    {
        $this->update([
            'status' => self::STATUS_LIVE,
            'started_at' => now(),
        ]);
    }

    public function end(): void
    {
        $this->update([
            'status' => self::STATUS_ENDED,
            'ended_at' => now(),
        ]);
    }

    public function updateViewerCount(int $count): void
    {
        $this->viewer_count = $count;
        
        if ($count > $this->peak_viewers) {
            $this->peak_viewers = $count;
        }
        
        $this->save();
    }

    public function addGiftAmount(float $amount): void
    {
        $this->increment('total_gifts_amount', $amount);
    }
}

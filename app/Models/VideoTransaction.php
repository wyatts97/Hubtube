<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VideoTransaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'video_id',
        'buyer_id',
        'seller_id',
        'type',
        'amount',
        'platform_cut',
        'seller_amount',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'platform_cut' => 'decimal:2',
            'seller_amount' => 'decimal:2',
            'expires_at' => 'datetime',
        ];
    }

    public function video(): BelongsTo
    {
        return $this->belongsTo(Video::class);
    }

    public function buyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    public function scopePurchases($query)
    {
        return $query->where('type', 'purchase');
    }

    public function scopeRentals($query)
    {
        return $query->where('type', 'rental');
    }

    public function isActive(): bool
    {
        if ($this->type === 'purchase') {
            return true;
        }

        return $this->expires_at && $this->expires_at->isFuture();
    }
}

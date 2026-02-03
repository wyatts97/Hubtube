<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Subscription extends Model
{
    use HasFactory;

    protected $fillable = [
        'subscriber_id',
        'channel_id',
        'is_paid',
        'amount',
        'expires_at',
        'notifications_enabled',
    ];

    protected function casts(): array
    {
        return [
            'is_paid' => 'boolean',
            'notifications_enabled' => 'boolean',
            'amount' => 'decimal:2',
            'expires_at' => 'datetime',
        ];
    }

    public function subscriber(): BelongsTo
    {
        return $this->belongsTo(User::class, 'subscriber_id');
    }

    public function channel(): BelongsTo
    {
        return $this->belongsTo(User::class, 'channel_id');
    }

    public function scopeActive($query)
    {
        return $query->where(function ($q) {
            $q->where('is_paid', false)
              ->orWhere('expires_at', '>', now());
        });
    }

    public function scopePaid($query)
    {
        return $query->where('is_paid', true);
    }

    public function isActive(): bool
    {
        if (!$this->is_paid) {
            return true;
        }

        return $this->expires_at && $this->expires_at->isFuture();
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WalletTransaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'type',
        'amount',
        'balance_after',
        'description',
        'reference_type',
        'reference_id',
        'status',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'balance_after' => 'decimal:2',
            'metadata' => 'array',
        ];
    }

    const TYPE_DEPOSIT = 'deposit';
    const TYPE_WITHDRAWAL = 'withdrawal';
    const TYPE_GIFT_SENT = 'gift_sent';
    const TYPE_GIFT_RECEIVED = 'gift_received';
    const TYPE_VIDEO_PURCHASE = 'video_purchase';
    const TYPE_VIDEO_SALE = 'video_sale';
    const TYPE_SUBSCRIPTION = 'subscription';
    const TYPE_SUBSCRIPTION_EARNING = 'subscription_earning';
    const TYPE_AD_REVENUE = 'ad_revenue';
    const TYPE_REFUND = 'refund';

    const STATUS_PENDING = 'pending';
    const STATUS_COMPLETED = 'completed';
    const STATUS_FAILED = 'failed';
    const STATUS_CANCELLED = 'cancelled';

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', self::STATUS_COMPLETED);
    }

    public function scopeCredits($query)
    {
        return $query->whereIn('type', [
            self::TYPE_DEPOSIT,
            self::TYPE_GIFT_RECEIVED,
            self::TYPE_VIDEO_SALE,
            self::TYPE_SUBSCRIPTION_EARNING,
            self::TYPE_AD_REVENUE,
            self::TYPE_REFUND,
        ]);
    }

    public function scopeDebits($query)
    {
        return $query->whereIn('type', [
            self::TYPE_WITHDRAWAL,
            self::TYPE_GIFT_SENT,
            self::TYPE_VIDEO_PURCHASE,
            self::TYPE_SUBSCRIPTION,
        ]);
    }

    public function isCredit(): bool
    {
        return in_array($this->type, [
            self::TYPE_DEPOSIT,
            self::TYPE_GIFT_RECEIVED,
            self::TYPE_VIDEO_SALE,
            self::TYPE_SUBSCRIPTION_EARNING,
            self::TYPE_AD_REVENUE,
            self::TYPE_REFUND,
        ]);
    }
}

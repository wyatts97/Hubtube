<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CCBillSubscription extends Model
{
    protected $table = 'ccbill_subscriptions';

    public const STATUS_ACTIVE = 'active';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_PAST_DUE = 'past_due';
    public const STATUS_REFUNDED = 'refunded';
    public const STATUS_CHARGEBACK = 'chargeback';

    protected $fillable = [
        'user_id',
        'plan_id',
        'ccbill_subscription_id',
        'status',
        'subscription_type',
        'current_period_end',
        'cancelled_at',
        'expired_at',
    ];

    protected function casts(): array
    {
        return [
            'current_period_end' => 'datetime',
            'cancelled_at' => 'datetime',
            'expired_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    /**
     * A subscription grants Pro access while active, cancelled-but-not-yet-expired,
     * or temporarily past-due (grace period until CCBill sends Expiration).
     */
    public function isActive(): bool
    {
        if (in_array($this->status, [self::STATUS_EXPIRED, self::STATUS_REFUNDED, self::STATUS_CHARGEBACK], true)) {
            return false;
        }

        if ($this->current_period_end && $this->current_period_end->isPast()) {
            return false;
        }

        return true;
    }
}

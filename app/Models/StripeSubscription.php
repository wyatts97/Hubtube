<?php

namespace App\Models;

use Database\Factories\StripeSubscriptionFactory;
use Laravel\Cashier\Subscription as CashierSubscription;

class StripeSubscription extends CashierSubscription
{
    protected $table = 'stripe_subscriptions';

    protected $casts = [
        'ends_at' => 'datetime',
        'trial_ends_at' => 'datetime',
        'current_period_start' => 'datetime',
        'current_period_end' => 'datetime',
        'last_used_at' => 'datetime',
        'quantity' => 'integer',
    ];

    protected static function newFactory()
    {
        return StripeSubscriptionFactory::new();
    }

    public function items()
    {
        return $this->hasMany(StripeSubscriptionItem::class, 'subscription_id');
    }
}

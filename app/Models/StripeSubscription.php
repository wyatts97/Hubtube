<?php

namespace App\Models;

use Laravel\Cashier\Subscription as CashierSubscription;

class StripeSubscription extends CashierSubscription
{
    protected $table = 'stripe_subscriptions';

    protected static function newFactory()
    {
        return \Database\Factories\StripeSubscriptionFactory::new();
    }

    public function items()
    {
        return $this->hasMany(StripeSubscriptionItem::class, 'subscription_id');
    }
}

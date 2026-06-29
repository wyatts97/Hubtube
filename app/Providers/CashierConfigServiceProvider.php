<?php

namespace App\Providers;

use App\Models\Setting;
use App\Models\StripeSubscription;
use App\Models\StripeSubscriptionItem;
use Illuminate\Support\ServiceProvider;
use Laravel\Cashier\Cashier;

class CashierConfigServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../../config/cashier.php',
            'cashier'
        );
    }

    public function boot(): void
    {
        // The app already uses a `subscriptions` table for channel subscriptions,
        // so Cashier must use the dedicated stripe_subscriptions tables/models.
        Cashier::useSubscriptionModel(StripeSubscription::class);
        Cashier::useSubscriptionItemModel(StripeSubscriptionItem::class);

        try {
            $key = Setting::get('stripe_key', '');
            $secret = Setting::get('stripe_secret', '');
            $webhookSecret = Setting::get('stripe_webhook_secret', '');

            if ($key) {
                config(['cashier.key' => $key]);
                config(['services.stripe.key' => $key]);
            }

            if ($secret) {
                config(['cashier.secret' => $secret]);
                config(['services.stripe.secret' => $secret]);
            }

            if ($webhookSecret) {
                config(['cashier.webhook.secret' => $webhookSecret]);
            }
        } catch (\Throwable $e) {
            // Settings table may not exist during initial install; ignore.
        }
    }
}

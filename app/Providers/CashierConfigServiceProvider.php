<?php

namespace App\Providers;

use App\Models\Setting;
use Illuminate\Support\ServiceProvider;

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
                config(['cashier.webhook_secret' => $webhookSecret]);
            }
        } catch (\Throwable $e) {
            // Settings table may not exist during initial install; ignore.
        }
    }
}

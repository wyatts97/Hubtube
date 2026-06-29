<?php

use App\Models\StripeSubscription;
use App\Models\StripeSubscriptionItem;
use App\Models\User;

return [
    'key' => null,
    'secret' => null,
    'webhook' => [
        'secret' => null,
        'tolerance' => env('STRIPE_WEBHOOK_TOLERANCE', 300),
        'events' => null,
    ],
    'currency' => env('CASHIER_CURRENCY', 'usd'),
    'currency_locale' => env('CASHIER_CURRENCY_LOCALE', 'en'),
    'stripe_model' => User::class,
    'subscription_model' => StripeSubscription::class,
    'subscription_item_model' => StripeSubscriptionItem::class,
    'payment_model' => env('CASHIER_PAYMENT_MODEL', Laravel\Cashier\Payment::class),
    'customer_model' => env('CASHIER_CUSTOMER_MODEL', Laravel\Cashier\Customer::class),
    'product_model' => env('CASHIER_PRODUCT_MODEL', Laravel\Cashier\Product::class),
    'logger' => env('CASHIER_LOGGER'),
    'path' => env('CASHIER_PATH', 'stripe'),
    'payment_notification' => env('CASHIER_PAYMENT_NOTIFICATION', Laravel\Cashier\Notifications\ConfirmPayment::class),
    'stripe_version' => env('CASHIER_STRIPE_VERSION', '2024-04-10'),
    'api_base' => env('CASHIER_API_BASE'),
    'currency_model' => env('CASHIER_CURRENCY_MODEL', Laravel\Cashier\Currency::class),
    'receipt_url' => env('CASHIER_RECEIPT_URL'),
];

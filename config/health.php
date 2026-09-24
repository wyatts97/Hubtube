<?php

return [

    'checks' => [],

    'result_stores' => [
        \Spatie\Health\ResultStores\EloquentHealthResultStore::class => [
            'model' => \Spatie\Health\Models\HealthCheckResultHistoryItem::class,
            'keep_history_for_days' => 14,
        ],

        \Spatie\Health\ResultStores\CacheHealthResultStore::class => [
            'store' => 'file',
        ],
    ],

    'notifications' => [
        'enabled' => true,
        'notifications' => [
            \Spatie\Health\Notifications\CheckFailedNotification::class => ['mail'],
        ],
        'notifiable' => \Spatie\Health\Notifications\Notifiable::class,
        'throttle_notifications_for_minutes' => 60,
        'only_on_failure' => true,
        'mail' => [
            // Overridden by the admin notification email when one is set in
            // the panel (DynamicConfigServiceProvider).
            'to' => env('HEALTH_MAIL_TO', env('MAIL_FROM_ADDRESS')),
            'from' => [
                'address' => env('MAIL_FROM_ADDRESS'),
                'name' => env('MAIL_FROM_NAME', 'HubTube Health'),
            ],
        ],
    ],

    'silenced_checks' => [],

];

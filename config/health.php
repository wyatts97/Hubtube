<?php

use Spatie\Health\Models\HealthCheckResultHistoryItem;
use Spatie\Health\Notifications\CheckFailedNotification;
use Spatie\Health\Notifications\Notifiable;
use Spatie\Health\ResultStores\CacheHealthResultStore;
use Spatie\Health\ResultStores\EloquentHealthResultStore;

return [

    'checks' => [],

    'result_stores' => [
        EloquentHealthResultStore::class => [
            'model' => HealthCheckResultHistoryItem::class,
            'keep_history_for_days' => 14,
        ],

        CacheHealthResultStore::class => [
            'store' => 'file',
        ],
    ],

    'notifications' => [
        'enabled' => true,
        'notifications' => [
            CheckFailedNotification::class => ['mail'],
        ],
        'notifiable' => Notifiable::class,
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

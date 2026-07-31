<?php

use Spatie\Health\Checks\Checks\CacheCheck;
use Spatie\Health\Checks\Checks\DatabaseCheck;
use Spatie\Health\Checks\Checks\DebugModeCheck;
use Spatie\Health\Checks\Checks\EnvironmentCheck;
use Spatie\Health\Checks\Checks\HorizonCheck;
use Spatie\Health\Checks\Checks\RedisCheck;
use Spatie\Health\Checks\Checks\ScheduleCheck;
use Spatie\Health\Checks\Checks\UsedDiskSpaceCheck;

return [

    'checks' => [
        DatabaseCheck::new(),
        RedisCheck::new(),
        CacheCheck::new(),
        DebugModeCheck::new()->unless(config('app.debug') === false),
        EnvironmentCheck::new()->expectEnvironment(config('app.env')),
        HorizonCheck::new(),
        ScheduleCheck::new(),
        UsedDiskSpaceCheck::new()
            ->warnWhenUsedSpaceIsAbovePercentage(80)
            ->failWhenUsedSpaceIsAbovePercentage(95),
    ],

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
        'only_on_failure' => false,
        'mail' => [
            'to' => env('HEALTH_MAIL_TO', env('MAIL_FROM_ADDRESS', 'admin@wedgietube.com')),
            'from' => [
                'address' => env('MAIL_FROM_ADDRESS', 'noreply@wedgietube.com'),
                'name' => env('MAIL_FROM_NAME', 'HubTube Health'),
            ],
        ],
    ],

    'silenced_checks' => [],

];

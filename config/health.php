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
            'connection' => env('HEALTH_DB_CONNECTION', null),
            'keep_results_for_days' => 14,
        ],
        \Spatie\Health\ResultStores\CacheHealthResultStore::class => [
            'store' => 'redis',
        ],
    ],

    'notifications' => [
        'enabled' => true,
        'notifications' => [
            \Spatie\Health\Notifications\CheckFailedNotification::class => ['mail'],
        ],
        'notifiable' => \Spatie\Health\Notifications\Notifiable::class,
        'channel' => 'mail',
        'queue' => null,
    ],

    'silenced_checks' => [],

];

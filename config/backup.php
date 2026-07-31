<?php

return [

    'backup_name' => env('APP_NAME', 'HubTube'),

    'source' => [
        'files' => [
            'include' => [
                base_path(),
            ],
            'exclude' => [
                base_path('vendor'),
                base_path('node_modules'),
                base_path('.git'),
                storage_path('app/videos'),
                storage_path('app/public/videos'),
                storage_path('app/backups'),
                storage_path('logs'),
                storage_path('framework'),
                storage_path('app/laravel-medialibrary'),
            ],
            'follow_links' => false,
            'ignore_unreadable_directories' => false,
            'relative_path' => base_path(),
        ],

        'databases' => [
            'mysql',
        ],
    ],

    'destination' => [
        'compression_method' => 'zip',
        'compression_extension' => 'zip',

        'disks' => [
            'local',
        ],
    ],

    'directory_name' => 'backups',

    'temporary_directory' => storage_path('app/laravel-backup-temp'),

    'password' => env('BACKUP_ARCHIVE_PASSWORD'),

    'notifications' => [
        'notifications' => [
            \Spatie\Backup\Notifications\Notifications\BackupHasFailedNotification::class => ['mail'],
            \Spatie\Backup\Notifications\Notifications\UnhealthyBackupWasFoundNotification::class => ['mail'],
            \Spatie\Backup\Notifications\Notifications\CleanupHasFailedNotification::class => ['mail'],
            \Spatie\Backup\Notifications\Notifications\HealthyBackupWasFoundNotification::class => ['mail'],
            \Spatie\Backup\Notifications\Notifications\BackupWasSuccessfulNotification::class => ['mail'],
            \Spatie\Backup\Notifications\Notifications\CleanupWasSuccessfulNotification::class => ['mail'],
            \Spatie\Backup\Notifications\Notifications\HealthyBackupWasFoundNotification::class => ['mail'],
        ],

        'notifiable' => \Spatie\Backup\Notifications\Notifiable::class,
    ],

    'cleanup' => [
        'strategy' => \Spatie\Backup\Tasks\Cleanup\Strategies\DefaultStrategy::class,

        'default_strategy' => [
            'keep_all_backups_for_days' => 7,
            'keep_daily_backups_for_days' => 16,
            'keep_weekly_backups_for_weeks' => 8,
            'keep_monthly_backups_for_months' => 4,
            'keep_yearly_backups_for_years' => 2,
            'delete_oldest_backups_when_using_more_megabytes_than' => 5000,
        ],
    ],

    'monitor' => [
        'health-check' => [
            'name' => env('APP_NAME', 'HubTube'),
            'disks' => ['local'],
            'maximum_storage_in_megabytes' => 5000,
        ],
    ],
];

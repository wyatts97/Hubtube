<?php

return [

    'backup_name' => env('APP_NAME', 'HubTube'),

    'source' => [
        'files' => [
            'include' => [
                base_path(),
            ],
            // Exclude the media roots WHOLESALE rather than naming individual
            // folders. The transcoder creates directories per video (hls/, sprite
            // and preview output), so an allowlist of known subfolders silently
            // leaks the entire library into the archive.
            //
            // Application code is in git, so this backup exists to capture the
            // database plus .env and any local config drift.
            'exclude' => [
                base_path('vendor'),
                base_path('node_modules'),
                base_path('.git'),
                base_path('tests'),
                base_path('bootstrap/cache'),
                base_path('public/build'),

                // Symlink to storage/app/public — the whole media library.
                base_path('public/storage'),

                // All user-uploaded and generated media.
                storage_path('app/public'),
                storage_path('app/videos'),
                storage_path('app/uploads'),
                storage_path('app/chunks'),
                storage_path('app/temp'),
                storage_path('app/tmp'),
                storage_path('app/laravel-medialibrary'),

                // Backups themselves, scratch space, caches and logs.
                storage_path('app/backups'),
                storage_path('app/laravel-backup-temp'),
                storage_path('framework'),
                storage_path('logs'),
                storage_path('debugbar'),

                base_path('.idea'),
                base_path('.vscode'),
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
            'keep_all_backups_for_days' => 3,
            'keep_daily_backups_for_days' => 7,
            'keep_weekly_backups_for_weeks' => 4,
            'keep_monthly_backups_for_months' => 2,
            'keep_yearly_backups_for_years' => 1,
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

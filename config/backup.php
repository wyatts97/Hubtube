<?php

/*
| spatie/laravel-backup reads config('backup.backup'), 'notifications',
| 'monitor_backups' and 'cleanup', merged shallowly over its own defaults.
| This file used to be flat (source/destination at the top level), so the
| package ignored the exclude list below and zipped the whole media library,
| plus earlier backups, on every nightly run.
*/

return [

    'backup' => [
        // Also the folder name on each disk: storage/app/backups, which is
        // what the Backups page lists.
        'name' => 'backups',

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

                    // Backups themselves (and the folder older releases wrote
                    // them to), scratch space, caches and logs.
                    storage_path('app/backups'),
                    storage_path('app/'.env('APP_NAME', 'HubTube')),
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
                env('DB_CONNECTION', 'mysql'),
            ],
        ],

        'database_dump_compressor' => null,
        'database_dump_file_timestamp_format' => null,
        'database_dump_filename_base' => 'database',
        'database_dump_file_extension' => '',

        'destination' => [
            'compression_method' => ZipArchive::CM_DEFAULT,
            'compression_level' => 9,
            'filename_prefix' => '',
            // Add an off-site disk so a lost server doesn't take its backups
            // with it, e.g. BACKUP_DISKS=local,wasabi
            'disks' => explode(',', env('BACKUP_DISKS', 'local')),
            'continue_on_failure' => false,
        ],

        'temporary_directory' => storage_path('app/laravel-backup-temp'),

        // Archives include .env — set this.
        'password' => env('BACKUP_ARCHIVE_PASSWORD'),
        'encryption' => 'default',
        'verify_backup' => false,
        'tries' => 1,
        'retry_delay' => 0,
    ],

    'notifications' => [
        'notifications' => [
            // Failures only; a success mail every night trains you to ignore them.
            \Spatie\Backup\Notifications\Notifications\BackupHasFailedNotification::class => ['mail'],
            \Spatie\Backup\Notifications\Notifications\UnhealthyBackupWasFoundNotification::class => ['mail'],
            \Spatie\Backup\Notifications\Notifications\CleanupHasFailedNotification::class => ['mail'],
        ],

        'notifiable' => \Spatie\Backup\Notifications\Notifiable::class,

        // Overridden by the admin notification email when one is set in the
        // panel (DynamicConfigServiceProvider).
        'mail' => [
            'to' => env('BACKUP_MAIL_TO', env('MAIL_FROM_ADDRESS')),
            'from' => [
                'address' => env('MAIL_FROM_ADDRESS'),
                'name' => env('MAIL_FROM_NAME', 'HubTube Backups'),
            ],
        ],

        'slack' => ['webhook_url' => '', 'channel' => null, 'username' => null, 'icon' => null],
        'discord' => ['webhook_url' => '', 'username' => '', 'avatar_url' => ''],
        'webhook' => ['url' => ''],
    ],

    'monitor_backups' => [
        [
            'name' => 'backups',
            'disks' => explode(',', env('BACKUP_DISKS', 'local')),
            'health_checks' => [
                \Spatie\Backup\Tasks\Monitor\HealthChecks\MaximumAgeInDays::class => 2,
                \Spatie\Backup\Tasks\Monitor\HealthChecks\MaximumStorageInMegabytes::class => 5000,
            ],
        ],
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

        'tries' => 1,
        'retry_delay' => 0,
    ],
];

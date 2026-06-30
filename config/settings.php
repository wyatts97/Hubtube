<?php

return [
    'settings' => [
        \FinityLabs\FinMail\Settings\GeneralSettings::class,
        \FinityLabs\FinMail\Settings\AttachmentSettings::class,
        \FinityLabs\FinMail\Settings\BrandingSettings::class,
        \FinityLabs\FinMail\Settings\LoggingSettings::class,
        \FinityLabs\FinMail\Settings\AuthEmailSettings::class,
    ],

    'setting_class_path' => app_path('Settings'),

    'migrations_paths' => [
        database_path('settings'),
    ],

    'default_repository' => 'database',

    'repositories' => [
        'database' => [
            'type' => Spatie\LaravelSettings\SettingsRepositories\DatabaseSettingsRepository::class,
            'model' => null,
            'table' => 'spatie_settings',
            'connection' => null,
        ],
        'redis' => [
            'type' => Spatie\LaravelSettings\SettingsRepositories\RedisSettingsRepository::class,
            'connection' => null,
            'prefix' => null,
        ],
    ],

    'encoder' => null,
    'decoder' => null,

    'cache' => [
        'enabled' => (bool) env('SETTINGS_CACHE_ENABLED', false),
        'store' => null,
        'prefix' => null,
        'ttl' => null,
        'memo' => env('SETTINGS_CACHE_MEMO', false),
    ],

    'global_casts' => [
        DateTimeInterface::class => Spatie\LaravelSettings\SettingsCasts\DateTimeInterfaceCast::class,
        DateTimeZone::class => Spatie\LaravelSettings\SettingsCasts\DateTimeZoneCast::class,
        Spatie\LaravelData\Data::class => Spatie\LaravelSettings\SettingsCasts\DataCast::class,
    ],

    'auto_discover_settings' => [
        app_path('Settings'),
    ],

    'discovered_settings_cache_path' => base_path('bootstrap/cache'),
];

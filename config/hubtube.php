<?php

/*
|--------------------------------------------------------------------------
| HubTube Configuration — Hardcoded Defaults Only
|--------------------------------------------------------------------------
|
| All optional/runtime settings are managed via the admin panel (Setting model).
| This file provides fallback defaults only. No env() calls for optional features.
| Infrastructure settings (DB, Redis, etc.) remain in .env.
|
*/

return [
    /*
    |--------------------------------------------------------------------------
    | Video Settings (non-configurable constants)
    |--------------------------------------------------------------------------
    */
    'video' => [
        'allowed_extensions' => ['mp4', 'mov', 'avi', 'mkv', 'webm', 'wmv', 'flv'],
        'qualities' => ['240p', '360p', '480p', '720p', '1080p', '1440p', '4k'],
        'default_privacy' => 'public',
    ],

    /*
    |--------------------------------------------------------------------------
    | 2257 Compliance (Adult Content)
    |--------------------------------------------------------------------------
    */
    'compliance' => [
        'require_2257_records' => true,
        'require_id_verification' => true,
        'record_retention_years' => 7,
    ],
];

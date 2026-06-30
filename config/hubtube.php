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

    /*
    |--------------------------------------------------------------------------
    | Media Library / File Manager
    |--------------------------------------------------------------------------
    */
    'media_library' => [
        // Top-level directories under storage/app/public that the admin file manager can browse.
        // Subdirectories are browsed automatically. Paths are relative to the public disk root.
        'allowed_paths' => [
            'media',
            'videos',
            'images',
            'avatars',
            'channel-covers',
            'thumbnails',
        ],

        // Number of files shown per page in the file manager grid/list.
        'per_page' => 50,

        // Thumbnail dimensions used by the file manager grid.
        'thumbnail_width' => 300,
        'thumbnail_height' => 200,

        // Cache duration for generated file-manager thumbnails and folder metadata (seconds).
        'cache_ttl' => 300,
    ],
];

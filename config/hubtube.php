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

        // A repeat visit from the same viewer inside this window is not
        // counted as another view.
        'view_dedup_minutes' => 360,

        // Raw rows in video_views older than this are pruned; the running
        // total lives on videos.views_count and is unaffected.
        'view_log_retention_days' => 90,

        // A viewer who reaches this fraction of the duration has finished it.
        'watch_completed_ratio' => 0.9,
    ],

    /*
    |--------------------------------------------------------------------------
    | Protected Media (private videos)
    |--------------------------------------------------------------------------
    |
    | Must match the `internal` location in deployment/nginx/hubtube.conf.
    | Only used when "X-Accel-Redirect" is enabled in Storage settings.
    |
    */
    'media' => [
        'x_accel_prefix' => '/_protected-media',
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

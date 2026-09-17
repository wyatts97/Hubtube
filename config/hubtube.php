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
        // 'thumbnails' is deliberately absent: it holds nothing but this file
        // manager's own generated thumbnail cache, sharded into 256
        // subdirectories, so browsing it made the folder tree walk a directory
        // that grows with every thumbnail ever made and showed the cache to
        // the admin as if it were media.
        // 'channel-covers' used to be listed here and appears nowhere else in
        // the codebase — nothing has ever written to it. Channel banners go to
        // 'banners/{user}', which was missing, so they were never browsable.
        'allowed_paths' => [
            'media',
            'videos',
            'images',
            'avatars',
            'banners',
        ],

        // Directories never browsed or indexed, even inside an allowed root.
        // The file manager's own generated thumbnails live here.
        'excluded_paths' => [
            'thumbnails/.filemanager',
            'temp',
            'livewire-tmp',
        ],

        // File types the manager will accept. Enforced server-side as well as
        // hinted to the file picker: storage/app/public is served directly by
        // nginx, so an executable extension landing there is worth refusing
        // even from an admin.
        'allowed_upload_extensions' => [
            'jpg', 'jpeg', 'png', 'gif', 'webp', 'avif', 'svg',
            'mp4', 'mov', 'webm', 'mkv',
            'mp3', 'wav', 'ogg', 'm4a',
            'pdf', 'vtt', 'srt', 'txt', 'csv',
        ],

        // Number of files shown per page in the file manager grid/list.
        'per_page' => 50,

        // Thumbnail dimensions used by the file manager grid.
        'thumbnail_width' => 300,
        'thumbnail_height' => 200,

        // Where generated thumbnails are written. Under 'thumbnails/' so it is
        // covered by excluded_paths above and never appears as media.
        'thumbnail_dir' => 'thumbnails/.filemanager',

        // Cache duration for generated file-manager thumbnails and folder metadata (seconds).
        'cache_ttl' => 300,

        // Cache duration for probed video durations (seconds). Keyed on the
        // file's mtime as well, so a replaced file is re-probed regardless;
        // this only bounds how long a *missing* duration stays missing.
        'duration_cache_ttl' => 86400,
    ],
];

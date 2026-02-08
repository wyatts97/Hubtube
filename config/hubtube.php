<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Platform Settings
    |--------------------------------------------------------------------------
    */
    'platform_cut' => env('PLATFORM_CUT_PERCENTAGE', 20),
    'min_withdrawal' => env('MIN_WITHDRAWAL_AMOUNT', 50),
    
    /*
    |--------------------------------------------------------------------------
    | Age Verification
    |--------------------------------------------------------------------------
    */
    'age_verification_required' => env('AGE_VERIFICATION_REQUIRED', true),
    'minimum_age' => 18,
    
    /*
    |--------------------------------------------------------------------------
    | Video Settings
    |--------------------------------------------------------------------------
    */
    'video' => [
        'max_upload_size' => env('MAX_VIDEO_UPLOAD_SIZE', 5368709120), // 5GB
        'allowed_extensions' => ['mp4', 'mov', 'avi', 'mkv', 'webm', 'wmv', 'flv'],
        'thumbnail_count' => 3,
        'shorts_max_duration' => 60, // seconds
        'qualities' => ['240p', '360p', '480p', '720p', '1080p', '1440p', '4k'],
        'default_privacy' => 'public', // public, private, unlisted
    ],
    
    /*
    |--------------------------------------------------------------------------
    | FFmpeg Settings
    |--------------------------------------------------------------------------
    */
    'ffmpeg' => [
        'binary' => env('FFMPEG_BINARY', '/usr/bin/ffmpeg'),
        'ffprobe' => env('FFPROBE_BINARY', '/usr/bin/ffprobe'),
        'threads' => env('FFMPEG_THREADS', 4),
        'timeout' => env('FFMPEG_TIMEOUT', 3600),
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Cloud Storage
    |--------------------------------------------------------------------------
    */
    'storage' => [
        'driver' => env('CLOUD_STORAGE_DRIVER', 'local'),
        'cdn_enabled' => env('CDN_ENABLED', false),
        'cdn_url' => env('CDN_URL'),
        'wasabi' => [
            'access_key' => env('WASABI_ACCESS_KEY'),
            'secret_key' => env('WASABI_SECRET_KEY'),
            'bucket' => env('WASABI_BUCKET'),
            'region' => env('WASABI_REGION', 'us-east-1'),
            'endpoint' => env('WASABI_ENDPOINT', 'https://s3.wasabisys.com'),
            'url' => env('WASABI_URL'),
        ],
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Agora Live Streaming
    |--------------------------------------------------------------------------
    */
    'agora' => [
        'app_id' => env('AGORA_APP_ID'),
        'app_certificate' => env('AGORA_APP_CERTIFICATE'),
        'token_expiry' => 86400, // 24 hours
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Monetization
    |--------------------------------------------------------------------------
    */
    'monetization' => [
        'enabled' => true,
        'min_payout' => 50,
        'currency' => 'USD',
        'gift_platform_cut' => 20, // percentage
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Virtual Gifts
    |--------------------------------------------------------------------------
    */
    'gifts' => [
        'enabled' => true,
        'min_balance_to_send' => 1,
    ],
    
    /*
    |--------------------------------------------------------------------------
    | User Limits
    |--------------------------------------------------------------------------
    */
    'limits' => [
        'free' => [
            'daily_uploads' => 5,
            'max_video_size' => 1073741824, // 1GB
            'can_go_live' => false,
        ],
        'pro' => [
            'daily_uploads' => 50,
            'max_video_size' => 5368709120, // 5GB
            'can_go_live' => true,
        ],
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

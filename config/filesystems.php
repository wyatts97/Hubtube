<?php

return [
    'default' => env('FILESYSTEM_DISK', 'local'),

    'disks' => [
        'local' => [
            'driver' => 'local',
            'root' => storage_path('app'),
            'throw' => false,
        ],

        'public' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => env('APP_URL').'/storage',
            'visibility' => 'public',
            'throw' => false,
        ],

        'videos' => [
            'driver' => 'local',
            'root' => storage_path('app/videos'),
            'throw' => false,
        ],

        'wasabi' => [
            'driver' => 's3',
            'key' => env('WASABI_ACCESS_KEY'),
            'secret' => env('WASABI_SECRET_KEY'),
            'region' => env('WASABI_REGION', 'us-east-1'),
            'bucket' => env('WASABI_BUCKET'),
            'endpoint' => env('WASABI_ENDPOINT', 'https://s3.wasabisys.com'),
        ],

        'b2' => [
            'driver' => 's3',
            'key' => env('B2_ACCESS_KEY'),
            'secret' => env('B2_SECRET_KEY'),
            'region' => env('B2_REGION', 'us-west-002'),
            'bucket' => env('B2_BUCKET'),
            'endpoint' => env('B2_ENDPOINT'),
        ],
    ],

    'links' => [
        public_path('storage') => storage_path('app/public'),
    ],
];

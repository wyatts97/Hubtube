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

        // Wasabi and B2 disks are configured at runtime by StorageManager
        // using credentials from the admin panel (Setting model).
        // These placeholder entries ensure Laravel recognizes the disk names.
        'wasabi' => [
            'driver' => 's3',
            'key' => '',
            'secret' => '',
            'region' => 'us-east-1',
            'bucket' => '',
            'endpoint' => 'https://s3.wasabisys.com',
            'use_path_style_endpoint' => false,
            'visibility' => 'public',
            'throw' => true,
        ],

        'b2' => [
            'driver' => 's3',
            'key' => '',
            'secret' => '',
            'region' => 'us-west-002',
            'bucket' => '',
            'endpoint' => '',
        ],
    ],

    'links' => [
        public_path('storage') => storage_path('app/public'),
    ],
];

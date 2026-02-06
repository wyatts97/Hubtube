<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Temporary File Uploads
    |--------------------------------------------------------------------------
    |
    | Livewire handles file uploads by storing uploads in a temporary directory
    | before the developer has a chance to validate and store them. All file
    | uploads are validated and cleaned up automatically.
    |
    */

    'temporary_file_upload' => [
        'disk' => null, // Uses the default filesystem disk (local)
        'rules' => 'file|max:262144', // 256MB max, accept any file type
        'directory' => null,
        'middleware' => null,
        'preview_mimes' => [
            'png', 'gif', 'bmp', 'svg', 'wav', 'mp4',
            'mov', 'avi', 'wmv', 'mp3', 'm4a',
            'jpg', 'jpeg', 'mpga', 'webp', 'wma',
        ],
        'max_upload_time' => 300, // 5 minutes
        'cleanup' => true,
    ],

];

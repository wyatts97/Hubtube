<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'scraper' => [
        'url' => env('SCRAPER_URL', 'http://localhost:3001'),
    ],

    'bunny_stream' => [
        'api_key' => env('BUNNY_STREAM_API_KEY', ''),
        'library_id' => env('BUNNY_STREAM_LIBRARY_ID', '250371'),
        'cdn_host' => env('BUNNY_STREAM_CDN_HOST', 'vz-1530c1f0-3aa.b-cdn.net'),
        'cdn_token_key' => env('BUNNY_STREAM_CDN_TOKEN_KEY', ''),
    ],

];

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

    // Scraper and Bunny Stream settings are managed via the admin panel (Setting model).
    // No env() calls needed — services read directly from Setting::get().

    // Social Login — credentials managed via Admin Panel → Integrations → Social Networks
    // Resolved at runtime from the Setting model (encrypted) via SocialLoginServiceProvider
    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID', ''),
        'client_secret' => env('GOOGLE_CLIENT_SECRET', ''),
        'redirect' => '/auth/google/callback',
    ],

    'twitter-oauth-2' => [
        'client_id' => env('TWITTER_CLIENT_ID', ''),
        'client_secret' => env('TWITTER_CLIENT_SECRET', ''),
        'redirect' => '/auth/twitter/callback',
    ],

    'reddit' => [
        'client_id' => env('REDDIT_CLIENT_ID', ''),
        'client_secret' => env('REDDIT_CLIENT_SECRET', ''),
        'redirect' => '/auth/reddit/callback',
    ],

];

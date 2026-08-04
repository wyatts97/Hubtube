<?php

declare(strict_types=1);

use Filament\Support\Icons\Heroicon;

return [

    /* -----------------------------------------------------------------
    | Driver
    | -----------------------------------------------------------------
    | Available drivers: 'daily', 'stack', 'raw'
    | -----------------------------------------------------------------
     */

    'driver' => env('FILAMENT_LOG_VIEWER_DRIVER', env('LOG_CHANNEL', 'stack')),

    /* -----------------------------------------------------------------
    | Resource configuration
    | -----------------------------------------------------------------
     */

    'resource' => [
        'slug' => 'logs',
        'cluster' => null,
    ],

    /* -----------------------------------------------------------------
    | View log in modal
    | -----------------------------------------------------------------
    | When true, clicking "View" opens the log in a modal instead of a
    | separate page. Set to false to use the full-page ViewLog page.
    | -----------------------------------------------------------------
     */

    'view_in_modal' => env('FILAMENT_LOG_VIEWER_VIEW_IN_MODAL', false),

    /* -----------------------------------------------------------------
    | Logs files can be cleared
    | -----------------------------------------------------------------
    */

    'clearable' => env('FILAMENT_LOG_VIEWER_CLEARABLE', false),

    /* -----------------------------------------------------------------
    |  Log files storage path
    | -----------------------------------------------------------------
     */

    'storage_path' => storage_path('logs'),

    /* -----------------------------------------------------------------
    |  Log files pattern
    | -----------------------------------------------------------------
     */

    'pattern' => [
        'prefix' => 'laravel-',
        'date' => '[0-9][0-9][0-9][0-9]-[0-9][0-9]-[0-9][0-9]',
        'extension' => '.log',
    ],

    /* -----------------------------------------------------------------
    |  Log entries per page
    | -----------------------------------------------------------------
    |  This defines how many logs and entries are displayed per page.
     */

    'per-page' => [
        5,
        10,
        25,
        30,
    ],

    /* -----------------------------------------------------------------
    |  Download settings
    | -----------------------------------------------------------------
     */

    'download' => [
        'prefix' => 'laravel-',

        'extension' => 'log',
    ],

    /* -----------------------------------------------------------------
    |  Icons
    | -----------------------------------------------------------------
     */

    'icons' => [
        'all' => Heroicon::ListBullet,
        'emergency' => Heroicon::BugAnt,
        'alert' => Heroicon::Megaphone,
        'critical' => Heroicon::Fire,
        'error' => Heroicon::XCircle,
        'warning' => Heroicon::ExclamationTriangle,
        'notice' => Heroicon::ExclamationCircle,
        'info' => Heroicon::InformationCircle,
        'debug' => Heroicon::CommandLine,
    ],

    /* -----------------------------------------------------------------
    |  Colors
    | -----------------------------------------------------------------
    |  Tuned for dark mode — brighter, higher-contrast variants
    |  that read well on the zinc/zinc-900 admin background.
    | -----------------------------------------------------------------
     */

    'colors' => [
        'levels' => [
            'all' => '#d4d4d8',
            'emergency' => '#fca5a5',
            'alert' => '#f87171',
            'critical' => '#ef4444',
            'error' => '#fb923c',
            'warning' => '#fbbf24',
            'notice' => '#86efac',
            'info' => '#93c5fd',
            'debug' => '#bfdbfe',
        ],
    ],

    /* -----------------------------------------------------------------
    |  Strings to highlight in stack trace
    | -----------------------------------------------------------------
     */

    'highlight' => [
        '^#\d+', '^Stack trace:',
    ],
];

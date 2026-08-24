<?php

use App\Models\Page;
use App\Services\Translation\Providers\GoogleTranslateProvider;
use App\Services\Translation\Providers\LibreTranslateProvider;

return [

    /*
    |--------------------------------------------------------------------------
    | Default driver
    |--------------------------------------------------------------------------
    | Used when the `translation_provider` setting has not been chosen yet.
    | Admins change the live value in Admin → Languages, not here.
    */

    'default' => 'google',

    /*
    |--------------------------------------------------------------------------
    | Drivers
    |--------------------------------------------------------------------------
    */

    'drivers' => [
        'google' => [
            'class' => GoogleTranslateProvider::class,
        ],
        'libretranslate' => [
            'class' => LibreTranslateProvider::class,
            'timeout' => 30,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Throttling and retries
    |--------------------------------------------------------------------------
    | The Google scraper is unmetered and bans aggressively, so it is paced
    | hard. A self-hosted LibreTranslate is yours — pacing it only slows your
    | own runs down.
    */

    'throttle' => [
        'google' => ['min_delay_ms' => 1200, 'max_retries' => 4, 'backoff' => [5, 10, 20, 40]],
        'libretranslate' => ['min_delay_ms' => 0, 'max_retries' => 3, 'backoff' => [2, 5, 15]],
    ],

    /*
    |--------------------------------------------------------------------------
    | Batching
    |--------------------------------------------------------------------------
    | max_items = 1 means the engine has no batch endpoint. Keep max_chars
    | comfortably under the server's LT_CHAR_LIMIT.
    */

    'batch' => [
        'google' => ['max_items' => 1, 'max_chars' => 5000],
        'libretranslate' => ['max_items' => 25, 'max_chars' => 4000],
    ],

    /*
    |--------------------------------------------------------------------------
    | App locale → provider code
    |--------------------------------------------------------------------------
    | LibreTranslate does not use plain ISO 639-1 throughout. Brazilian
    | Portuguese is exposed by the API as `pt-BR` (plain `pt` is European
    | Portuguese). This app's toHreflang() already advertises pt-BR, so the app
    | locale `pt` maps to `pt-BR`.
    |
    | Careful: the API code and the Argos *package* code differ. The model is
    | packaged as `pb`, which is what LT_LOAD_ONLY expects, but /languages and
    | the `target` parameter both report and accept `pt-BR`. Always trust
    | GET /languages for the values that belong here.
    |
    | Admins can override per-driver via the `translation_locale_overrides`
    | setting. Provider codes never leave the outbound HTTP body — URLs,
    | translations.locale and the i18n filenames all stay on app locales.
    */

    'locale_map' => [
        'google' => [],
        'libretranslate' => [
            'pt' => 'pt-BR',  // Brazilian Portuguese (packaged as `pb`)
            'no' => 'nb',   // Norwegian Bokmål
            'fil' => 'tl',  // Tagalog
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Fields translated as HTML
    |--------------------------------------------------------------------------
    | Sent with format=html so markup survives. The Google scraper mangles
    | HTML, so this only applies to drivers that declare support.
    */

    'html_fields' => [
        Page::class => ['content'],
    ],

    'html_capable_drivers' => ['libretranslate'],

    /*
    |--------------------------------------------------------------------------
    | Scheduled runs
    |--------------------------------------------------------------------------
    */

    'schedule' => [
        'default_frequency' => 'daily',
        'default_time' => '03:30',
        'default_limit' => 500,
    ],
];

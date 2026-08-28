<?php

namespace App\Console\Commands;

use App\Http\Middleware\HandleInertiaRequests;
use App\Services\TranslationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class ClearTranslationCache extends Command
{
    protected $signature = 'translations:clear-cache';

    protected $description = 'Clear the cached UI translation catalogues (resources/js/i18n/*.json).';

    public function handle(): int
    {
        // Every locale the app knows about, not just the enabled ones — a
        // locale that was disabled since the last edit still holds a stale
        // entry that would be served if it were switched back on.
        $locales = array_keys(TranslationService::LANGUAGES);

        foreach ($locales as $locale) {
            // The legacy unversioned key, plus the mtime-versioned key the
            // middleware writes today.
            Cache::forget("i18n:ui:{$locale}");
            Cache::forget(HandleInertiaRequests::uiTranslationCacheKey($locale));
        }

        $this->info('Cleared cached UI translations for '.count($locales).' locales.');

        return self::SUCCESS;
    }
}

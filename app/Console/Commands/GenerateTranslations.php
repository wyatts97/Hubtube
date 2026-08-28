<?php

namespace App\Console\Commands;

use Exception;
use App\Models\TranslationOverride;
use App\Services\TranslationService;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use App\Services\Translation\Contracts\TranslationProvider;
use App\Services\Translation\TranslationProviderException;
use App\Services\Translation\TranslationProviderManager;

class GenerateTranslations extends Command
{
    protected $signature = 'translations:generate
                            {locale? : Target locale code (e.g. es, fr, de). Omit to generate all enabled locales}
                            {--force : Overwrite existing translation files completely (re-translates everything)}
                            {--retry-untranslated : Also re-translate keys whose stored value is still identical to the English source}
                            {--delay=250 : Milliseconds to wait between provider calls (raise if you hit rate limits)}';

    protected $description = 'Auto-generate i18n JSON files for UI strings using the configured translation provider. By default, merges new/missing keys into existing files without overwriting existing translations.';

    protected int $newKeysCount = 0;
    protected int $removedKeysCount = 0;

    /** Keys the provider could not translate; deliberately left out of the file. */
    protected int $failedKeysCount = 0;

    /** Consecutive provider failures — used to bail out of a hard rate limit. */
    protected int $consecutiveFailures = 0;

    /** Set once the provider is clearly refusing everything. */
    protected bool $rateLimited = false;

    protected int $delayMs = 250;

    protected bool $retryUntranslated = false;

    /**
     * Give up on a locale after this many provider failures in a row. Grinding
     * through hundreds of keys against a hard 429 wastes time and deepens the
     * block; better to stop and let the operator retry later.
     */
    protected const MAX_CONSECUTIVE_FAILURES = 8;

    public function handle(): int
    {
        $sourcePath = resource_path('js/i18n/en.json');
        if (!file_exists($sourcePath)) {
            $this->error('Source file not found: resources/js/i18n/en.json');
            return 1;
        }

        $source = json_decode(file_get_contents($sourcePath), true);
        if (!$source) {
            $this->error('Failed to parse en.json');
            return 1;
        }

        $targetLocale = $this->argument('locale');
        $force = $this->option('force');
        $this->retryUntranslated = (bool) $this->option('retry-untranslated');
        $this->delayMs = max(0, (int) $this->option('delay'));

        if ($targetLocale) {
            if (!isset(TranslationService::LANGUAGES[$targetLocale])) {
                $this->error("Unknown locale: {$targetLocale}");
                return 1;
            }
            $locales = [$targetLocale];
        } else {
            $locales = TranslationService::getEnabledLocales();
            $locales = array_filter($locales, fn($l) => $l !== 'en');
        }

        if (empty($locales)) {
            $this->info('No locales to generate (only English is enabled).');
            return 0;
        }

        $provider = app(TranslationProviderManager::class)->default();
        $this->line("  Using provider: <info>{$provider->label()}</info>");

        foreach ($locales as $locale) {
            $targetPath = resource_path("js/i18n/{$locale}.json");
            $langName = TranslationService::LANGUAGES[$locale]['name'] ?? $locale;
            $existing = [];

            // Always load the existing file. Even in --force mode it is needed as
            // a safety net: a key the provider refuses must keep whatever
            // translation it already had rather than being dropped from the file.
            if (file_exists($targetPath)) {
                $existing = json_decode(file_get_contents($targetPath), true) ?: [];
            }

            $this->consecutiveFailures = 0;
            $this->rateLimited = false;

            if ($force || empty($existing)) {
                // Full generation: translate everything from scratch
                $mode = empty($existing) ? 'new' : 'force';
                $this->info("Generating {$locale} ({$langName}) [{$mode}]...");
                $this->failedKeysCount = 0;
                $translated = $this->translateArray($source, $existing, $provider, $locale);

                if ($this->failedKeysCount > 0) {
                    $this->warn("  {$this->failedKeysCount} key(s) could not be translated; their previous values were kept.");
                }
            } else {
                // Merge mode: only translate missing keys, remove stale keys
                $this->newKeysCount = 0;
                $this->removedKeysCount = 0;
                $this->failedKeysCount = 0;
                $this->info("Syncing {$locale} ({$langName}) — merging new keys...");
                $translated = $this->mergeTranslations($source, $existing, $provider, $locale);

                if ($this->newKeysCount === 0 && $this->removedKeysCount === 0) {
                    if ($this->failedKeysCount > 0) {
                        // Don't claim success: the provider refused every key
                        // (rate limit, network), so the file is still short.
                        $this->warn("  {$locale}: {$this->failedKeysCount} key(s) could not be translated — nothing written, rerun to retry.");
                    } else {
                        $this->line("  <info>✓</info> {$locale} is already up to date");
                    }

                    continue;
                }

                $this->line("  Added <info>{$this->newKeysCount}</info> new key(s), removed <comment>{$this->removedKeysCount}</comment> stale key(s)");

                if ($this->failedKeysCount > 0) {
                    $this->warn("  {$this->failedKeysCount} key(s) could not be translated and were left out — rerun to retry them.");
                }
            }

            $dir = dirname($targetPath);
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }

            file_put_contents(
                $targetPath,
                json_encode($translated, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n"
            );

            // HandleInertiaRequests caches these catalogues forever.
            \Illuminate\Support\Facades\Cache::forget("i18n:ui:{$locale}");
            \Illuminate\Support\Facades\Cache::forget(
                \App\Http\Middleware\HandleInertiaRequests::uiTranslationCacheKey($locale)
            );

            $this->line("  <info>✓</info> Written to resources/js/i18n/{$locale}.json");

            // Small delay to avoid rate limiting
            usleep(500000); // 0.5s
        }

        $this->newLine();
        $this->info('Done! Run `npm run build` to include the new translations.');

        return 0;
    }

    /**
     * Recursively merge source (en.json) with existing translations.
     * - Keys in source but NOT in existing → translate and add (new keys)
     * - Keys in both → keep existing translation (preserve human edits)
     * - Keys in existing but NOT in source → remove (stale keys)
     */
    protected function mergeTranslations(array $source, array $existing, TranslationProvider $provider, string $locale): array
    {
        $result = [];

        foreach ($source as $key => $value) {
            if (is_array($value)) {
                // Recurse into nested sections
                $existingChild = (isset($existing[$key]) && is_array($existing[$key])) ? $existing[$key] : [];
                $result[$key] = $this->mergeTranslations($value, $existingChild, $provider, $locale);
            } elseif (is_string($value)) {
                $stale = $this->retryUntranslated
                    && isset($existing[$key])
                    && is_string($existing[$key])
                    && $existing[$key] === $value;

                if (isset($existing[$key]) && is_string($existing[$key]) && !$stale) {
                    // Key exists — keep existing translation
                    $result[$key] = $existing[$key];
                } else {
                    // New key — translate it. A failure leaves the key out so
                    // the next run retries it.
                    $translated = $this->translateString($value, $key, $provider, $locale);

                    if ($translated !== null) {
                        $result[$key] = $translated;
                        $this->newKeysCount++;
                    } elseif ($stale) {
                        // Retry failed — leave the untranslated value in place.
                        $result[$key] = $existing[$key];
                    }
                }
            } else {
                $result[$key] = $value;
            }
        }

        // Count removed keys (in existing but not in source)
        foreach ($existing as $key => $value) {
            if (!array_key_exists($key, $source)) {
                $this->removedKeysCount++;
            }
        }

        return $result;
    }

    /**
     * Recursively translate all string values in a nested array (full generation).
     */
    protected function translateArray(array $source, array $existing, TranslationProvider $provider, string $locale): array
    {
        $result = [];

        foreach ($source as $key => $value) {
            $previous = $existing[$key] ?? null;

            if (is_array($value)) {
                $result[$key] = $this->translateArray($value, is_array($previous) ? $previous : [], $provider, $locale);
            } elseif (is_string($value)) {
                $translated = $this->translateString($value, $key, $provider, $locale);

                if ($translated !== null) {
                    $result[$key] = $translated;
                } elseif (is_string($previous) && $previous !== $value) {
                    // Provider refused, but a real translation already existed —
                    // keep it rather than regressing the file.
                    $result[$key] = $previous;
                }
            } else {
                $result[$key] = $value;
            }
        }

        return $result;
    }

    /**
     * Translate a single string value, preserving interpolation placeholders.
     */
    /**
     * Translate one message, or return null if the provider could not.
     *
     * Null matters: writing the English source under a translated key would
     * make the catalogue *look* complete, so useI18n()'s fallback chain would
     * stop supplying English and a later merge run would never retry the key.
     * Omitting it leaves both behaviours intact.
     */
    protected function translateString(string $value, string $key, TranslationProvider $provider, string $locale): ?string
    {
        // Plural messages are pipe-separated forms ("{count} view | {count} views").
        // Translate each form on its own so the separator and the form count survive.
        if (str_contains($value, '|')) {
            $forms = [];

            foreach (explode('|', $value) as $form) {
                $translatedForm = $this->translateString(trim($form), $key, $provider, $locale);

                if ($translatedForm === null) {
                    return null;
                }

                $forms[] = $translatedForm;
            }

            return implode(' | ', $forms);
        }

        // Preserve interpolation placeholders like {count}, {name}
        $placeholders = [];
        $text = preg_replace_callback('/\{(\w+)\}/', function ($match) use (&$placeholders) {
            $token = '___PH' . count($placeholders) . '___';
            $placeholders[$token] = $match[0];
            return $token;
        }, $value);

        // Once the provider is hard-limiting us, stop asking. Continuing just
        // burns time and deepens the block.
        if ($this->rateLimited) {
            $this->failedKeysCount++;

            return null;
        }

        $translated = null;

        foreach ([0, 5, 15, 45] as $attempt => $waitSeconds) {
            if ($waitSeconds > 0) {
                sleep($waitSeconds);
            }

            try {
                $translated = $provider->translate($text, $locale, 'en');

                if ($translated !== null) {
                    $this->consecutiveFailures = 0;
                    break;
                }
            } catch (TranslationProviderException $e) {
                // Fatal reasons (bad key, no model for this language) will fail
                // identically on every remaining key — stop the locale now.
                if ($e->isFatal()) {
                    $this->warn("  Fatal: {$e->getMessage()}");
                    $this->rateLimited = true;
                    break;
                }

                if (! $e->isRetryable() || $attempt === 3) {
                    $this->warn("  Failed to translate key: {$key} — ".Str::limit($e->getMessage(), 120));
                    break;
                }

                $this->line("  <comment>Provider unavailable</comment> on {$key}, retrying in {$waitSeconds}s...");
            } catch (Exception $e) {
                $this->warn("  Failed to translate key: {$key} — ".Str::limit($e->getMessage(), 120));
                break;
            }
        }

        if ($translated === null) {
            $this->failedKeysCount++;
            $this->consecutiveFailures++;

            if ($this->consecutiveFailures >= self::MAX_CONSECUTIVE_FAILURES) {
                $this->rateLimited = true;
                $this->newLine();
                $this->warn('  Provider refused '.self::MAX_CONSECUTIVE_FAILURES.' keys in a row — abandoning the rest of this locale.');
                $this->warn('  Existing translations are preserved. Retry later, optionally with --delay=1000.');
            }

            return null;
        }

        // Restore placeholders
        foreach ($placeholders as $token => $original) {
            $translated = str_replace($token, $original, $translated);
        }

        // Apply admin-defined word/phrase overrides
        $translated = TranslationOverride::applyOverrides($translated, $locale);

        // Pace requests; --delay raises this when the provider is touchy.
        usleep($this->delayMs * 1000);

        return $translated;
    }
}

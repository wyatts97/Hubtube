<?php

namespace App\Console\Commands;

use App\Models\TranslationOverride;
use App\Services\TranslationService;
use Illuminate\Console\Command;
use Stichoza\GoogleTranslate\GoogleTranslate;

class GenerateTranslations extends Command
{
    protected $signature = 'translations:generate
                            {locale? : Target locale code (e.g. es, fr, de). Omit to generate all enabled locales}
                            {--force : Overwrite existing translation files completely (re-translates everything)}';

    protected $description = 'Auto-generate i18n JSON files for UI strings using Google Translate. By default, merges new/missing keys into existing files without overwriting existing translations.';

    protected int $newKeysCount = 0;
    protected int $removedKeysCount = 0;

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

        $translator = new GoogleTranslate();
        $translator->setSource('en');

        foreach ($locales as $locale) {
            $targetPath = resource_path("js/i18n/{$locale}.json");
            $langName = TranslationService::LANGUAGES[$locale]['name'] ?? $locale;
            $existing = [];

            // Load existing translations if file exists
            if (file_exists($targetPath) && !$force) {
                $existing = json_decode(file_get_contents($targetPath), true) ?: [];
            }

            $translator->setTarget($locale);

            if ($force || empty($existing)) {
                // Full generation: translate everything from scratch
                $mode = empty($existing) ? 'new' : 'force';
                $this->info("Generating {$locale} ({$langName}) [{$mode}]...");
                $translated = $this->translateArray($source, $translator, $locale);
            } else {
                // Merge mode: only translate missing keys, remove stale keys
                $this->newKeysCount = 0;
                $this->removedKeysCount = 0;
                $this->info("Syncing {$locale} ({$langName}) — merging new keys...");
                $translated = $this->mergeTranslations($source, $existing, $translator, $locale);

                if ($this->newKeysCount === 0 && $this->removedKeysCount === 0) {
                    $this->line("  <info>✓</info> {$locale} is already up to date");
                    continue;
                }

                $this->line("  Added <info>{$this->newKeysCount}</info> new key(s), removed <comment>{$this->removedKeysCount}</comment> stale key(s)");
            }

            $dir = dirname($targetPath);
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }

            file_put_contents(
                $targetPath,
                json_encode($translated, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n"
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
    protected function mergeTranslations(array $source, array $existing, GoogleTranslate $translator, string $locale): array
    {
        $result = [];

        foreach ($source as $key => $value) {
            if (is_array($value)) {
                // Recurse into nested sections
                $existingChild = (isset($existing[$key]) && is_array($existing[$key])) ? $existing[$key] : [];
                $result[$key] = $this->mergeTranslations($value, $existingChild, $translator, $locale);
            } elseif (is_string($value)) {
                if (isset($existing[$key]) && is_string($existing[$key])) {
                    // Key exists — keep existing translation
                    $result[$key] = $existing[$key];
                } else {
                    // New key — translate it
                    $result[$key] = $this->translateString($value, $key, $translator, $locale);
                    $this->newKeysCount++;
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
    protected function translateArray(array $source, GoogleTranslate $translator, string $locale): array
    {
        $result = [];

        foreach ($source as $key => $value) {
            if (is_array($value)) {
                $result[$key] = $this->translateArray($value, $translator, $locale);
            } elseif (is_string($value)) {
                $result[$key] = $this->translateString($value, $key, $translator, $locale);
            } else {
                $result[$key] = $value;
            }
        }

        return $result;
    }

    /**
     * Translate a single string value, preserving interpolation placeholders.
     */
    protected function translateString(string $value, string $key, GoogleTranslate $translator, string $locale): string
    {
        // Preserve interpolation placeholders like {count}, {name}
        $placeholders = [];
        $text = preg_replace_callback('/\{(\w+)\}/', function ($match) use (&$placeholders) {
            $token = '___PH' . count($placeholders) . '___';
            $placeholders[$token] = $match[0];
            return $token;
        }, $value);

        try {
            $translated = $translator->translate($text);
            if ($translated === null) {
                $translated = $text;
            }
        } catch (\Exception $e) {
            $this->warn("  Failed to translate key: {$key} — {$e->getMessage()}");
            $translated = $text;
        }

        // Restore placeholders
        foreach ($placeholders as $token => $original) {
            $translated = str_replace($token, $original, $translated);
        }

        // Apply admin-defined word/phrase overrides
        $translated = TranslationOverride::applyOverrides($translated, $locale);

        // Tiny delay between strings to avoid rate limiting
        usleep(100000); // 0.1s

        return $translated;
    }
}

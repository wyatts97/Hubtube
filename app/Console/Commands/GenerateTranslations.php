<?php

namespace App\Console\Commands;

use App\Services\TranslationService;
use Illuminate\Console\Command;
use Stichoza\GoogleTranslate\GoogleTranslate;

class GenerateTranslations extends Command
{
    protected $signature = 'translations:generate
                            {locale? : Target locale code (e.g. es, fr, de). Omit to generate all enabled locales}
                            {--force : Overwrite existing translation files}';

    protected $description = 'Auto-generate i18n JSON files for UI strings using Google Translate';

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

            if (file_exists($targetPath) && !$force) {
                $this->line("  <comment>Skipping {$locale}</comment> — file exists (use --force to overwrite)");
                continue;
            }

            $langName = TranslationService::LANGUAGES[$locale]['name'] ?? $locale;
            $this->info("Generating {$locale} ({$langName})...");

            $translator->setTarget($locale);
            $translated = $this->translateArray($source, $translator);

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
     * Recursively translate all string values in a nested array.
     */
    protected function translateArray(array $source, GoogleTranslate $translator): array
    {
        $result = [];

        foreach ($source as $key => $value) {
            if (is_array($value)) {
                $result[$key] = $this->translateArray($value, $translator);
            } elseif (is_string($value)) {
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

                $result[$key] = $translated;

                // Tiny delay between strings to avoid rate limiting
                usleep(100000); // 0.1s
            } else {
                $result[$key] = $value;
            }
        }

        return $result;
    }
}

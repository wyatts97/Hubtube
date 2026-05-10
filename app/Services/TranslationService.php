<?php

namespace App\Services;

use App\Models\Setting;
use App\Models\Translation;
use App\Models\TranslationOverride;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Stichoza\GoogleTranslate\GoogleTranslate;

class TranslationService
{
    protected ?GoogleTranslate $translator = null;

    /**
     * Supported languages with native names and flag emoji.
     */
    public const LANGUAGES = [
        'en' => ['name' => 'English', 'native' => 'English', 'flag' => '🇺🇸'],
        'es' => ['name' => 'Spanish', 'native' => 'Español', 'flag' => '🇪🇸'],
        'fr' => ['name' => 'French', 'native' => 'Français', 'flag' => '🇫🇷'],
        'de' => ['name' => 'German', 'native' => 'Deutsch', 'flag' => '🇩🇪'],
        'pt' => ['name' => 'Portuguese', 'native' => 'Português', 'flag' => '🇧🇷'],
        'it' => ['name' => 'Italian', 'native' => 'Italiano', 'flag' => '🇮🇹'],
        'nl' => ['name' => 'Dutch', 'native' => 'Nederlands', 'flag' => '🇳🇱'],
        'ru' => ['name' => 'Russian', 'native' => 'Русский', 'flag' => '🇷🇺'],
        'ja' => ['name' => 'Japanese', 'native' => '日本語', 'flag' => '🇯🇵'],
        'ko' => ['name' => 'Korean', 'native' => '한국어', 'flag' => '🇰🇷'],
        'zh' => ['name' => 'Chinese', 'native' => '中文', 'flag' => '🇨🇳'],
        'ar' => ['name' => 'Arabic', 'native' => 'العربية', 'flag' => '🇸🇦'],
        'hi' => ['name' => 'Hindi', 'native' => 'हिन्दी', 'flag' => '🇮🇳'],
        'tr' => ['name' => 'Turkish', 'native' => 'Türkçe', 'flag' => '🇹🇷'],
        'pl' => ['name' => 'Polish', 'native' => 'Polski', 'flag' => '🇵🇱'],
        'sv' => ['name' => 'Swedish', 'native' => 'Svenska', 'flag' => '🇸🇪'],
        'da' => ['name' => 'Danish', 'native' => 'Dansk', 'flag' => '🇩🇰'],
        'no' => ['name' => 'Norwegian', 'native' => 'Norsk', 'flag' => '🇳🇴'],
        'fi' => ['name' => 'Finnish', 'native' => 'Suomi', 'flag' => '🇫🇮'],
        'cs' => ['name' => 'Czech', 'native' => 'Čeština', 'flag' => '🇨🇿'],
        'th' => ['name' => 'Thai', 'native' => 'ไทย', 'flag' => '🇹🇭'],
        'vi' => ['name' => 'Vietnamese', 'native' => 'Tiếng Việt', 'flag' => '🇻🇳'],
        'id' => ['name' => 'Indonesian', 'native' => 'Bahasa Indonesia', 'flag' => '🇮🇩'],
        'ms' => ['name' => 'Malay', 'native' => 'Bahasa Melayu', 'flag' => '🇲🇾'],
        'ro' => ['name' => 'Romanian', 'native' => 'Română', 'flag' => '🇷🇴'],
        'uk' => ['name' => 'Ukrainian', 'native' => 'Українська', 'flag' => '🇺🇦'],
        'el' => ['name' => 'Greek', 'native' => 'Ελληνικά', 'flag' => '🇬🇷'],
        'hu' => ['name' => 'Hungarian', 'native' => 'Magyar', 'flag' => '🇭🇺'],
        'he' => ['name' => 'Hebrew', 'native' => 'עברית', 'flag' => '🇮🇱'],
        'bg' => ['name' => 'Bulgarian', 'native' => 'Български', 'flag' => '🇧🇬'],
        'hr' => ['name' => 'Croatian', 'native' => 'Hrvatski', 'flag' => '🇭🇷'],
        'sk' => ['name' => 'Slovak', 'native' => 'Slovenčina', 'flag' => '🇸🇰'],
        'sr' => ['name' => 'Serbian', 'native' => 'Српски', 'flag' => '🇷🇸'],
        'lt' => ['name' => 'Lithuanian', 'native' => 'Lietuvių', 'flag' => '🇱🇹'],
        'lv' => ['name' => 'Latvian', 'native' => 'Latviešu', 'flag' => '🇱🇻'],
        'et' => ['name' => 'Estonian', 'native' => 'Eesti', 'flag' => '🇪🇪'],
        'fil' => ['name' => 'Filipino', 'native' => 'Filipino', 'flag' => '🇵🇭'],
    ];

    /**
     * Get the default site language.
     */
    public static function getDefaultLocale(): string
    {
        return Setting::get('default_language', 'en');
    }

    /**
     * Get enabled languages from admin settings.
     */
    public static function getEnabledLocales(): array
    {
        if (!(bool) Setting::get('translation_enabled', false)) {
            return [static::getDefaultLocale()];
        }

        $enabled = Setting::get('enabled_languages');
        if (!$enabled) {
            return [static::getDefaultLocale()];
        }
        if (is_string($enabled)) {
            $enabled = json_decode($enabled, true);
        }
        return is_array($enabled) ? $enabled : [static::getDefaultLocale()];
    }

    /**
     * Get enabled languages with their metadata.
     */
    public static function getEnabledLanguages(): array
    {
        $enabled = static::getEnabledLocales();
        $languages = [];
        foreach ($enabled as $code) {
            if (isset(static::LANGUAGES[$code])) {
                $languages[$code] = static::LANGUAGES[$code];
            }
        }
        return $languages;
    }

    /**
     * Check if a locale code is valid and enabled.
     */
    public static function isValidLocale(string $locale): bool
    {
        return in_array($locale, static::getEnabledLocales());
    }

    /**
     * Get or create the Google Translate instance.
     */
    protected function getTranslator(): GoogleTranslate
    {
        if (!$this->translator) {
            $this->translator = new GoogleTranslate();
        }
        return $this->translator;
    }

    /**
     * Translate a raw string (no caching, no DB).
     */
    public function translateText(string $text, string $targetLocale, ?string $sourceLocale = null): string
    {
        if (empty(trim($text))) {
            return $text;
        }

        $source = $sourceLocale ?? static::getDefaultLocale();
        if ($source === $targetLocale) {
            return $text;
        }

        try {
            $translator = $this->getTranslator();
            $translator->setSource($source);
            $translator->setTarget($targetLocale);
            $translated = $translator->translate($text) ?? $text;

            // Apply admin-defined overrides (word/phrase corrections)
            $translated = TranslationOverride::applyOverrides($translated, $targetLocale);

            return $translated;
        } catch (\Exception $e) {
            Log::warning("Translation failed: {$e->getMessage()}", [
                'text' => Str::limit($text, 100),
                'target' => $targetLocale,
            ]);
            return $text;
        }
    }

    /**
     * Translate a model field with DB caching.
     * Returns the cached translation if available, otherwise translates and stores.
     */
    public function translateField(string $modelClass, int $modelId, string $field, string $originalValue, string $targetLocale): string
    {
        $defaultLocale = static::getDefaultLocale();
        if ($targetLocale === $defaultLocale) {
            return $originalValue;
        }

        // Check DB cache first
        $cached = Translation::getTranslation($modelClass, $modelId, $field, $targetLocale);
        if ($cached !== null) {
            return $cached;
        }

        // Translate
        $translated = $this->translateText($originalValue, $targetLocale, $defaultLocale);

        // Store in DB
        $data = [
            'translatable_type' => $modelClass,
            'translatable_id' => $modelId,
            'field' => $field,
            'locale' => $targetLocale,
            'value' => $translated,
        ];

        // Generate translated slug for title fields
        if ($field === 'title') {
            $data['translated_slug'] = $this->generateUniqueTranslatedSlug(
                $modelClass, $modelId, $targetLocale, $translated, $originalValue
            );
        }

        Translation::updateOrCreate(
            [
                'translatable_type' => $modelClass,
                'translatable_id' => $modelId,
                'field' => $field,
                'locale' => $targetLocale,
            ],
            $data
        );

        return $translated;
    }

    /**
     * Translate multiple fields for a model at once.
     */
    public function translateModel(string $modelClass, int $modelId, array $fields, string $targetLocale): array
    {
        $defaultLocale = static::getDefaultLocale();
        if ($targetLocale === $defaultLocale) {
            return $fields;
        }

        $result = [];
        foreach ($fields as $field => $value) {
            if (empty($value)) {
                $result[$field] = $value;
                continue;
            }
            $result[$field] = $this->translateField($modelClass, $modelId, $field, $value, $targetLocale);
        }

        return $result;
    }

    /**
     * Batch translate multiple items (e.g. video listings).
     * Returns array keyed by model ID with translated fields.
     */
    public function translateBatch(string $modelClass, array $items, array $fieldNames, string $targetLocale): array
    {
        $defaultLocale = static::getDefaultLocale();
        if ($targetLocale === $defaultLocale) {
            return $items;
        }

        $ids = array_column($items, 'id');

        // Fetch all existing translations in one query
        $existing = Translation::where('translatable_type', $modelClass)
            ->whereIn('translatable_id', $ids)
            ->where('locale', $targetLocale)
            ->whereIn('field', $fieldNames)
            ->get()
            ->groupBy('translatable_id');

        $result = [];
        foreach ($items as $item) {
            $id = $item['id'];
            $translated = $item;

            foreach ($fieldNames as $field) {
                if (empty($item[$field])) {
                    continue;
                }

                // Check if we already have a cached translation
                $cachedTranslation = $existing->get($id)?->firstWhere('field', $field);
                if ($cachedTranslation) {
                    $translated[$field] = $cachedTranslation->value;
                    if ($field === 'title' && $cachedTranslation->translated_slug) {
                        $translated['translated_slug'] = $cachedTranslation->translated_slug;
                    }
                } else {
                    // Translate on-the-fly and cache
                    $translatedValue = $this->translateText($item[$field], $targetLocale, $defaultLocale);
                    $translated[$field] = $translatedValue;

                    $data = [
                        'translatable_type' => $modelClass,
                        'translatable_id' => $id,
                        'field' => $field,
                        'locale' => $targetLocale,
                        'value' => $translatedValue,
                    ];

                    if ($field === 'title') {
                        $slug = $this->generateUniqueTranslatedSlug(
                            $modelClass, $id, $targetLocale, $translatedValue, $item[$field]
                        );
                        $data['translated_slug'] = $slug;
                        $translated['translated_slug'] = $slug;
                    }

                    Translation::updateOrCreate(
                        [
                            'translatable_type' => $modelClass,
                            'translatable_id' => $id,
                            'field' => $field,
                            'locale' => $targetLocale,
                        ],
                        $data
                    );
                }
            }

            $result[] = $translated;
        }

        return $result;
    }

    /**
     * Get the translated slug for a model, or null if not translated.
     */
    public function getTranslatedSlug(string $modelClass, int $modelId, string $locale): ?string
    {
        return Translation::where('translatable_type', $modelClass)
            ->where('translatable_id', $modelId)
            ->where('field', 'title')
            ->where('locale', $locale)
            ->value('translated_slug');
    }

    /**
     * Find a model ID by its translated slug.
     */
    public function findByTranslatedSlug(string $modelClass, string $slug, string $locale): ?int
    {
        return Translation::findBySlug($modelClass, $slug, $locale);
    }

    /**
     * Delete all translations for a model (e.g. when model is deleted).
     */
    public static function deleteTranslations(string $modelClass, int $modelId): void
    {
        Translation::where('translatable_type', $modelClass)
            ->where('translatable_id', $modelId)
            ->delete();
    }

    /**
     * Get all available translated slugs for a model (for hreflang tags).
     *
     * Returns URL map keyed by locale: ['en' => 'https://…', 'es' => 'https://…/es/…'].
     * Includes only the default locale + locales that have a confirmed translated slug.
     */
    public function getAlternateUrls(string $modelClass, int $modelId, string $originalSlug): array
    {
        $translations = Translation::where('translatable_type', $modelClass)
            ->where('translatable_id', $modelId)
            ->where('field', 'title')
            ->whereNotNull('translated_slug')
            ->pluck('translated_slug', 'locale')
            ->toArray();

        $defaultLocale = static::getDefaultLocale();
        $enabled = static::getEnabledLocales();
        $urls = [];

        // Default locale uses original slug
        if (in_array($defaultLocale, $enabled, true)) {
            $urls[$defaultLocale] = url("/{$originalSlug}");
        }

        // Translated locales use /{locale}/{translated_slug}, only if enabled
        foreach ($translations as $locale => $slug) {
            if (!in_array($locale, $enabled, true) || $locale === $defaultLocale) {
                continue;
            }
            $urls[$locale] = url("/{$locale}/{$slug}");
        }

        return $urls;
    }

    /**
     * Generate a slug for a translated value, ensuring uniqueness across the same
     * model type + locale combination.
     *
     * Falls back to a transliterated/original slug for non-Latin scripts where
     * Str::slug returns an empty string.
     */
    public function generateUniqueTranslatedSlug(
        string $modelClass,
        int $modelId,
        string $locale,
        string $translatedValue,
        ?string $originalValue = null,
    ): string {
        $base = Str::slug($translatedValue);

        // Non-Latin scripts (zh, ja, ko, ar, he, ru, hi, th) often produce empty slugs.
        // Fall back to a transliteration using PHP's intl/iconv, then to original-slug-{locale}.
        if ($base === '') {
            $base = Str::slug($translatedValue, '-', 'en');
        }
        if ($base === '' && function_exists('transliterator_transliterate')) {
            $tx = @transliterator_transliterate('Any-Latin; Latin-ASCII; [^A-Za-z0-9]+ Remove', $translatedValue);
            if (is_string($tx) && trim($tx) !== '') {
                $base = Str::slug($tx);
            }
        }
        if ($base === '' && $originalValue !== null) {
            $base = Str::slug($originalValue);
        }
        if ($base === '') {
            $base = $locale . '-' . $modelId;
        }

        $base = mb_substr($base, 0, 200);

        // Walk through suffixes until a free slug is found within the same model+locale scope.
        $slug = $base;
        $suffix = 1;
        while (
            Translation::where('translatable_type', $modelClass)
                ->where('locale', $locale)
                ->where('field', 'title')
                ->where('translated_slug', $slug)
                ->where('translatable_id', '!=', $modelId)
                ->exists()
        ) {
            $suffix++;
            $slug = $base . '-' . $suffix;
            if ($suffix > 50) {
                $slug = $base . '-' . $modelId;
                break;
            }
        }

        return $slug;
    }

    /**
     * Map a 2-letter locale code to a region-aware BCP 47 hreflang code
     * (e.g. 'pt' → 'pt-BR', 'zh' → 'zh-CN'). Falls back to the input
     * unchanged when no region mapping is known.
     */
    public static function toHreflang(string $locale): string
    {
        $map = [
            'en' => 'en',     'es' => 'es',     'fr' => 'fr',     'de' => 'de',
            'pt' => 'pt-BR',  'it' => 'it',     'nl' => 'nl',     'ru' => 'ru',
            'ja' => 'ja',     'ko' => 'ko',     'zh' => 'zh-CN',  'ar' => 'ar',
            'hi' => 'hi',     'tr' => 'tr',     'pl' => 'pl',     'sv' => 'sv',
            'da' => 'da',     'no' => 'nb',     'fi' => 'fi',     'cs' => 'cs',
            'th' => 'th',     'vi' => 'vi',     'id' => 'id',     'ms' => 'ms',
            'ro' => 'ro',     'uk' => 'uk',     'el' => 'el',     'hu' => 'hu',
            'he' => 'he',     'bg' => 'bg',     'hr' => 'hr',     'sk' => 'sk',
            'sr' => 'sr',     'lt' => 'lt',     'lv' => 'lv',     'et' => 'et',
            'fil' => 'fil-PH',
        ];
        return $map[$locale] ?? $locale;
    }

    /**
     * Build a validated hreflang map for a non-video page path.
     *
     * Returns ['hreflang' => 'url'] including 'x-default'. Skips locales that
     * resolve to identical URLs (which would produce duplicate hreflang entries
     * Google rejects).
     */
    public static function hreflangMapForPath(string $path): array
    {
        $enabled = static::getEnabledLocales();
        if (count($enabled) <= 1) {
            return [];
        }

        $defaultLocale = static::getDefaultLocale();
        $cleanPath = ltrim($path, '/');

        $map = [];
        $defaultUrl = url('/' . $cleanPath);
        $map['x-default'] = $defaultUrl;

        $seen = [$defaultUrl => true];

        foreach ($enabled as $locale) {
            $href = $locale === $defaultLocale
                ? $defaultUrl
                : url('/' . $locale . ($cleanPath ? '/' . $cleanPath : ''));

            // Avoid emitting duplicate hreflang entries pointing at the same URL.
            $tag = static::toHreflang($locale);
            if (!isset($seen[$href]) || $tag === $locale) {
                $map[$tag] = $href;
                $seen[$href] = true;
            }
        }

        return $map;
    }

    /**
     * Translate a batch of static UI strings.
     */
    public function translateUIStrings(array $strings, string $targetLocale): array
    {
        $defaultLocale = static::getDefaultLocale();
        if ($targetLocale === $defaultLocale) {
            return $strings;
        }

        $cacheKey = "ui_translations:{$targetLocale}:" . md5(implode('|', array_keys($strings)));

        return Cache::remember($cacheKey, 3600, function () use ($strings, $targetLocale, $defaultLocale) {
            $result = [];
            foreach ($strings as $key => $value) {
                try {
                    $result[$key] = $this->translateText($value, $targetLocale, $defaultLocale);
                } catch (\Exception $e) {
                    $result[$key] = $value;
                }
            }
            return $result;
        });
    }
}

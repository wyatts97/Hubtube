<?php

namespace App\Services;

use Exception;
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

    protected float $lastRequestTime = 0;

    protected int $minDelayMs = 1200;

    protected int $maxRetries = 4;

    /**
     * Supported languages with native names and flag emoji.
     */
    /**
     * Locales written right-to-left. Single source of truth: app.blade.php sets
     * the document `dir` from this, and it is shipped to the client in the
     * locale payload so useI18n() doesn't keep its own copy.
     */
    public const RTL_LOCALES = ['ar', 'he'];

    public static function isRtl(string $locale): bool
    {
        return in_array($locale, static::RTL_LOCALES, true);
    }

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
                $languages[$code] = static::LANGUAGES[$code] + [
                    'dir' => static::isRtl($code) ? 'rtl' : 'ltr',
                ];
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
     * Enforce a minimum delay between Google Translate API requests
     * to avoid 429 Too Many Responses rate limiting.
     */
    protected function throttle(): void
    {
        $now = microtime(true);
        $elapsed = ($now - $this->lastRequestTime) * 1000;
        if ($elapsed < $this->minDelayMs) {
            usleep((int)(($this->minDelayMs - $elapsed) * 1000));
        }
        $this->lastRequestTime = microtime(true);
    }

    /**
     * Translate a raw string (no caching, no DB).
     * Includes throttling and retry with exponential backoff on 429s.
     */
    /**
     * Translate text, returning the source unchanged if the provider fails.
     *
     * Convenient, but lossy: callers cannot tell a genuine translation from a
     * failure, and storing the result of a failure permanently poisons a cache
     * with source-language text. Anything that persists the result should use
     * tryTranslateText() instead.
     */
    public function translateText(string $text, string $targetLocale, ?string $sourceLocale = null): string
    {
        return $this->tryTranslateText($text, $targetLocale, $sourceLocale) ?? $text;
    }

    /**
     * Translate text, or return null if the provider could not.
     */
    public function tryTranslateText(string $text, string $targetLocale, ?string $sourceLocale = null): ?string
    {
        if (empty(trim($text))) {
            return $text;
        }

        $source = $sourceLocale ?? static::getDefaultLocale();
        if ($source === $targetLocale) {
            return $text;
        }

        $translator = $this->getTranslator();
        $translator->setSource($source);
        $translator->setTarget($targetLocale);

        $attempt = 0;
        while ($attempt <= $this->maxRetries) {
            try {
                $this->throttle();
                $translated = $translator->translate($text) ?? $text;

                // Apply admin-defined overrides (word/phrase corrections)
                $translated = TranslationOverride::applyOverrides($translated, $targetLocale);

                return $translated;
            } catch (Exception $e) {
                $is429 = str_contains($e->getMessage(), '429');
                if (!$is429 || $attempt === $this->maxRetries) {
                    Log::warning("Translation failed: {$e->getMessage()}", [
                        'text' => Str::limit($text, 100),
                        'target' => $targetLocale,
                        'attempt' => $attempt + 1,
                    ]);

                    return null;
                }

                // Exponential backoff: 5s, 10s, 20s, 40s
                $backoff = pow(2, $attempt) * 5000000; // microseconds
                usleep((int) $backoff);
                $attempt++;
            }
        }

        return null;
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

        // Translate. A failure must not be written: storing the source text
        // under a translated locale makes the row look done forever, so it is
        // never retried and the reader never falls back.
        $translated = $this->tryTranslateText($originalValue, $targetLocale, $defaultLocale);

        if ($translated === null) {
            return $originalValue;
        }

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
    /**
     * Cache key for a free-standing string translation (tags, and anything
     * else that isn't a model field and so has no translations-table row).
     */
    public static function textCacheKey(string $text, string $locale): string
    {
        return 'translation:text:'.$locale.':'.sha1($text);
    }

    /**
     * Previously translated free-standing string, or null if not yet known.
     *
     * translateText() itself is an uncached provider call, so request handlers
     * must go through this and queue TranslateTextJob for misses — otherwise
     * the same tag is re-translated on every single page view.
     */
    public function cachedText(string $text, string $targetLocale): ?string
    {
        if ($targetLocale === static::getDefaultLocale() || trim($text) === '') {
            return $text;
        }

        return Cache::get(static::textCacheKey($text, $targetLocale));
    }

    /**
     * Store a translated free-standing string.
     */
    public function rememberText(string $text, string $targetLocale, string $translated): void
    {
        Cache::put(static::textCacheKey($text, $targetLocale), $translated, now()->addDays(30));
    }

    /**
     * Briefly cache the source text after a failed translation.
     *
     * This is a negative cache, not a result: it stops the page re-queueing the
     * same doomed job on every view, and expires soon enough that a transient
     * provider outage heals itself without any intervention.
     */
    public function rememberFailedText(string $text, string $targetLocale): void
    {
        Cache::put(static::textCacheKey($text, $targetLocale), $text, now()->addHours(6));
    }

    /**
     * Is background translation of user content switched on?
     */
    public static function autoTranslateEnabled(): bool
    {
        return (bool) Setting::get('auto_translate_content', true);
    }

    /**
     * Read already-stored translations for a batch, without ever contacting the
     * translation provider.
     *
     * This is the request-path counterpart to translateBatch(): the provider is
     * throttled to one call every 1.2s with 5/10/20/40s retry backoff, so
     * translating inline can hold a PHP-FPM worker for a minute or more. HTTP
     * handlers call this and queue TranslateModelJob for whatever is missing.
     *
     * Returns ['items' => <items with any known translations applied>,
     *          'missing' => <ids that still need at least one field>].
     */
    public function batchFromCache(string $modelClass, array $items, array $fieldNames, string $targetLocale): array
    {
        if ($targetLocale === static::getDefaultLocale()) {
            return ['items' => $items, 'missing' => []];
        }

        $existing = Translation::where('translatable_type', $modelClass)
            ->whereIn('translatable_id', array_column($items, 'id'))
            ->where('locale', $targetLocale)
            ->whereIn('field', $fieldNames)
            ->get()
            ->groupBy('translatable_id');

        $result = [];
        $missing = [];

        foreach ($items as $item) {
            $translated = $item;
            $incomplete = false;

            foreach ($fieldNames as $field) {
                if (empty($item[$field])) {
                    continue;
                }

                $row = $existing->get($item['id'])?->firstWhere('field', $field);

                if (!$row) {
                    $incomplete = true;

                    continue;
                }

                $translated[$field] = $row->value;

                if ($field === 'title' && $row->translated_slug) {
                    $translated['translated_slug'] = $row->translated_slug;
                }
            }

            if ($incomplete) {
                $missing[] = $item['id'];
            }

            $result[] = $translated;
        }

        return ['items' => $result, 'missing' => $missing];
    }

    /**
     * Single-model counterpart to batchFromCache(). Returns the stored
     * translations, falling back to the source value for any missing field.
     */
    public function modelFromCache(string $modelClass, int $modelId, array $fields, string $targetLocale): array
    {
        $item = ['id' => $modelId] + $fields;
        $result = $this->batchFromCache($modelClass, [$item], array_keys($fields), $targetLocale);

        $translated = $result['items'][0] ?? $item;
        unset($translated['id']);

        return ['fields' => $translated, 'complete' => empty($result['missing'])];
    }

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
                    // Translate on-the-fly and cache. A failed attempt is not
                    // persisted — a row holding source text would look complete
                    // forever and never be retried.
                    $translatedValue = $this->tryTranslateText($item[$field], $targetLocale, $defaultLocale);

                    if ($translatedValue === null) {
                        continue;
                    }

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
                } catch (Exception $e) {
                    $result[$key] = $value;
                }
            }
            return $result;
        });
    }
}

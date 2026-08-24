<?php

namespace App\Services\Translation;

use App\Models\Setting;
use App\Services\Translation\Contracts\TranslationProvider;
use Throwable;

abstract class AbstractTranslationProvider implements TranslationProvider
{
    public function __construct(protected array $config = []) {}

    /**
     * Naive batch: one call per string.
     *
     * Engines with a real batch endpoint override this. Keeping a working
     * default means callers can always use translateBatch() without caring
     * whether the engine supports it.
     */
    public function translateBatch(array $texts, string $targetLocale, string $sourceLocale, string $format = 'text'): array
    {
        $result = [];

        foreach ($texts as $key => $text) {
            $result[$key] = $this->translate($text, $targetLocale, $sourceLocale, $format);
        }

        return $result;
    }

    /**
     * App locale -> engine code, most specific wins:
     * admin overrides, then the driver's config map, then the code unchanged.
     */
    public function providerCode(string $appLocale): string
    {
        $overrides = $this->adminOverrides();

        if (isset($overrides[$appLocale]) && is_string($overrides[$appLocale]) && $overrides[$appLocale] !== '') {
            return $overrides[$appLocale];
        }

        return $this->config['locale_map'][$appLocale] ?? $appLocale;
    }

    public function supportedCodes(): array
    {
        return [];
    }

    public function maxCharsPerRequest(): int
    {
        return (int) ($this->config['max_chars'] ?? 0);
    }

    public function maxBatchSize(): int
    {
        return max(1, (int) ($this->config['max_items'] ?? 1));
    }

    /**
     * Split a set of texts into request-sized chunks.
     *
     * Splits on cumulative character count as well as item count — a handful of
     * long video descriptions can blow a provider's per-request character limit
     * long before the item limit is reached.
     *
     * @param  array<int|string, string>  $texts
     * @return array<int, array<int|string, string>>
     */
    public function chunkTexts(array $texts): array
    {
        $maxItems = $this->maxBatchSize();
        $maxChars = $this->maxCharsPerRequest();

        $chunks = [];
        $current = [];
        $currentChars = 0;

        foreach ($texts as $key => $text) {
            $length = mb_strlen($text);

            $wouldOverflow = $current !== [] && (
                count($current) >= $maxItems
                || ($maxChars > 0 && $currentChars + $length > $maxChars)
            );

            if ($wouldOverflow) {
                $chunks[] = $current;
                $current = [];
                $currentChars = 0;
            }

            $current[$key] = $text;
            $currentChars += $length;
        }

        if ($current !== []) {
            $chunks[] = $current;
        }

        return $chunks;
    }

    /**
     * Per-driver locale overrides set by an admin, keyed by driver.
     */
    protected function adminOverrides(): array
    {
        try {
            $raw = Setting::get('translation_locale_overrides');
        } catch (Throwable) {
            return [];
        }

        if (is_string($raw)) {
            $raw = json_decode($raw, true);
        }

        if (! is_array($raw)) {
            return [];
        }

        $forDriver = $raw[$this->key()] ?? null;

        return is_array($forDriver) ? $forDriver : [];
    }
}

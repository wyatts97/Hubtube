<?php

namespace App\Services\Translation\Providers;

use App\Models\Setting;
use App\Services\Translation\AbstractTranslationProvider;
use App\Services\Translation\TranslationProviderException;
use App\Services\TranslationService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * LibreTranslate — self-hosted, or the paid libretranslate.com.
 *
 * Unlike the Google scraper this has a real batch endpoint: POST /translate
 * accepts `q` as an array and returns `translatedText` as an array in the same
 * order, which collapses hundreds of requests per locale into a few dozen.
 *
 * Language codes come from Argos Translate and are NOT always ISO 639-1. Most
 * notably Brazilian Portuguese is `pb` while `pt` is European Portuguese, so
 * the app locale `pt` is mapped to `pb` by default (see config/translation.php)
 * — this app's hreflang already advertises pt-BR.
 */
class LibreTranslateProvider extends AbstractTranslationProvider
{
    public function key(): string
    {
        return 'libretranslate';
    }

    public function label(): string
    {
        return 'LibreTranslate (self-hosted or paid)';
    }

    public function translate(string $text, string $targetLocale, string $sourceLocale, string $format = 'text'): string
    {
        $result = $this->request($text, $targetLocale, $sourceLocale, $format);

        if (! is_string($result)) {
            throw TranslationProviderException::failed('LibreTranslate returned an unexpected response shape.');
        }

        return $result;
    }

    public function translateBatch(array $texts, string $targetLocale, string $sourceLocale, string $format = 'text'): array
    {
        if ($texts === []) {
            return [];
        }

        $keys = array_keys($texts);
        $result = $this->request(array_values($texts), $targetLocale, $sourceLocale, $format);

        if (! is_array($result)) {
            throw TranslationProviderException::failed('LibreTranslate returned a single value for a batch request.');
        }

        // A length mismatch would silently pair each translation with the wrong
        // record — video A's title landing on video B. Refuse rather than guess.
        if (count($result) !== count($keys)) {
            throw TranslationProviderException::failed(sprintf(
                'LibreTranslate returned %d translations for %d inputs.',
                count($result),
                count($keys),
            ));
        }

        return array_combine($keys, $result);
    }

    public function supportedCodes(): array
    {
        try {
            $response = Http::timeout(10)->acceptJson()->get($this->endpoint().'/languages');
        } catch (Throwable) {
            return [];
        }

        if (! $response->successful()) {
            return [];
        }

        return array_values(array_filter(array_map(
            fn ($entry) => is_array($entry) ? ($entry['code'] ?? null) : null,
            $response->json() ?: [],
        )));
    }

    public function testConnection(): array
    {
        $endpoint = $this->endpoint();

        if ($endpoint === '') {
            return ['success' => false, 'message' => 'No LibreTranslate endpoint configured.'];
        }

        try {
            $response = Http::timeout(10)->acceptJson()->get($endpoint.'/languages');
        } catch (ConnectionException $e) {
            return ['success' => false, 'message' => "Could not reach {$endpoint}: {$e->getMessage()}"];
        } catch (Throwable $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }

        if (! $response->successful()) {
            return [
                'success' => false,
                'message' => "{$endpoint} responded with HTTP {$response->status()}.",
            ];
        }

        $available = array_values(array_filter(array_map(
            fn ($entry) => is_array($entry) ? ($entry['code'] ?? null) : null,
            $response->json() ?: [],
        )));

        // A bare "connected" is close to useless here: LT_LOAD_ONLY means an
        // instance can be perfectly healthy yet have no model for half the
        // enabled languages. Name the gaps.
        $missing = [];

        foreach (TranslationService::getEnabledLocales() as $locale) {
            $code = $this->providerCode($locale);

            if (! in_array($code, $available, true)) {
                $name = TranslationService::LANGUAGES[$locale]['name'] ?? $locale;
                $missing[] = "{$name} ({$locale} → {$code})";
            }
        }

        $summary = 'Connected. '.count($available).' language(s) installed.';

        if ($missing !== []) {
            return [
                'success' => false,
                'message' => $summary.' No model for: '.implode(', ', $missing).'.',
            ];
        }

        return ['success' => true, 'message' => $summary.' All enabled languages are covered.'];
    }

    /**
     * One POST /translate call. Returns a string or an array, mirroring `q`.
     */
    protected function request(string|array $q, string $targetLocale, string $sourceLocale, string $format): string|array
    {
        $endpoint = $this->endpoint();

        if ($endpoint === '') {
            throw TranslationProviderException::unavailable('No LibreTranslate endpoint configured.');
        }

        $payload = [
            'q' => $q,
            'source' => $this->providerCode($sourceLocale),
            'target' => $this->providerCode($targetLocale),
            'format' => $format,
        ];

        // LibreTranslate reads the key from the JSON body (or query), never a header.
        if (($key = $this->apiKey()) !== '') {
            $payload['api_key'] = $key;
        }

        try {
            $response = Http::timeout((int) ($this->config['timeout'] ?? 30))
                ->acceptJson()
                ->asJson()
                ->post($endpoint.'/translate', $payload);
        } catch (ConnectionException $e) {
            throw TranslationProviderException::unavailable($e->getMessage(), null, $e);
        } catch (Throwable $e) {
            throw TranslationProviderException::failed($e->getMessage(), null, $e);
        }

        if (! $response->successful()) {
            throw $this->classify($response);
        }

        $translated = $response->json('translatedText');

        if ($translated === null) {
            throw TranslationProviderException::failed('LibreTranslate response had no translatedText.');
        }

        return $translated;
    }

    protected function classify(Response $response): TranslationProviderException
    {
        $status = $response->status();
        $message = $response->json('error') ?: $response->body();
        $message = is_string($message) ? trim($message) : 'Unknown error';

        return match (true) {
            $status === 429 => TranslationProviderException::rateLimited($message, $status),
            in_array($status, [401, 403], true) => TranslationProviderException::unauthorized(
                "LibreTranslate rejected the API key: {$message}", $status
            ),
            $status === 400 && str_contains(strtolower($message), 'language') => TranslationProviderException::unsupportedLanguage($message, $status),
            $status >= 500 => TranslationProviderException::unavailable($message, $status),
            default => TranslationProviderException::failed($message, $status),
        };
    }

    protected function endpoint(): string
    {
        $endpoint = (string) ($this->config['endpoint'] ?? '');

        if ($endpoint === '') {
            try {
                $endpoint = (string) Setting::get('libretranslate_endpoint', '');
            } catch (Throwable) {
                return '';
            }
        }

        return rtrim(trim($endpoint), '/');
    }

    /**
     * Read the key through getDecrypted() only.
     *
     * Setting::get() would hand back ciphertext: getAll() caches raw column
     * values for 24h and never decrypts.
     */
    protected function apiKey(): string
    {
        if (! empty($this->config['api_key'])) {
            return (string) $this->config['api_key'];
        }

        try {
            return (string) Setting::getDecrypted('libretranslate_api_key', '');
        } catch (Throwable) {
            return '';
        }
    }
}

<?php

namespace App\Services\Translation\Providers;

use App\Services\Translation\AbstractTranslationProvider;
use App\Services\Translation\TranslationProviderException;
use App\Services\TranslationService;
use Stichoza\GoogleTranslate\GoogleTranslate;
use Throwable;

/**
 * The legacy engine: an unofficial scraper of Google's free endpoint.
 *
 * No API key, no SLA, no documented rate limit — it simply starts returning 429
 * once it decides you have asked too often, and has previously blocked this
 * site's whole server for over a day. Kept as a selectable driver so existing
 * installs keep working, but it is never selected automatically.
 */
class GoogleTranslateProvider extends AbstractTranslationProvider
{
    protected ?GoogleTranslate $translator = null;

    public function key(): string
    {
        return 'google';
    }

    public function label(): string
    {
        return 'Google Translate (free, unofficial)';
    }

    public function translate(string $text, string $targetLocale, string $sourceLocale, string $format = 'text'): string
    {
        $translator = $this->translator();
        $translator->setSource($this->providerCode($sourceLocale));
        $translator->setTarget($this->providerCode($targetLocale));

        try {
            return $translator->translate($text) ?? throw TranslationProviderException::failed(
                'Google Translate returned no result.'
            );
        } catch (TranslationProviderException $e) {
            throw $e;
        } catch (Throwable $e) {
            throw $this->classify($e);
        }
    }

    public function supportedCodes(): array
    {
        return array_keys(TranslationService::LANGUAGES);
    }

    public function testConnection(): array
    {
        try {
            $result = $this->translate('Hello', 'es', 'en');

            return [
                'success' => true,
                'message' => "Connected. \"Hello\" translated to \"{$result}\".",
            ];
        } catch (Throwable $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * The scraper only ever signals trouble through the exception message.
     */
    protected function classify(Throwable $e): TranslationProviderException
    {
        $message = $e->getMessage();

        if (str_contains($message, '429')) {
            return TranslationProviderException::rateLimited($message, 429, $e);
        }

        if (str_contains($message, 'cURL error') || str_contains($message, 'Connection')) {
            return TranslationProviderException::unavailable($message, null, $e);
        }

        return TranslationProviderException::failed($message, null, $e);
    }

    protected function translator(): GoogleTranslate
    {
        return $this->translator ??= new GoogleTranslate;
    }
}

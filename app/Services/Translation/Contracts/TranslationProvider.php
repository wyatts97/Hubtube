<?php

namespace App\Services\Translation\Contracts;

use App\Services\Translation\TranslationProviderException;

/**
 * A translation engine.
 *
 * Implementations receive APP locale codes ('pt') and are responsible for
 * mapping them to whatever the engine expects ('pb' for Brazilian Portuguese on
 * LibreTranslate). Provider codes must never escape the implementation — the
 * rest of the app, including URLs, `translations.locale` and the i18n
 * filenames, only ever deals in app locales.
 */
interface TranslationProvider
{
    /** Stable identifier stored in settings and in `translations.provider`. */
    public function key(): string;

    /** Human label for the admin dropdown. */
    public function label(): string;

    /**
     * @throws TranslationProviderException
     */
    public function translate(string $text, string $targetLocale, string $sourceLocale, string $format = 'text'): string;

    /**
     * Translate several strings in one call where the engine supports it.
     *
     * All-or-nothing: returns a map with exactly the input's keys, in order, or
     * throws. Partial-failure recovery is the caller's job — it re-splits a
     * failed chunk into singles rather than guessing which entries survived.
     *
     * @param  array<int|string, string>  $texts
     * @return array<int|string, string>
     *
     * @throws TranslationProviderException
     */
    public function translateBatch(array $texts, string $targetLocale, string $sourceLocale, string $format = 'text'): array;

    /** Map an app locale onto this engine's code. */
    public function providerCode(string $appLocale): string;

    /** Provider codes this engine actually has models for; empty when unknown. */
    public function supportedCodes(): array;

    /**
     * Connectivity/credentials probe.
     *
     * @return array{success: bool, message: string}
     */
    public function testConnection(): array;

    /** Character budget for a single request; 0 means unbounded. */
    public function maxCharsPerRequest(): int;

    /** Strings per batch call; 1 means the engine has no batch endpoint. */
    public function maxBatchSize(): int;
}

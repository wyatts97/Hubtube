<?php

namespace Tests\Support;

use App\Services\Translation\AbstractTranslationProvider;
use App\Services\Translation\TranslationProviderException;

/**
 * Deterministic in-memory provider.
 *
 * Records every call so tests can assert the request path never reaches a
 * provider, and can be primed to throw on the Nth call to exercise the
 * retry / re-split / fatal-abort paths without a network.
 */
class FakeTranslationProvider extends AbstractTranslationProvider
{
    /** @var array<int, array{texts: array, target: string, source: string, format: string}> */
    public static array $calls = [];

    /** @var array<int, TranslationProviderException> keyed by 0-based call index */
    public static array $failures = [];

    public static function reset(): void
    {
        static::$calls = [];
        static::$failures = [];
    }

    public static function callCount(): int
    {
        return count(static::$calls);
    }

    /** Number of individual strings sent, across all calls. */
    public static function stringCount(): int
    {
        return array_sum(array_map(fn ($call) => count($call['texts']), static::$calls));
    }

    public static function failOnCall(int $index, TranslationProviderException $e): void
    {
        static::$failures[$index] = $e;
    }

    public function key(): string
    {
        return 'fake';
    }

    public function label(): string
    {
        return 'Fake Provider (testing)';
    }

    public function translate(string $text, string $targetLocale, string $sourceLocale, string $format = 'text'): string
    {
        return $this->record([$text], $targetLocale, $sourceLocale, $format)[0];
    }

    public function translateBatch(array $texts, string $targetLocale, string $sourceLocale, string $format = 'text'): array
    {
        $keys = array_keys($texts);
        $values = $this->record(array_values($texts), $targetLocale, $sourceLocale, $format);

        return array_combine($keys, $values);
    }

    public function testConnection(): array
    {
        return ['success' => true, 'message' => 'Fake provider is always reachable.'];
    }

    protected function record(array $texts, string $target, string $source, string $format): array
    {
        $index = count(static::$calls);
        static::$calls[] = compact('texts', 'target', 'source', 'format');

        if (isset(static::$failures[$index])) {
            throw static::$failures[$index];
        }

        return array_map(fn (string $text) => "[{$target}] {$text}", $texts);
    }
}

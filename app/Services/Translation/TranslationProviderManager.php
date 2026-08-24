<?php

namespace App\Services\Translation;

use App\Models\Setting;
use App\Services\Translation\Contracts\TranslationProvider;
use InvalidArgumentException;
use Throwable;

/**
 * Resolves the configured translation engine.
 *
 * Deliberately has no fallback: if the configured driver is missing or
 * misconfigured this throws rather than quietly returning Google. Silent
 * failover is what turned a LibreTranslate outage into a site-wide Google ban
 * once before.
 */
class TranslationProviderManager
{
    /** @var array<string, TranslationProvider> */
    protected array $resolved = [];

    public function driver(?string $key = null): TranslationProvider
    {
        $key ??= $this->configuredKey();

        return $this->resolved[$key] ??= $this->make($key);
    }

    public function default(): TranslationProvider
    {
        return $this->driver();
    }

    /**
     * @return array<string, string> driver key => label
     */
    public function available(): array
    {
        $labels = [];

        foreach (array_keys(config('translation.drivers', [])) as $key) {
            try {
                $labels[$key] = $this->driver($key)->label();
            } catch (Throwable) {
                // A driver that cannot be constructed is simply not offered.
            }
        }

        return $labels;
    }

    /**
     * Drop memoised instances — call after settings change so the next
     * resolution picks up a new endpoint or key.
     */
    public function forget(): void
    {
        $this->resolved = [];
    }

    public function configuredKey(): string
    {
        try {
            $key = (string) Setting::get('translation_provider', '');
        } catch (Throwable) {
            $key = '';
        }

        return $key !== '' ? $key : (string) config('translation.default', 'google');
    }

    protected function make(string $key): TranslationProvider
    {
        $driver = config("translation.drivers.{$key}");

        if (! is_array($driver) || ! isset($driver['class'])) {
            throw new InvalidArgumentException("Unknown translation driver [{$key}].");
        }

        $class = $driver['class'];

        if (! class_exists($class) || ! is_subclass_of($class, TranslationProvider::class)) {
            throw new InvalidArgumentException("Translation driver [{$key}] does not implement TranslationProvider.");
        }

        return new $class($this->configFor($key, $driver));
    }

    /**
     * Flatten the per-driver slices of config/translation.php into one array.
     */
    protected function configFor(string $key, array $driver): array
    {
        return array_merge(
            $driver,
            config("translation.batch.{$key}", []),
            config("translation.throttle.{$key}", []),
            ['locale_map' => config("translation.locale_map.{$key}", [])],
        );
    }
}

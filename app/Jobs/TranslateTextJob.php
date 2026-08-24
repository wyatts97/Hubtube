<?php

namespace App\Jobs;

use App\Services\TranslationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Translates a free-standing string — a tag, mostly — off the request path.
 *
 * Tags live in a JSON column rather than their own table, so they have no
 * translations-table row to hang off; the result is cached by content hash
 * instead. Without this, every view of a tagged video in a non-default locale
 * made one uncached, throttled provider call per tag, forever.
 */
class TranslateTextJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 120;

    public array $backoff = [30, 120];

    public int $uniqueFor = 900;

    public function __construct(
        public string $text,
        public string $locale,
    ) {}

    public function uniqueId(): string
    {
        return TranslationService::textCacheKey($this->text, $this->locale);
    }

    public function handle(TranslationService $translations): void
    {
        if (! TranslationService::autoTranslateEnabled()) {
            return;
        }

        if (! TranslationService::isValidLocale($this->locale)
            || $this->locale === TranslationService::getDefaultLocale()) {
            return;
        }

        $translated = $translations->tryTranslateText($this->text, $this->locale);

        if ($translated !== null) {
            $translations->rememberText($this->text, $this->locale, $translated);

            return;
        }

        // Negative cache. Without this the page finds nothing cached on the very
        // next view, queues this job again, fails again, and repeats forever —
        // which hammers the provider hard enough to get the whole site rate
        // limited. Caching the source briefly makes the page render (in the
        // source language) and stay quiet until the TTL lets it retry.
        $translations->rememberFailedText($this->text, $this->locale);
    }

    public function failed(Throwable $e): void
    {
        Log::warning('Text translation job failed', [
            'locale' => $this->locale,
            'error' => $e->getMessage(),
        ]);
    }
}

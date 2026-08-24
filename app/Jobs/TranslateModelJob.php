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
 * Translates one model's fields into one locale, off the request path.
 *
 * TranslationService talks to a throttled provider — one call every 1.2s, with
 * 5/10/20/40s backoff on rate limits — so a cold batch of 50 videos took over a
 * minute of synchronous PHP. HTTP handlers now serve whatever translations
 * already exist and queue this for the rest.
 *
 * Field *names* are stored rather than values so the job re-reads the model at
 * run time; a title edited between dispatch and execution translates the new
 * text instead of a stale copy.
 */
class TranslateModelJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /** Generous: the provider's own retry backoff can consume most of this. */
    public int $timeout = 300;

    public array $backoff = [30, 120];

    /**
     * Stop the same model+locale being queued repeatedly while one attempt is
     * still pending — every page view of an untranslated listing would
     * otherwise enqueue a duplicate.
     */
    public int $uniqueFor = 900;

    public function __construct(
        public string $modelClass,
        public int $modelId,
        public array $fields,
        public string $locale,
    ) {
        // Its own queue, matching video-processing and ad-processing. Provider
        // calls are slow and, with several locales enabled, bursty — on the
        // shared default queue (60s worker timeout) they risk being killed and
        // starve everything else waiting behind them.
        //
        // Set here rather than as a $queue property: the Queueable trait
        // declares $queue with no default, and PHP rejects a redeclaration
        // whose default differs.
        $this->onQueue('translations');
    }

    public function uniqueId(): string
    {
        return "{$this->modelClass}:{$this->modelId}:{$this->locale}";
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

        $model = $this->modelClass::find($this->modelId);

        if (! $model) {
            return;
        }

        $values = [];

        foreach ($this->fields as $field) {
            $value = $model->{$field} ?? null;

            if (! empty($value)) {
                $values[$field] = $value;
            }
        }

        if (empty($values)) {
            return;
        }

        $translations->translateModel($this->modelClass, $this->modelId, $values, $this->locale);
    }

    public function failed(Throwable $e): void
    {
        Log::warning('Translation job failed', [
            'model' => $this->modelClass,
            'id' => $this->modelId,
            'locale' => $this->locale,
            'error' => $e->getMessage(),
        ]);
    }
}

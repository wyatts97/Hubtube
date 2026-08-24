<?php

namespace App\Console\Commands;

use App\Models\Setting;
use App\Services\Translation\Sections\CategorySection;
use App\Services\Translation\Sections\PageSection;
use App\Services\Translation\Sections\TagSection;
use App\Services\Translation\Sections\TranslationSection;
use App\Services\Translation\Sections\VideoSection;
use App\Services\Translation\TranslationProviderException;
use App\Services\Translation\TranslationProviderManager;
use App\Services\TranslationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * The scheduled translation sweep.
 *
 * Since on-view dispatching was removed, this is the only thing that translates
 * content. It is deliberately idempotent — every section selects only rows with
 * no translation for the target locale — so a run that dies halfway simply
 * resumes on the next tick, and a run immediately after a successful one makes
 * zero provider calls.
 */
class RunScheduledTranslations extends Command
{
    protected $signature = 'translations:run
                            {--locale=* : Limit to specific app locales (default: every enabled non-default locale)}
                            {--section=* : videos|categories|pages|tags|ui (default: all)}
                            {--limit= : Max items per section per locale}
                            {--provider= : Override the configured driver for this run}
                            {--dry-run : Report what would be translated without calling the provider}';

    protected $description = 'Translate outstanding content and UI strings using the configured provider.';

    protected array $summary = [];

    public function handle(TranslationService $translations, TranslationProviderManager $providers): int
    {
        if (! TranslationService::autoTranslateEnabled()) {
            $this->warn('Auto-translation is disabled in Admin → Languages. Nothing to do.');

            return self::SUCCESS;
        }

        if ($override = $this->option('provider')) {
            Setting::set('translation_provider', $override, 'translation', 'string');
            $providers->forget();
        }

        try {
            $provider = $providers->default();
        } catch (Throwable $e) {
            $this->error("Translation provider unavailable: {$e->getMessage()}");

            return self::FAILURE;
        }

        $default = TranslationService::getDefaultLocale();
        $locales = $this->option('locale') ?: array_values(array_diff(TranslationService::getEnabledLocales(), [$default]));

        if ($locales === []) {
            $this->info('No non-default locales are enabled. Nothing to do.');

            return self::SUCCESS;
        }

        $limit = (int) ($this->option('limit') ?: config('translation.schedule.default_limit', 500));
        $dryRun = (bool) $this->option('dry-run');
        $started = now();

        // Recorded up front as well as at the end so the catch-up check cannot
        // re-trigger a long-running sweep that is still in progress.
        if (! $dryRun) {
            Setting::set('translation_last_run_at', $started->toDateTimeString(), 'translation', 'string');
        }

        $this->info("Provider: {$provider->label()}");
        $this->info('Locales: '.implode(', ', $locales));
        $this->newLine();

        $exitReason = 'completed';

        foreach ($locales as $locale) {
            $this->line("<info>{$locale}</info>");

            foreach ($this->sections() as $section) {
                if ($section === 'ui') {
                    $this->runUiSection($locale, $dryRun);

                    continue;
                }

                try {
                    $this->runSection($section, $locale, $limit, $dryRun, $translations);
                } catch (TranslationProviderException $e) {
                    $this->error("  {$e->getMessage()}");

                    if ($e->reason === TranslationProviderException::UNAUTHORIZED) {
                        $this->error('  Credentials rejected — aborting the entire run.');
                        $this->finish($started, 'unauthorized', $provider->key(), $dryRun);

                        return self::FAILURE;
                    }

                    $exitReason = 'aborted_locale';

                    break; // next locale
                }
            }

            $this->newLine();
        }

        $this->finish($started, $exitReason, $provider->key(), $dryRun);
        $this->renderSummary();

        return self::SUCCESS;
    }

    /**
     * @throws TranslationProviderException on a fatal provider failure
     */
    protected function runSection(string $key, string $locale, int $limit, bool $dryRun, TranslationService $translations): void
    {
        $section = $this->section($key);
        $pending = $section->pending($locale, $limit);

        if ($pending === []) {
            $this->line("  {$key}: up to date");

            return;
        }

        if ($dryRun) {
            $this->line("  {$key}: <comment>".count($pending).' item(s) would be translated</comment>');
            $this->record($locale, $key, 'would_translate', count($pending));

            return;
        }

        $provider = app(TranslationProviderManager::class)->default();
        $translated = 0;
        $failed = 0;

        // Group by format so an HTML chunk is never mixed with plain text.
        foreach ($this->byFormat($pending) as $format => $rows) {
            $texts = [];
            foreach ($rows as $index => $row) {
                $texts[$index] = $row['text'];
            }

            foreach ($provider->chunkTexts($texts) as $chunk) {
                $results = $translations->tryTranslateBatch($chunk, $locale);

                // A whole failed chunk is re-tried one string at a time so a
                // single problem string cannot poison its 24 neighbours.
                if ($this->allFailed($results)) {
                    $results = [];
                    foreach ($chunk as $index => $text) {
                        $results[$index] = $translations->tryTranslateText($text, $locale);
                    }
                }

                $store = [];
                foreach ($results as $index => $value) {
                    if ($value === null || $value === '') {
                        $failed++;

                        continue;
                    }

                    $store[] = $rows[$index] + ['translated' => $value];
                    $translated++;
                }

                if ($store !== []) {
                    $section->store($store, $locale);
                }
            }
        }

        $this->line("  {$key}: <info>{$translated}</info> translated".($failed ? ", <comment>{$failed}</comment> failed" : ''));
        $this->record($locale, $key, 'translated', $translated);
        $this->record($locale, $key, 'failed', $failed);
    }

    protected function runUiSection(string $locale, bool $dryRun): void
    {
        if ($dryRun) {
            $this->line('  ui: <comment>would sync interface strings</comment>');

            return;
        }

        Artisan::call('translations:generate', ['locale' => $locale], $this->getOutput());

        // The JSON files are read server-side, but the browser bundle still
        // needs rebuilding. Cron must not shell out to npm, so flag it for the
        // admin panel instead.
        Setting::set('translation_ui_rebuild_needed', true, 'translation', 'boolean');
    }

    /** @return array<string, array<int, array>> */
    protected function byFormat(array $pending): array
    {
        $grouped = [];

        foreach ($pending as $index => $row) {
            $grouped[$row['format']][$index] = $row;
        }

        return $grouped;
    }

    protected function allFailed(array $results): bool
    {
        foreach ($results as $value) {
            if ($value !== null) {
                return false;
            }
        }

        return $results !== [];
    }

    protected function sections(): array
    {
        $requested = $this->option('section');
        $all = ['videos', 'categories', 'pages', 'tags', 'ui'];

        return $requested ? array_values(array_intersect($all, $requested)) : $all;
    }

    protected function section(string $key): TranslationSection
    {
        return match ($key) {
            'videos' => app(VideoSection::class),
            'categories' => app(CategorySection::class),
            'pages' => app(PageSection::class),
            'tags' => app(TagSection::class),
            default => throw new \InvalidArgumentException("Unknown translation section [{$key}]."),
        };
    }

    protected function record(string $locale, string $section, string $metric, int $value): void
    {
        if ($value > 0) {
            $this->summary[$locale][$section][$metric] = $value;
        }
    }

    protected function finish($started, string $reason, string $provider, bool $dryRun): void
    {
        if ($dryRun) {
            return;
        }

        $payload = [
            'finished_at' => now()->toDateTimeString(),
            'duration_seconds' => now()->diffInSeconds($started),
            'provider' => $provider,
            'reason' => $reason,
            'locales' => $this->summary,
        ];

        Setting::set('translation_last_run_at', now()->toDateTimeString(), 'translation', 'string');
        Setting::set('translation_last_run_summary', $payload, 'translation', 'json');

        Log::info('translation.run', $payload);
    }

    protected function renderSummary(): void
    {
        if ($this->summary === []) {
            $this->info('Everything is already translated.');

            return;
        }

        foreach ($this->summary as $locale => $sections) {
            foreach ($sections as $section => $metrics) {
                $parts = [];
                foreach ($metrics as $metric => $value) {
                    $parts[] = "{$metric}={$value}";
                }
                $this->line("  {$locale}/{$section}: ".implode(' ', $parts));
            }
        }
    }
}

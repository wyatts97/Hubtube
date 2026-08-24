<?php

namespace App\Services\Translation\Sections;

use App\Models\Translation;
use App\Services\Translation\TranslationProviderManager;
use App\Services\TranslationService;
use Illuminate\Database\Eloquent\Builder;

/**
 * Shared implementation for sections backed by an Eloquent model whose
 * translations live in the `translations` table.
 */
abstract class ModelSection implements TranslationSection
{
    public function __construct(
        protected TranslationService $translations,
        protected TranslationProviderManager $providers,
    ) {}

    /** Fully-qualified model class stored in `translatable_type`. */
    abstract protected function modelClass(): string;

    /** Fields to translate, in priority order. */
    abstract protected function fields(): array;

    /** Base query restricted to content worth translating. */
    abstract protected function query(): Builder;

    public function pending(string $locale, int $limit): array
    {
        $modelClass = $this->modelClass();
        $rows = [];

        foreach ($this->fields() as $field) {
            if (count($rows) >= $limit) {
                break;
            }

            $models = $this->query()
                ->whereNotExists(function ($sub) use ($modelClass, $field, $locale) {
                    $sub->selectRaw('1')
                        ->from('translations')
                        ->whereColumn('translations.translatable_id', $this->query()->getModel()->getTable().'.id')
                        ->where('translations.translatable_type', $modelClass)
                        ->where('translations.field', $field)
                        ->where('translations.locale', $locale);
                })
                ->latest('id')
                ->limit($limit - count($rows))
                ->get();

            foreach ($models as $model) {
                $text = (string) ($model->{$field} ?? '');

                if (trim($text) === '') {
                    continue;
                }

                $rows[] = [
                    'ref' => $model->getKey(),
                    'field' => $field,
                    'text' => $text,
                    'format' => $this->formatFor($field),
                ];
            }
        }

        return $rows;
    }

    public function store(array $rows, string $locale): void
    {
        $modelClass = $this->modelClass();
        $provider = $this->providers->default()->key();
        $source = TranslationService::getDefaultLocale();

        foreach ($rows as $row) {
            $data = [
                'value' => $row['translated'],
                'provider' => $provider,
                'source_locale' => $source,
            ];

            // Titles drive the per-locale URL slug used for hreflang.
            if ($row['field'] === $this->slugField()) {
                $data['translated_slug'] = $this->translations->generateUniqueTranslatedSlug(
                    $modelClass,
                    (int) $row['ref'],
                    $locale,
                    $row['translated'],
                    $row['text'],
                );
            }

            Translation::updateOrCreate([
                'translatable_type' => $modelClass,
                'translatable_id' => $row['ref'],
                'field' => $row['field'],
                'locale' => $locale,
            ], $data);
        }
    }

    /** Field whose translation should also produce a slug, if any. */
    protected function slugField(): ?string
    {
        return 'title';
    }

    /**
     * HTML fields are sent with format=html so markup survives — but only for
     * drivers that handle it. The Google scraper mangles tags.
     */
    protected function formatFor(string $field): string
    {
        $htmlFields = config('translation.html_fields.'.$this->modelClass(), []);

        if (! in_array($field, $htmlFields, true)) {
            return 'text';
        }

        $capable = config('translation.html_capable_drivers', []);

        return in_array($this->providers->configuredKey(), $capable, true) ? 'html' : 'text';
    }
}

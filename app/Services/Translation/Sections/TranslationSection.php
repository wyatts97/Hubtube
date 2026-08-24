<?php

namespace App\Services\Translation\Sections;

/**
 * One translatable slice of the site (videos, categories, pages, tags…).
 *
 * Keeping each slice behind this interface keeps translations:run short and
 * lets every slice be unit-tested without the command.
 */
interface TranslationSection
{
    /** Identifier used by the --section option and in run summaries. */
    public function key(): string;

    /**
     * Untranslated items for this locale, newest/most relevant first.
     *
     * Each row: ['ref' => scalar, 'field' => string, 'text' => string, 'format' => 'text'|'html'].
     * `ref` is opaque to the command and handed straight back to store().
     *
     * @return array<int, array{ref: mixed, field: string, text: string, format: string}>
     */
    public function pending(string $locale, int $limit): array;

    /**
     * Persist successful translations.
     *
     * @param  array<int, array{ref: mixed, field: string, text: string, translated: string}>  $rows
     */
    public function store(array $rows, string $locale): void;
}

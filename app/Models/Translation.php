<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Translation extends Model
{
    protected $fillable = [
        'translatable_type',
        'translatable_id',
        'field',
        'locale',
        'value',
        'translated_slug',
    ];

    public function translatable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Get a translated value for a model field.
     */
    public static function getTranslation(string $type, int $id, string $field, string $locale): ?string
    {
        return static::where('translatable_type', $type)
            ->where('translatable_id', $id)
            ->where('field', $field)
            ->where('locale', $locale)
            ->value('value');
    }

    /**
     * Get all translations for a model in a given locale.
     */
    public static function getTranslations(string $type, int $id, string $locale): array
    {
        return static::where('translatable_type', $type)
            ->where('translatable_id', $id)
            ->where('locale', $locale)
            ->pluck('value', 'field')
            ->toArray();
    }

    /**
     * Find a model by its translated slug.
     */
    public static function findBySlug(string $type, string $slug, string $locale): ?int
    {
        return static::where('translatable_type', $type)
            ->where('translated_slug', $slug)
            ->where('locale', $locale)
            ->where('field', 'title')
            ->value('translatable_id');
    }
}

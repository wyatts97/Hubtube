<?php

namespace App\Traits;

use App\Models\Translation;
use App\Services\TranslationService;

trait Translatable
{
    public static function bootTranslatable(): void
    {
        static::deleting(function ($model) {
            TranslationService::deleteTranslations(get_class($model), $model->id);
        });
    }

    /**
     * Relationship to all translation records for this model.
     */
    public function translations(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Translation::class, 'translatable_id')
            ->where('translatable_type', static::class);
    }

    /**
     * Get all translations for this model in a given locale.
     */
    public function getTranslations(string $locale): array
    {
        return Translation::getTranslations(get_class($this), $this->id, $locale);
    }

    /**
     * Get a translated attribute value.
     */
    public function translated(string $field, ?string $locale = null): string
    {
        $locale = $locale ?? app()->getLocale();
        $default = TranslationService::getDefaultLocale();

        if ($locale === $default) {
            return $this->{$field} ?? '';
        }

        $cached = Translation::getTranslation(get_class($this), $this->id, $field, $locale);
        return $cached ?? $this->{$field} ?? '';
    }
}

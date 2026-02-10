<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class TranslationOverride extends Model
{
    protected $fillable = [
        'locale',
        'original_text',
        'replacement_text',
        'case_sensitive',
        'is_active',
        'notes',
    ];

    protected $casts = [
        'case_sensitive' => 'boolean',
        'is_active' => 'boolean',
    ];

    /**
     * Get all active overrides for a locale, cached for performance.
     */
    public static function getOverrides(string $locale): array
    {
        return Cache::remember("translation_overrides:{$locale}", 3600, function () use ($locale) {
            return static::where('locale', $locale)
                ->where('is_active', true)
                ->get()
                ->toArray();
        });
    }

    /**
     * Get overrides that apply to ALL locales (locale = '*').
     */
    public static function getGlobalOverrides(): array
    {
        return Cache::remember('translation_overrides:*', 3600, function () {
            return static::where('locale', '*')
                ->where('is_active', true)
                ->get()
                ->toArray();
        });
    }

    /**
     * Apply overrides to a translated string.
     * Runs global overrides first, then locale-specific ones.
     */
    public static function applyOverrides(string $text, string $locale): string
    {
        $globalOverrides = static::getGlobalOverrides();
        $localeOverrides = static::getOverrides($locale);

        $allOverrides = array_merge($globalOverrides, $localeOverrides);

        foreach ($allOverrides as $override) {
            if ($override['case_sensitive']) {
                $text = str_replace($override['original_text'], $override['replacement_text'], $text);
            } else {
                $text = str_ireplace($override['original_text'], $override['replacement_text'], $text);
            }
        }

        return $text;
    }

    /**
     * Clear the override cache for a locale (or all).
     */
    public static function clearCache(?string $locale = null): void
    {
        if ($locale) {
            Cache::forget("translation_overrides:{$locale}");
        } else {
            // Clear all locale caches
            $locales = static::select('locale')->distinct()->pluck('locale');
            foreach ($locales as $loc) {
                Cache::forget("translation_overrides:{$loc}");
            }
        }
        Cache::forget('translation_overrides:*');
    }

    protected static function booted(): void
    {
        static::saved(fn () => static::clearCache());
        static::deleted(fn () => static::clearCache());
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class VideoAd extends Model
{
    protected $fillable = [
        'name',
        'type',
        'placement',
        'content',
        'file_path',
        'click_url',
        'weight',
        'is_active',
        'category_ids',
        'target_roles',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'category_ids' => 'array',
            'target_roles' => 'array',
            'weight' => 'integer',
        ];
    }

    // Scopes
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopePlacement(Builder $query, string $placement): Builder
    {
        return $query->where('placement', $placement);
    }

    public function scopeForCategory(Builder $query, ?int $categoryId): Builder
    {
        if (!$categoryId) {
            return $query;
        }

        return $query->where(function ($q) use ($categoryId) {
            $q->whereNull('category_ids')
              ->orWhereJsonContains('category_ids', $categoryId)
              ->orWhereJsonLength('category_ids', 0);
        });
    }

    public function scopeForRole(Builder $query, ?string $role): Builder
    {
        if (!$role) {
            return $query;
        }

        return $query->where(function ($q) use ($role) {
            $q->whereNull('target_roles')
              ->orWhereJsonContains('target_roles', $role)
              ->orWhereJsonLength('target_roles', 0);
        });
    }

    /**
     * Get a weighted-random ad from a collection of ads.
     */
    public static function pickWeightedRandom($ads)
    {
        if ($ads->isEmpty()) {
            return null;
        }

        $totalWeight = $ads->sum('weight');
        $random = mt_rand(1, max(1, $totalWeight));
        $cumulative = 0;

        foreach ($ads as $ad) {
            $cumulative += $ad->weight;
            if ($random <= $cumulative) {
                return $ad;
            }
        }

        return $ads->last();
    }

    /**
     * Get ads for a specific placement, filtered by category and user role.
     */
    public static function getAdsForPlacement(
        string $placement,
        ?int $categoryId = null,
        ?string $userRole = 'default',
        bool $shuffle = true
    ): array {
        $query = static::active()
            ->placement($placement)
            ->forCategory($categoryId)
            ->forRole($userRole);

        $ads = $query->get();

        if ($ads->isEmpty()) {
            return [];
        }

        if ($shuffle) {
            // Return one weighted-random ad
            $picked = static::pickWeightedRandom($ads);
            return $picked ? [self::formatAd($picked)] : [];
        }

        return $ads->map(fn ($ad) => self::formatAd($ad))->values()->toArray();
    }

    protected static function formatAd(self $ad): array
    {
        // For mp4 ads with a local file, serve from storage
        $content = $ad->content;
        if ($ad->type === 'mp4' && $ad->file_path) {
            $content = asset('storage/' . $ad->file_path);
        }

        return [
            'id' => $ad->id,
            'type' => $ad->type,
            'placement' => $ad->placement,
            'content' => $content,
            'click_url' => $ad->click_url,
            'name' => $ad->name,
        ];
    }
}

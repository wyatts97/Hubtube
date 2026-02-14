<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SponsoredCard extends Model
{
    protected $fillable = [
        'title',
        'thumbnail_url',
        'click_url',
        'description',
        'target_pages',
        'frequency',
        'weight',
        'is_active',
        'category_ids',
        'target_roles',
    ];

    protected $casts = [
        'target_pages' => 'array',
        'category_ids' => 'array',
        'target_roles' => 'array',
        'is_active' => 'boolean',
        'frequency' => 'integer',
        'weight' => 'integer',
    ];

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForPage($query, string $page)
    {
        return $query->where(function ($q) use ($page) {
            $q->whereNull('target_pages')
              ->orWhere('target_pages', '[]')
              ->orWhere('target_pages', 'null')
              ->orWhereJsonContains('target_pages', $page);
        });
    }

    public function scopeForRole($query, ?string $role)
    {
        return $query->where(function ($q) use ($role) {
            $q->whereNull('target_roles')
              ->orWhere('target_roles', '[]')
              ->orWhere('target_roles', 'null')
              ->orWhereJsonContains('target_roles', $role ?? 'guest');
        });
    }

    public function scopeForCategory($query, ?int $categoryId)
    {
        return $query->where(function ($q) use ($categoryId) {
            $q->whereNull('category_ids')
              ->orWhere('category_ids', '[]')
              ->orWhere('category_ids', 'null');
            if ($categoryId) {
                $q->orWhereJsonContains('category_ids', $categoryId);
            }
        });
    }

    /**
     * Resolve a storage-relative path to a public URL.
     */
    protected static function resolveThumbUrl(?string $path): string
    {
        if (!$path) return '';
        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://') || str_starts_with($path, '/')) {
            return $path;
        }
        return '/storage/' . $path;
    }

    /**
     * Get sponsored cards for a given page context, weighted randomly.
     */
    public static function getForPage(string $page, ?string $role = null, ?int $categoryId = null, int $limit = 5): array
    {
        $cards = static::active()
            ->forPage($page)
            ->forRole($role)
            ->forCategory($categoryId)
            ->get();

        if ($cards->isEmpty()) {
            return [];
        }

        // Weighted random selection
        $selected = [];
        $pool = $cards->toArray();

        for ($i = 0; $i < min($limit, count($pool)); $i++) {
            $totalWeight = array_sum(array_column($pool, 'weight'));
            $rand = mt_rand(1, max(1, $totalWeight));
            $cumulative = 0;

            foreach ($pool as $key => $card) {
                $cumulative += $card['weight'];
                if ($rand <= $cumulative) {
                    $card['thumbnail_url'] = static::resolveThumbUrl($card['thumbnail_url'] ?? '');
                    $selected[] = $card;
                    unset($pool[$key]);
                    $pool = array_values($pool);
                    break;
                }
            }
        }

        return $selected;
    }
}

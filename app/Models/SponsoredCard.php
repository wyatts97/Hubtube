<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SponsoredCard extends Model
{
    protected $fillable = [
        'external_id',
        'title',
        'thumbnail_url',
        'click_url',
        'description',
        'price',
        'sale_price',
        'ribbon_text',
        'preview_images',
        'studio',
        'duration',
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
        'preview_images' => 'array',
        'is_active' => 'boolean',
        'frequency' => 'integer',
        'weight' => 'integer',
        'price' => 'decimal:2',
        'sale_price' => 'decimal:2',
        'duration' => 'integer',
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
              ->orWhereRaw("JSON_LENGTH(target_pages) = 0")
              ->orWhereJsonContains('target_pages', $page);
        });
    }

    public function scopeForRole($query, ?string $role)
    {
        return $query->where(function ($q) use ($role) {
            $q->whereNull('target_roles')
              ->orWhere('target_roles', '[]')
              ->orWhere('target_roles', 'null')
              ->orWhereRaw("JSON_LENGTH(target_roles) = 0")
              ->orWhereJsonContains('target_roles', $role ?? 'guest');
        });
    }

    public function scopeForCategory($query, ?int $categoryId)
    {
        return $query->where(function ($q) use ($categoryId) {
            $q->whereNull('category_ids')
              ->orWhere('category_ids', '[]')
              ->orWhere('category_ids', 'null')
              ->orWhereRaw("JSON_LENGTH(category_ids) = 0");
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

        // Shuffle first so equal-weight cards are randomly ordered
        $pool = $cards->toArray();
        shuffle($pool);

        $selected = [];

        while (count($selected) < $limit && !empty($pool)) {
            $totalWeight = array_sum(array_map(fn($c) => max(0, (int) ($c['weight'] ?? 0)), $pool));

            if ($totalWeight <= 0) {
                // All weights are zero — just take the next (pool is already shuffled)
                $card = array_shift($pool);
            } else {
                $rand = mt_rand(1, $totalWeight);
                $cumulative = 0;
                $chosenKey = null;

                foreach ($pool as $key => $card) {
                    $w = max(0, (int) ($card['weight'] ?? 0));
                    if ($w === 0) continue;
                    $cumulative += $w;
                    if ($rand <= $cumulative) {
                        $chosenKey = $key;
                        break;
                    }
                }

                if ($chosenKey === null) break;

                $card = $pool[$chosenKey];
                array_splice($pool, $chosenKey, 1);
            }

            $card['thumbnail_url'] = static::resolveThumbUrl($card['thumbnail_url'] ?? '');

            if (!empty($card['preview_images']) && is_array($card['preview_images'])) {
                $card['preview_images'] = array_map(fn($img) => static::resolveThumbUrl($img), $card['preview_images']);
            }

            $card['formatted_price'] = $card['price'] ? '$' . number_format((float) $card['price'], 2) : null;
            $card['formatted_sale_price'] = $card['sale_price'] ? '$' . number_format((float) $card['sale_price'], 2) : null;
            $card['is_on_sale'] = $card['sale_price'] && $card['price'] && $card['sale_price'] < $card['price'];
            $card['discount_percent'] = $card['is_on_sale']
                ? (int) round((($card['price'] - $card['sale_price']) / $card['price']) * 100)
                : null;

            if (!empty($card['duration'])) {
                $minutes = floor($card['duration'] / 60);
                $seconds = $card['duration'] % 60;
                $card['formatted_duration'] = sprintf('%d:%02d', $minutes, $seconds);
            }

            $selected[] = $card;
        }

        return $selected;
    }

    /**
     * Get formatted price display.
     */
    public function getFormattedPriceAttribute(): ?string
    {
        if (!$this->price) {
            return null;
        }
        return '$' . number_format((float) $this->price, 2);
    }

    /**
     * Get formatted sale price display.
     */
    public function getFormattedSalePriceAttribute(): ?string
    {
        if (!$this->sale_price) {
            return null;
        }
        return '$' . number_format((float) $this->sale_price, 2);
    }

    /**
     * Check if item is on sale.
     */
    public function getIsOnSaleAttribute(): bool
    {
        return $this->sale_price && $this->price && $this->sale_price < $this->price;
    }

    /**
     * Get discount percentage.
     */
    public function getDiscountPercentAttribute(): ?int
    {
        if (!$this->is_on_sale) {
            return null;
        }
        return (int) round((($this->price - $this->sale_price) / $this->price) * 100);
    }

    /**
     * Get formatted duration.
     */
    public function getFormattedDurationAttribute(): ?string
    {
        if (!$this->duration) {
            return null;
        }
        $minutes = floor($this->duration / 60);
        $seconds = $this->duration % 60;
        return sprintf('%d:%02d', $minutes, $seconds);
    }

    /**
     * Resolve preview images to public URLs.
     */
    public function getResolvedPreviewImagesAttribute(): array
    {
        $images = $this->preview_images ?? [];
        return array_map(fn($img) => static::resolveThumbUrl($img), $images);
    }
}

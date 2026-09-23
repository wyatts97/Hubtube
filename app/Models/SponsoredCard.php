<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SponsoredCard extends Model
{
    use HasFactory;

    public const TYPE_IMAGE = 'image';
    public const TYPE_VIDEO = 'video';
    public const TYPE_HTML = 'html';

    public const TYPES = [
        self::TYPE_IMAGE => 'Image',
        self::TYPE_VIDEO => 'Video',
        self::TYPE_HTML => 'HTML code',
    ];

    /** Pages a card can target. Empty targeting means all of them. */
    public const PAGES = [
        'home' => 'Home',
        'trending' => 'Trending',
        'search' => 'Search Results',
        'category' => 'Category Pages',
        'browse' => 'Browse Videos',
        'tag' => 'Tag Pages',
        'playlist' => 'Playlist Pages',
        'gallery' => 'Galleries',
        'images' => 'Images',
        'history' => 'Watch History',
        'feed' => 'Subscription Feed',
    ];

    protected $fillable = [
        'external_id',
        'type',
        'title',
        'thumbnail_url',
        'click_url',
        'html_code',
        'mobile_html_code',
        'video_path',
        'video_url',
        'description',
        'price',
        'sale_price',
        'ribbon_text',
        'preview_images',
        'studio',
        'duration',
        'target_pages',
        'weight',
        'is_active',
        'category_ids',
        'target_roles',
    ];

    protected $attributes = [
        'type' => self::TYPE_IMAGE,
    ];

    protected $casts = [
        'target_pages' => 'array',
        'category_ids' => 'array',
        'target_roles' => 'array',
        'preview_images' => 'array',
        'is_active' => 'boolean',
        'weight' => 'integer',
        'price' => 'decimal:2',
        'sale_price' => 'decimal:2',
        'duration' => 'integer',
    ];

    protected static function booted(): void
    {
        // Empty targeting is stored as NULL so the "match everything" branch of
        // the scopes below is a plain IS NULL check.
        static::saving(function (SponsoredCard $card) {
            foreach (['target_pages', 'target_roles', 'category_ids', 'preview_images'] as $field) {
                $value = array_values(array_filter((array) ($card->{$field} ?? [])));
                $card->{$field} = $value ?: null;
            }

            if ($card->category_ids) {
                $card->category_ids = array_map('intval', $card->category_ids);
            }
        });
    }

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
              ->orWhereJsonLength('target_pages', 0)
              ->orWhereJsonContains('target_pages', $page);
        });
    }

    public function scopeForRole($query, ?string $role)
    {
        return $query->where(function ($q) use ($role) {
            $q->whereNull('target_roles')
              ->orWhere('target_roles', '[]')
              ->orWhere('target_roles', 'null')
              ->orWhereJsonLength('target_roles', 0)
              ->orWhereJsonContains('target_roles', $role ?? 'guest');
        });
    }

    public function scopeForCategory($query, ?int $categoryId)
    {
        return $query->where(function ($q) use ($categoryId) {
            $q->whereNull('category_ids')
              ->orWhere('category_ids', '[]')
              ->orWhere('category_ids', 'null')
              ->orWhereJsonLength('category_ids', 0);
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
     *
     * @param  array<int, string>|null  $types  Restrict to these creative types.
     */
    public static function getForPage(
        string $page,
        ?string $role = null,
        ?int $categoryId = null,
        int $limit = 5,
        ?array $types = null,
    ): array {
        $pool = static::active()
            ->forPage($page)
            ->forRole($role)
            ->forCategory($categoryId)
            ->when($types, fn ($q) => $q->whereIn('type', $types))
            ->get()
            ->shuffle() // equal-weight cards come out in random order
            ->values()
            ->all();

        $selected = [];

        while (count($selected) < $limit && !empty($pool)) {
            $totalWeight = array_sum(array_map(fn (self $c) => max(0, $c->weight), $pool));

            if ($totalWeight <= 0) {
                // All weights are zero — the pool is already shuffled.
                $selected[] = array_shift($pool)->toCardPayload();
                continue;
            }

            $rand = mt_rand(1, $totalWeight);
            $cumulative = 0;

            foreach ($pool as $key => $card) {
                $cumulative += max(0, $card->weight);
                if ($rand <= $cumulative) {
                    $selected[] = $card->toCardPayload();
                    array_splice($pool, $key, 1);
                    break;
                }
            }
        }

        return $selected;
    }

    /**
     * The fields the grid needs for this card's type, and nothing else.
     *
     * HTML cards never ship product fields and image cards never ship ad code,
     * so the page payload stays small.
     */
    public function toCardPayload(): array
    {
        $base = [
            'id' => $this->id,
            'type' => $this->type,
            'title' => $this->title,
        ];

        if ($this->type === self::TYPE_HTML) {
            return $base + [
                'html_code' => (string) $this->html_code,
                'mobile_html_code' => (string) ($this->mobile_html_code ?: $this->html_code),
            ];
        }

        $payload = $base + [
            'click_url' => $this->click_url,
            'thumbnail_url' => static::resolveThumbUrl($this->thumbnail_url),
            'description' => $this->description,
            'studio' => $this->studio,
            'formatted_price' => $this->formatted_price,
            'formatted_sale_price' => $this->formatted_sale_price,
            'is_on_sale' => $this->is_on_sale,
            'discount_percent' => $this->discount_percent,
            'formatted_duration' => $this->formatted_duration,
        ];

        if ($this->type === self::TYPE_VIDEO) {
            $payload['video_src'] = $this->video_path
                ? static::resolveThumbUrl($this->video_path)
                : (string) $this->video_url;
        } else {
            $payload['preview_images'] = $this->resolved_preview_images;
        }

        return $payload;
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

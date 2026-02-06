<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MenuItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'label',
        'type',
        'url',
        'target',
        'icon',
        'parent_id',
        'sort_order',
        'is_active',
        'is_mega',
        'mega_columns',
        'location',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_mega' => 'boolean',
            'mega_columns' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    public function children(): HasMany
    {
        return $this->hasMany(MenuItem::class, 'parent_id')->orderBy('sort_order');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(MenuItem::class, 'parent_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeTopLevel($query)
    {
        return $query->whereNull('parent_id');
    }

    public function scopeForLocation($query, string $location)
    {
        return $query->where('location', $location);
    }

    /**
     * Get the full menu tree for a location, with children eager-loaded.
     */
    public static function getMenuTree(string $location = 'header'): \Illuminate\Database\Eloquent\Collection
    {
        return static::query()
            ->with(['children' => fn ($q) => $q->active()->orderBy('sort_order')])
            ->active()
            ->topLevel()
            ->forLocation($location)
            ->orderBy('sort_order')
            ->get();
    }
}

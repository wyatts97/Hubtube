<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Plan extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'stripe_price_id',
        'amount_cents',
        'interval',
        'annual_discount_percent',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'amount_cents' => 'integer',
            'annual_discount_percent' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForInterval($query, string $interval)
    {
        return $query->where('interval', $interval);
    }

    public function getDisplayPriceAttribute(): string
    {
        return number_format($this->amount_cents / 100, 2);
    }

    public function getAnnualEquivalentMonthlyPriceAttribute(): ?float
    {
        if ($this->interval !== 'year') {
            return null;
        }

        return $this->amount_cents / 100 / 12;
    }
}

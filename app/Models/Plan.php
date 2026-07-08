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
        'ccbill_initial_price',
        'ccbill_initial_period',
        'ccbill_recurring_price',
        'ccbill_recurring_period',
        'ccbill_num_rebills',
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
            'ccbill_initial_price' => 'decimal:2',
            'ccbill_recurring_price' => 'decimal:2',
            'ccbill_initial_period' => 'integer',
            'ccbill_recurring_period' => 'integer',
            'ccbill_num_rebills' => 'integer',
        ];
    }

    /**
     * Whether this plan has the CCBill dynamic-pricing fields required to build
     * a FlexForms checkout URL.
     */
    public function hasCCBillPricing(): bool
    {
        return $this->ccbill_initial_price !== null && $this->ccbill_initial_period !== null;
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

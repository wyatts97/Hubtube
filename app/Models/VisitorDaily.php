<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class VisitorDaily extends Model
{
    protected $table = 'visitor_daily';

    protected $fillable = [
        'visitor_hash',
        'date',
        'visit_count',
    ];

    protected $casts = [
        'date' => 'date',
    ];

    public function scopeSinceDate($query, Carbon $date)
    {
        return $query->where('date', '>=', $date->toDateString());
    }

    public function scopeForRange($query, int $days)
    {
        return $query->where('date', '>=', now()->subDays($days - 1)->startOfDay()->toDateString());
    }
}

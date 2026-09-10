<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

/**
 * Read model for `ad_stats_daily`.
 *
 * Reads only. The hot write path uses a raw DB::table()->upsert() in
 * App\Services\AdStatsRecorder, mirroring how TrackVisitor writes
 * visitor_daily — Eloquent on a per-impression path buys nothing and costs
 * model hydration on every ad view.
 */
class AdStatDaily extends Model
{
    protected $table = 'ad_stats_daily';

    protected $fillable = [
        'date', 'source', 'ad_id', 'placement', 'country', 'device', 'impressions', 'clicks', 'completions',
    ];

    protected $casts = [
        'date' => 'date',
        'ad_id' => 'integer',
        'impressions' => 'integer',
        'clicks' => 'integer',
        'completions' => 'integer',
    ];

    public const SOURCE_VIDEO_AD = 'video_ad';
    public const SOURCE_SPONSORED_CARD = 'sponsored_card';
    public const SOURCE_NETWORK = 'network';

    /** Rows on or after the given date string (Y-m-d). */
    public function scopeSince(Builder $query, string $date): Builder
    {
        return $query->where('date', '>=', $date);
    }

    /** The trailing $days days, inclusive of today. */
    public function scopeForRange(Builder $query, int $days): Builder
    {
        return $query->where('date', '>=', now()->subDays(max(0, $days - 1))->toDateString());
    }
}

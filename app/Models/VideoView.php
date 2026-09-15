<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One counted view (see VideoViewRecorder). Raw rows are analytics detail and
 * are pruned; the running total lives on videos.views_count.
 */
class VideoView extends Model
{
    use HasFactory, MassPrunable;

    public function prunable(): Builder
    {
        return static::where('created_at', '<', now()->subDays((int) config('hubtube.video.view_log_retention_days', 90)));
    }

    protected $fillable = [
        'video_id',
        'user_id',
        'ip_address',
        'user_agent',
        'country',
        'referrer',
    ];

    public function video(): BelongsTo
    {
        return $this->belongsTo(Video::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class PointsTransaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'type',
        'points',
        'balance_after',
        'reference_type',
        'reference_id',
        'description',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'points' => 'integer',
            'balance_after' => 'integer',
            'metadata' => 'array',
        ];
    }

    const TYPE_VIDEO_UPLOAD = 'video_upload';
    const TYPE_IMAGE_UPLOAD = 'image_upload';
    const TYPE_COMMENT = 'comment';
    const TYPE_REDEMPTION = 'redemption';
    const TYPE_ADMIN_ADJUSTMENT = 'admin_adjustment';

    const EARN_TYPES = [
        self::TYPE_VIDEO_UPLOAD,
        self::TYPE_IMAGE_UPLOAD,
        self::TYPE_COMMENT,
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reference(): MorphTo
    {
        return $this->morphTo();
    }

    public function scopeCredits($query)
    {
        return $query->where('points', '>', 0);
    }

    public function scopeDebits($query)
    {
        return $query->where('points', '<', 0);
    }

    public function isCredit(): bool
    {
        return $this->points > 0;
    }
}

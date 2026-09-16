<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One rung of the resolution ladder videos are transcoded to.
 *
 * Only profiles strictly below a video's source height are used, so enabling
 * 2160p never upscales a 1080p upload.
 */
class EncodeProfile extends Model
{
    public const CODECS = ['h264' => 'H.264 (MP4)'];

    protected $fillable = [
        'name',
        'codec',
        'width',
        'height',
        'video_bitrate',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'width' => 'integer',
            'height' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function encodings(): HasMany
    {
        return $this->hasMany(VideoEncoding::class);
    }

    /** The bitrate in bits per second, for the HLS master playlist. */
    public function bandwidth(): int
    {
        return static::parseBitrate($this->video_bitrate);
    }

    /** '2800k' → 2800000, '5M' → 5000000, '750000' → 750000. */
    public static function parseBitrate(?string $value): int
    {
        if (! is_string($value) || ! preg_match('/^\s*(\d+(?:\.\d+)?)\s*([kKmM]?)\s*$/', $value, $m)) {
            return 0;
        }

        $multiplier = match (strtolower($m[2])) {
            'k' => 1000,
            'm' => 1000000,
            default => 1,
        };

        return (int) round((float) $m[1] * $multiplier);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The encode of one rendition of one video. See RenditionCoordinator.
 */
class VideoEncoding extends Model
{
    public const PENDING = 'pending';

    public const QUEUED = 'queued';

    public const PROCESSING = 'processing';

    public const FINALIZING = 'finalizing';

    public const COMPLETED = 'completed';

    public const FAILED = 'failed';

    /** Rendition label for the watermarked full-resolution copy of the upload. */
    public const ORIGINAL = 'original';

    public const TERMINAL = [self::COMPLETED, self::FAILED];

    protected $fillable = [
        'video_id',
        'encode_profile_id',
        'quality',
        'height',
        'source_width',
        'source_height',
        'status',
        'progress',
        'is_priority',
        'apply_watermark',
        'chunks_total',
        'chunks_completed',
        'chunk_seconds',
        'run_id',
        'settings_overrides',
        'error',
        'size',
        'started_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'height' => 'integer',
            'source_width' => 'integer',
            'source_height' => 'integer',
            'progress' => 'integer',
            'settings_overrides' => 'array',
            'is_priority' => 'boolean',
            'apply_watermark' => 'boolean',
            'chunks_total' => 'integer',
            'chunks_completed' => 'integer',
            'chunk_seconds' => 'integer',
            'size' => 'integer',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function video(): BelongsTo
    {
        return $this->belongsTo(Video::class);
    }

    public function profile(): BelongsTo
    {
        return $this->belongsTo(EncodeProfile::class, 'encode_profile_id');
    }

    public function isTerminal(): bool
    {
        return in_array($this->status, self::TERMINAL, true);
    }

    public function isOriginal(): bool
    {
        return $this->quality === self::ORIGINAL;
    }

    /** Human label for progress bars. */
    public function label(): string
    {
        return $this->isOriginal() ? 'Original (watermark)' : $this->quality;
    }
}

<?php

namespace App\Models;

use App\Support\Bytes;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One attempt to reclaim storage from a video's files.
 *
 * See the create_storage_reclaims_table migration for why this is not tracked
 * on video_encodings. The row's job is to make the bargain reviewable: how big
 * the file was, how big it is now, which settings produced that, and where the
 * old bytes are still being held.
 */
class StorageReclaim extends Model
{
    /** The uploaded file at `videos/{slug}/{file}.mp4`, re-compressed. */
    public const TARGET_ORIGINAL = 'original';

    /** One encoded rendition at `videos/{slug}/processed/{quality}.mp4`. */
    public const TARGET_RENDITION = 'rendition';

    /** The whole `videos/{slug}/processed/hls` tree, which duplicates them. */
    public const TARGET_HLS = 'hls';

    public const TARGETS = [self::TARGET_ORIGINAL, self::TARGET_RENDITION, self::TARGET_HLS];

    public const PENDING = 'pending';

    public const RUNNING = 'running';

    /** A smaller file exists; the old one is still held and still serving. */
    public const AWAITING_REVIEW = 'awaiting_review';

    public const ACCEPTED = 'accepted';

    public const REVERTED = 'reverted';

    /** keep_until passed unreviewed, which counts as acceptance. */
    public const EXPIRED = 'expired';

    public const FAILED = 'failed';

    /** Ran, but the result was not worth keeping. Nothing was changed. */
    public const SKIPPED = 'skipped';

    /** The kept file could not be put back. The live file is untouched. */
    public const REVERT_FAILED = 'revert_failed';

    /** Statuses that still hold bytes or a worker slot. */
    public const ACTIVE = [self::PENDING, self::RUNNING, self::AWAITING_REVIEW];

    public const TERMINAL = [
        self::ACCEPTED,
        self::REVERTED,
        self::EXPIRED,
        self::FAILED,
        self::SKIPPED,
        self::REVERT_FAILED,
    ];

    protected $fillable = [
        'video_id',
        'requested_by',
        'reviewed_by',
        'target',
        'quality',
        'status',
        'run_id',
        'settings_snapshot',
        'source_path',
        'new_path',
        'kept_path',
        'kept_is_hardlink',
        'before_bytes',
        'after_bytes',
        'before_duration_ms',
        'after_duration_ms',
        'error',
        'keep_until',
        'started_at',
        'finished_at',
    ];

    protected function casts(): array
    {
        return [
            'settings_snapshot' => 'array',
            'kept_is_hardlink' => 'boolean',
            'before_bytes' => 'integer',
            'after_bytes' => 'integer',
            'before_duration_ms' => 'integer',
            'after_duration_ms' => 'integer',
            'keep_until' => 'datetime',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function video(): BelongsTo
    {
        return $this->belongsTo(Video::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /** Attempts still in flight or waiting on a human. */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereIn('status', self::ACTIVE);
    }

    public function scopeAwaitingReview(Builder $query): Builder
    {
        return $query->where('status', self::AWAITING_REVIEW);
    }

    public function isActive(): bool
    {
        return in_array($this->status, self::ACTIVE, true);
    }

    public function isTerminal(): bool
    {
        return in_array($this->status, self::TERMINAL, true);
    }

    /**
     * Whether this attempt actually freed the space it claims.
     *
     * Bytes held by a kept file are not saved yet, so a row awaiting review
     * must never be counted towards a total — saying otherwise would be a lie
     * about how much disk is free.
     */
    public function isRealised(): bool
    {
        return in_array($this->status, [self::ACCEPTED, self::EXPIRED], true);
    }

    public function savedBytes(): int
    {
        if ($this->before_bytes === null || $this->after_bytes === null) {
            return 0;
        }

        return max(0, $this->before_bytes - $this->after_bytes);
    }

    public function savedPercent(): float
    {
        if (! $this->before_bytes) {
            return 0.0;
        }

        return round($this->savedBytes() / $this->before_bytes * 100, 1);
    }

    /** "1.4 GB (38%)", or an em dash while there is nothing to compare. */
    public function savingLabel(): string
    {
        if ($this->before_bytes === null || $this->after_bytes === null) {
            return '—';
        }

        return Bytes::saving($this->before_bytes, $this->after_bytes);
    }

    public function targetLabel(): string
    {
        return match ($this->target) {
            self::TARGET_ORIGINAL => 'Original upload',
            self::TARGET_RENDITION => $this->quality ? "Rendition {$this->quality}" : 'Rendition',
            self::TARGET_HLS => 'HLS duplicate',
            default => $this->target,
        };
    }
}

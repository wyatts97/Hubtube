<?php

namespace App\Models;

use App\Support\Bytes;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One attempt to re-compress a video's original upload.
 *
 * The original is the largest file a video owns — whatever the uploader gave
 * us, often camera footage at tens of megabits — and encoding it again at a
 * slower preset typically halves it. It is also the only file here worth
 * touching: the encoded renditions and the HLS segments are what the player
 * actually streams, so shrinking or dropping *those* costs real playback
 * capability rather than recovering waste.
 *
 * Deliberately not tracked on `video_encodings`: that table holds exactly one
 * row per rendition and `plan()` refills it and nulls `size` on every re-plan,
 * which destroys the before-size a reclaim has to compare against. A
 * non-terminal row there would also make Video::isEncoding() true forever.
 *
 * The row is the record of a bargain: we produced a smaller file, we are still
 * holding the original, and nothing about the video changes until someone
 * accepts. before/after bytes and the exact encoder settings are kept so a
 * result is still interpretable months later.
 */
class StorageReclaim extends Model
{
    /** The uploaded file at `videos/{slug}/{file}.mp4`, re-compressed. */
    public const TARGET_ORIGINAL = 'original';

    /**
     * The only thing this feature touches.
     *
     * Kept as an array because the ledger, the eligibility check and the
     * review page all read it — adding a second target means adding a worker,
     * an eligibility branch and a statement of what capability it costs.
     */
    public const TARGETS = [self::TARGET_ORIGINAL];

    public const PENDING = 'pending';

    public const RUNNING = 'running';

    /** A smaller file exists; the original is still held and still serving. */
    public const AWAITING_REVIEW = 'awaiting_review';

    public const ACCEPTED = 'accepted';

    public const REVERTED = 'reverted';

    public const FAILED = 'failed';

    /** Ran, but the result was not worth keeping. Nothing was changed. */
    public const SKIPPED = 'skipped';

    /** The original could not be put back. The live file is untouched. */
    public const REVERT_FAILED = 'revert_failed';

    /** Statuses that still hold bytes or a worker slot. */
    public const ACTIVE = [self::PENDING, self::RUNNING, self::AWAITING_REVIEW];

    public const TERMINAL = [
        self::ACCEPTED,
        self::REVERTED,
        self::FAILED,
        self::SKIPPED,
        self::REVERT_FAILED,
    ];

    protected $fillable = [
        'video_id',
        'requested_by',
        'reviewed_by',
        'target',
        'status',
        'run_id',
        'settings_snapshot',
        'source_path',
        'new_path',
        'kept_path',
        'before_bytes',
        'after_bytes',
        'before_duration_ms',
        'after_duration_ms',
        'error',
        'started_at',
        'finished_at',
    ];

    protected function casts(): array
    {
        return [
            'settings_snapshot' => 'array',
            'before_bytes' => 'integer',
            'after_bytes' => 'integer',
            'before_duration_ms' => 'integer',
            'after_duration_ms' => 'integer',
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
     * Bytes held by the kept original are not saved yet, so a row awaiting
     * review must never be counted towards a total — saying otherwise would be
     * a lie about how much disk is free.
     */
    public function isRealised(): bool
    {
        return $this->status === self::ACCEPTED;
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
        return 'Original upload';
    }
}

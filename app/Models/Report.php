<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Report extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'reportable_type',
        'reportable_id',
        'reason',
        'description',
        'status',
        'resolved_at',
        'resolved_by',
        'resolution_notes',
    ];

    protected function casts(): array
    {
        return [
            'resolved_at' => 'datetime',
        ];
    }

    const STATUS_PENDING = 'pending';
    const STATUS_REVIEWING = 'reviewing';
    const STATUS_RESOLVED = 'resolved';
    const STATUS_DISMISSED = 'dismissed';

    const REASON_SPAM = 'spam';
    const REASON_HARASSMENT = 'harassment';
    const REASON_ILLEGAL = 'illegal';
    const REASON_COPYRIGHT = 'copyright';
    const REASON_UNDERAGE = 'underage';
    const REASON_OTHER = 'other';

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reportable(): MorphTo
    {
        return $this->morphTo();
    }

    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function resolve(User $admin, ?string $notes = null): void
    {
        $this->update([
            'status' => self::STATUS_RESOLVED,
            'resolved_at' => now(),
            'resolved_by' => $admin->id,
            'resolution_notes' => $notes,
        ]);
    }

    public function dismiss(User $admin, ?string $notes = null): void
    {
        $this->update([
            'status' => self::STATUS_DISMISSED,
            'resolved_at' => now(),
            'resolved_by' => $admin->id,
            'resolution_notes' => $notes,
        ]);
    }
}

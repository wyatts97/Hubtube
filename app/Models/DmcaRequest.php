<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DmcaRequest extends Model
{
    protected $fillable = [
        'complainant_name',
        'complainant_email',
        'complainant_company',
        'copyrighted_work_description',
        'infringing_urls',
        'video_id',
        'good_faith_statement',
        'accuracy_statement',
        'signature',
        'status',
        'admin_notes',
        'resolved_by',
        'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'good_faith_statement' => 'boolean',
            'accuracy_statement' => 'boolean',
            'resolved_at' => 'datetime',
        ];
    }

    const STATUS_PENDING = 'pending';
    const STATUS_ACTIONED = 'actioned';
    const STATUS_REJECTED = 'rejected';

    public function video(): BelongsTo
    {
        return $this->belongsTo(Video::class);
    }

    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function action(User $admin, ?string $notes = null): void
    {
        $this->update([
            'status' => self::STATUS_ACTIONED,
            'resolved_at' => now(),
            'resolved_by' => $admin->id,
            'admin_notes' => $notes,
        ]);
    }

    public function reject(User $admin, ?string $notes = null): void
    {
        $this->update([
            'status' => self::STATUS_REJECTED,
            'resolved_at' => now(),
            'resolved_by' => $admin->id,
            'admin_notes' => $notes,
        ]);
    }
}

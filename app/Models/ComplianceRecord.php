<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ComplianceRecord extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'document_type',
        'document_number',
        'document_front_path',
        'document_back_path',
        'selfie_path',
        'full_name',
        'date_of_birth',
        'address',
        'country',
        'status',
        'verified_at',
        'verified_by',
        'rejection_reason',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'verified_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    const STATUS_PENDING = 'pending';
    const STATUS_APPROVED = 'approved';
    const STATUS_REJECTED = 'rejected';
    const STATUS_EXPIRED = 'expired';

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeApproved($query)
    {
        return $query->where('status', self::STATUS_APPROVED);
    }

    public function isValid(): bool
    {
        if ($this->status !== self::STATUS_APPROVED) {
            return false;
        }

        if ($this->expires_at && $this->expires_at->isPast()) {
            return false;
        }

        return true;
    }

    public function approve(User $admin): void
    {
        $this->update([
            'status' => self::STATUS_APPROVED,
            'verified_at' => now(),
            'verified_by' => $admin->id,
        ]);

        $this->user->update(['age_verified_at' => now()]);
    }

    public function reject(User $admin, string $reason): void
    {
        $this->update([
            'status' => self::STATUS_REJECTED,
            'verified_by' => $admin->id,
            'rejection_reason' => $reason,
        ]);
    }
}

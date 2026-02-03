<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WithdrawalRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'amount',
        'currency',
        'payment_method',
        'payment_details',
        'status',
        'processed_by',
        'processed_at',
        'notes',
        'transaction_id',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'payment_details' => 'array',
            'processed_at' => 'datetime',
        ];
    }

    const STATUS_PENDING = 'pending';
    const STATUS_PROCESSING = 'processing';
    const STATUS_COMPLETED = 'completed';
    const STATUS_REJECTED = 'rejected';

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function processedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function approve(User $admin, string $transactionId = null): void
    {
        $this->update([
            'status' => self::STATUS_COMPLETED,
            'processed_by' => $admin->id,
            'processed_at' => now(),
            'transaction_id' => $transactionId,
        ]);
    }

    public function reject(User $admin, string $notes = null): void
    {
        $this->update([
            'status' => self::STATUS_REJECTED,
            'processed_by' => $admin->id,
            'processed_at' => now(),
            'notes' => $notes,
        ]);

        $this->user->increment('wallet_balance', $this->amount);
    }
}

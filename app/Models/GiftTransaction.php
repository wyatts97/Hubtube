<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GiftTransaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'gift_id',
        'sender_id',
        'receiver_id',
        'live_stream_id',
        'amount',
        'platform_cut',
        'receiver_amount',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'platform_cut' => 'decimal:2',
            'receiver_amount' => 'decimal:2',
        ];
    }

    public function gift(): BelongsTo
    {
        return $this->belongsTo(Gift::class);
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'receiver_id');
    }

    public function liveStream(): BelongsTo
    {
        return $this->belongsTo(LiveStream::class);
    }
}

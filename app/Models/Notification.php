<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Notification extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'from_user_id',
        'type',
        'title',
        'message',
        'data',
        'read_at',
    ];

    protected function casts(): array
    {
        return [
            'data' => 'array',
            'read_at' => 'datetime',
        ];
    }

    const TYPE_NEW_VIDEO = 'new_video';
    const TYPE_NEW_SUBSCRIBER = 'new_subscriber';
    const TYPE_NEW_COMMENT = 'new_comment';
    const TYPE_COMMENT_REPLY = 'comment_reply';
    const TYPE_VIDEO_LIKE = 'video_like';
    const TYPE_GIFT_RECEIVED = 'gift_received';
    const TYPE_LIVE_STARTED = 'live_started';
    const TYPE_WITHDRAWAL_APPROVED = 'withdrawal_approved';
    const TYPE_VIDEO_APPROVED = 'video_approved';
    const TYPE_VIDEO_PROCESSED = 'video_processed';
    const TYPE_VIDEO_REJECTED = 'video_rejected';

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function fromUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'from_user_id');
    }

    public function scopeUnread($query)
    {
        return $query->whereNull('read_at');
    }

    public function markAsRead(): void
    {
        $this->update(['read_at' => now()]);
    }

    public function isRead(): bool
    {
        return $this->read_at !== null;
    }
}

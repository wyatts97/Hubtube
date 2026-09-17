<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class Comment extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'video_id',
        'parent_id',
        'content',
        'likes_count',
        'dislikes_count',
        'is_pinned',
        'is_approved',
        'edited_at',
    ];

    protected function casts(): array
    {
        return [
            'is_pinned' => 'boolean',
            'is_approved' => 'boolean',
            'edited_at' => 'datetime',
        ];
    }

    /**
     * Keep videos.comments_count in step with the approved comments.
     *
     * The count used to be adjusted by the controller that happened to be
     * handling the request, so a comment approved or deleted from the admin
     * panel never moved it, and a comment held for moderation was counted as
     * though it were public. Doing it here covers every path that writes a
     * comment.
     */
    protected static function booted(): void
    {
        static::created(function (Comment $comment) {
            if ($comment->is_approved) {
                $comment->adjustVideoCount(1);
            }
        });

        static::updated(function (Comment $comment) {
            if ($comment->wasChanged('is_approved')) {
                $comment->adjustVideoCount($comment->is_approved ? 1 : -1);
            }
        });

        static::deleted(function (Comment $comment) {
            if ($comment->is_approved) {
                $comment->adjustVideoCount(-1);
            }
        });

        static::restored(function (Comment $comment) {
            if ($comment->is_approved) {
                $comment->adjustVideoCount(1);
            }
        });
    }

    /**
     * Move the parent video's comment counter, without letting it go negative
     * or touching the video's updated_at.
     */
    protected function adjustVideoCount(int $delta): void
    {
        // Straight to the query builder: an Eloquent update would also touch
        // the video's updated_at, which would reshuffle "recently updated"
        // ordering every time somebody commented.
        DB::table('videos')
            ->where('id', $this->video_id)
            ->when($delta < 0, fn ($query) => $query->where('comments_count', '>', 0))
            ->update(['comments_count' => DB::raw('comments_count '.($delta > 0 ? '+' : '-').' 1')]);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function video(): BelongsTo
    {
        return $this->belongsTo(Video::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Comment::class, 'parent_id');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(Comment::class, 'parent_id');
    }

    public function likes(): HasMany
    {
        return $this->hasMany(CommentLike::class);
    }

    public function scopeApproved($query)
    {
        return $query->where('is_approved', true);
    }

    /**
     * Comments $user is allowed to see: everything approved, plus their own
     * while it waits for moderation — otherwise a held comment looks to its
     * author as though it was silently dropped.
     */
    public function scopeVisibleTo($query, ?User $user)
    {
        return $query->where(function ($q) use ($user) {
            $q->where('is_approved', true);

            if ($user) {
                $q->orWhere('user_id', $user->id);
            }
        });
    }

    public function scopeTopLevel($query)
    {
        return $query->whereNull('parent_id');
    }

    public function scopePinned($query)
    {
        return $query->where('is_pinned', true);
    }
}

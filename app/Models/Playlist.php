<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class Playlist extends Model
{
    use HasFactory;

    /** The privacy values a playlist may take, matching the column's enum. */
    public const PRIVACIES = ['public', 'unlisted', 'private'];

    /** Title of the one playlist every account gets for free. */
    public const WATCH_LATER = 'Watch Later';

    protected $fillable = [
        'user_id',
        'title',
        'slug',
        'description',
        'thumbnail',
        'privacy',
        'is_default',
        'video_count',
    ];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function videos(): BelongsToMany
    {
        return $this->belongsToMany(Video::class, 'playlist_videos')
            ->withPivot('position')
            ->withTimestamps()
            ->orderBy('playlist_videos.position');
    }

    public function favoritedBy(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'playlist_favorites')
            ->withTimestamps();
    }

    public function isFavoritedBy(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        return $this->favoritedBy()->where('user_id', $user->id)->exists();
    }

    public function scopePublic($query)
    {
        return $query->where('privacy', 'public');
    }

    /**
     * Playlists $user is allowed to open.
     *
     * Unlisted behaves the way an unlisted video does — reachable by anyone
     * with the link, just never listed — so only private playlists are
     * restricted here.
     */
    public function scopeVisibleTo($query, ?User $user)
    {
        return $query->where(function ($q) use ($user) {
            $q->whereIn('privacy', ['public', 'unlisted']);

            if ($user) {
                $q->orWhere('user_id', $user->id);
            }
        });
    }

    /** Whether $user may open this playlist. */
    public function isVisibleTo(?User $user): bool
    {
        if ($this->privacy !== 'private') {
            return true;
        }

        return $user !== null && ($user->id === $this->user_id || (bool) $user->is_admin);
    }

    /**
     * The user's Watch Later list, created the first time it is needed.
     *
     * It is marked is_default, which is what stops it being deleted or
     * renamed, and starts private: a list of things you mean to watch is not
     * something to publish by accident.
     */
    public static function watchLaterFor(User $user): self
    {
        return static::firstOrCreate(
            ['user_id' => $user->id, 'is_default' => true],
            [
                'title' => self::WATCH_LATER,
                'slug' => 'watch-later-'.Str::random(8),
                'privacy' => 'private',
            ],
        );
    }

    public function addVideo(Video $video): void
    {
        $maxPosition = $this->videos()->max('position') ?? 0;
        $this->videos()->attach($video->id, ['position' => $maxPosition + 1]);
        $this->increment('video_count');

        // Set playlist thumbnail from the first video added
        if (! $this->thumbnail && $video->thumbnail_url) {
            $this->update(['thumbnail' => $video->thumbnail_url]);
        }
    }

    public function removeVideo(Video $video): void
    {
        $this->videos()->detach($video->id);
        $this->decrement('video_count');

        // A playlist keeps a copy of its first video's thumbnail, so removing
        // that video would otherwise leave a picture of something no longer in
        // the list.
        if ($this->thumbnail && $this->thumbnail === $video->thumbnail_url) {
            $next = $this->videos()->first();
            $this->update(['thumbnail' => $next?->thumbnail_url]);
        }
    }

    /**
     * Apply a new order to the playlist.
     *
     * Only the ids actually in the playlist are honoured, and anything the
     * caller left out keeps its place after them, so a stale page cannot drop
     * videos from a list by reordering it.
     *
     * @param  array<int>  $videoIds  in their new order
     */
    public function reorder(array $videoIds): void
    {
        $current = $this->videos()->pluck('videos.id')->all();
        $ordered = array_values(array_intersect(
            array_map('intval', $videoIds),
            $current,
        ));
        $ordered = array_merge($ordered, array_values(array_diff($current, $ordered)));

        DB::transaction(function () use ($ordered) {
            foreach ($ordered as $position => $videoId) {
                DB::table('playlist_videos')
                    ->where('playlist_id', $this->id)
                    ->where('video_id', $videoId)
                    ->update(['position' => $position + 1]);
            }
        });
    }
}

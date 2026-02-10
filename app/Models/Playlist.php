<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Playlist extends Model
{
    use HasFactory;

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
        if (!$user) return false;
        return $this->favoritedBy()->where('user_id', $user->id)->exists();
    }

    public function scopePublic($query)
    {
        return $query->where('privacy', 'public');
    }

    public function addVideo(Video $video): void
    {
        $maxPosition = $this->videos()->max('position') ?? 0;
        $this->videos()->attach($video->id, ['position' => $maxPosition + 1]);
        $this->increment('video_count');
    }

    public function removeVideo(Video $video): void
    {
        $this->videos()->detach($video->id);
        $this->decrement('video_count');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Hashtag extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'usage_count',
    ];

    public function videos(): BelongsToMany
    {
        return $this->belongsToMany(Video::class, 'video_hashtags')
            ->withTimestamps();
    }

    public function scopeTrending($query, int $limit = 10)
    {
        return $query->orderByDesc('usage_count')->limit($limit);
    }

    public function incrementUsage(): void
    {
        $this->increment('usage_count');
    }
}

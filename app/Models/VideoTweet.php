<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VideoTweet extends Model
{
    protected $fillable = [
        'video_id',
        'tweet_id',
        'tweet_type',
        'tweeted_at',
        'tweet_url',
    ];

    protected function casts(): array
    {
        return [
            'tweeted_at' => 'datetime',
        ];
    }

    public function video(): BelongsTo
    {
        return $this->belongsTo(Video::class);
    }
}

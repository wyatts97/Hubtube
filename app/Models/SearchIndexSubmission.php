<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class SearchIndexSubmission extends Model
{
    protected $fillable = [
        'engine',
        'url',
        'url_hash',
        'subject_type',
        'subject_id',
        'status',
        'response_code',
        'response_message',
        'attempts',
        'submitted_at',
    ];

    protected $casts = [
        'submitted_at' => 'datetime',
        'response_code' => 'integer',
        'attempts' => 'integer',
    ];

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public static function hashUrl(string $url): string
    {
        return hash('sha256', $url);
    }
}

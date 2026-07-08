<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CCBillWebhookEvent extends Model
{
    protected $table = 'ccbill_webhook_events';

    protected $fillable = [
        'event_type',
        'ccbill_subscription_id',
        'fingerprint',
        'payload',
        'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'processed_at' => 'datetime',
        ];
    }
}

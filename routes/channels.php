<?php

use App\Models\LiveStream;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('user.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('live-stream.{liveStreamId}', function ($user, $liveStreamId) {
    return LiveStream::query()
        ->whereKey($liveStreamId)
        ->where('status', LiveStream::STATUS_LIVE)
        ->exists();
});

Broadcast::channel('live-streams', function ($user) {
    return $user !== null;
});

<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('user.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('live-stream.{liveStreamId}', function ($user, $liveStreamId) {
    return true;
});

Broadcast::channel('live-streams', function () {
    return true;
});

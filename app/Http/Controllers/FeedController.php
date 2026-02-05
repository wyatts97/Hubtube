<?php

namespace App\Http\Controllers;

use App\Models\Video;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class FeedController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $subscribedChannelIds = $request->user()
            ->subscriptions()
            ->pluck('channel_id');

        $videos = Video::query()
            ->with('user')
            ->whereIn('user_id', $subscribedChannelIds)
            ->public()
            ->approved()
            ->processed()
            ->latest('published_at')
            ->paginate(24);

        return Inertia::render('Feed', [
            'videos' => $videos,
        ]);
    }
}

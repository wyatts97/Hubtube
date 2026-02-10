<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Video;
use App\Services\SeoService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ChannelController extends Controller
{
    public function __construct(
        protected SeoService $seoService,
    ) {}

    public function show(User $user): Response
    {
        $user->load('channel');

        $videos = Video::query()
            ->where('user_id', $user->id)
            ->public()
            ->approved()
            ->processed()
            ->latest('published_at')
            ->paginate(24);

        $isSubscribed = auth()->check() 
            ? auth()->user()->isSubscribedTo($user) 
            : false;

        return Inertia::render('Channel/Show', [
            'channel' => $user,
            'videos' => $videos,
            'isSubscribed' => $isSubscribed,
            'subscriberCount' => $user->subscriber_count,
            'seo' => $this->seoService->forChannel($user),
        ]);
    }

    public function videos(User $user): Response
    {
        $videos = Video::query()
            ->where('user_id', $user->id)
            ->public()
            ->approved()
            ->processed()
            ->latest('published_at')
            ->paginate(24);

        return Inertia::render('Channel/Videos', [
            'channel' => $user->load('channel'),
            'videos' => $videos,
        ]);
    }

    public function shorts(User $user): Response
    {
        $shorts = Video::query()
            ->where('user_id', $user->id)
            ->shorts()
            ->public()
            ->approved()
            ->processed()
            ->latest('published_at')
            ->paginate(24);

        return Inertia::render('Channel/Shorts', [
            'channel' => $user->load('channel'),
            'shorts' => $shorts,
        ]);
    }

    public function playlists(User $user, Request $request): Response
    {
        $tab = $request->query('tab', 'user');

        $playlists = $user->playlists()
            ->public()
            ->withCount('videos')
            ->latest()
            ->paginate(24, ['*'], 'page');

        $favoritePlaylists = $user->favoritePlaylists()
            ->public()
            ->with('user')
            ->withCount('videos')
            ->latest('playlist_favorites.created_at')
            ->paginate(24, ['*'], 'fav_page');

        return Inertia::render('Channel/Playlists', [
            'channel' => $user->load('channel'),
            'playlists' => $playlists,
            'favoritePlaylists' => $favoritePlaylists,
            'activeTab' => $tab,
        ]);
    }

    public function about(User $user): Response
    {
        $user->load('channel');

        return Inertia::render('Channel/About', [
            'channel' => $user,
            'stats' => [
                'totalViews' => $user->channel?->total_views ?? 0,
                'joinedAt' => $user->created_at,
                'videoCount' => $user->videos()->public()->approved()->count(),
            ],
        ]);
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\Setting;
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

    public function localeShow(string $locale, string $username): Response
    {
        $user = User::where('username', $username)->firstOrFail();
        return $this->show($user);
    }

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
            'bannerAd' => [
                'enabled' => Setting::get('channel_banner_ad_enabled', false),
                'type' => Setting::get('channel_banner_ad_type', 'html'),
                'code' => Setting::get('channel_banner_ad_code', ''),
                'image' => Setting::get('channel_banner_ad_image', ''),
                'link' => Setting::get('channel_banner_ad_link', ''),
                'mobileType' => Setting::get('channel_banner_ad_mobile_type', 'html'),
                'mobileCode' => Setting::get('channel_banner_ad_mobile_code', ''),
                'mobileImage' => Setting::get('channel_banner_ad_mobile_image', ''),
                'mobileLink' => Setting::get('channel_banner_ad_mobile_link', ''),
            ],
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

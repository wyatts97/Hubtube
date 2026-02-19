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

    public function localeVideos(string $locale, string $username): Response
    {
        return $this->videos(User::where('username', $username)->firstOrFail());
    }

    public function localePlaylists(string $locale, string $username, Request $request): Response
    {
        return $this->playlists(User::where('username', $username)->firstOrFail(), $request);
    }

    public function localeLikedVideos(string $locale, string $username): Response
    {
        return $this->likedVideos(User::where('username', $username)->firstOrFail());
    }

    public function localeWatchHistory(string $locale, string $username): Response
    {
        return $this->watchHistory(User::where('username', $username)->firstOrFail());
    }

    public function localeAbout(string $locale, string $username): Response
    {
        return $this->about(User::where('username', $username)->firstOrFail());
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

        $isOwner = auth()->id() === $user->id;
        $settings = $user->settings ?? [];

        return Inertia::render('Channel/Show', [
            'channel' => $user,
            'videos' => $videos,
            'isSubscribed' => $isSubscribed,
            'subscriberCount' => $user->subscriber_count,
            'showLikedVideos' => $isOwner || !empty($settings['show_liked_videos']),
            'showWatchHistory' => $isOwner || !empty($settings['show_watch_history']),
            'seo' => $this->seoService->forChannel($user),
            'bannerAd' => [
                'enabled' => (bool) Setting::get('channel_banner_ad_enabled', false),
                'code' => (string) Setting::get('channel_banner_ad_code', ''),
                'image' => (string) Setting::get('channel_banner_ad_image', ''),
                'link' => (string) Setting::get('channel_banner_ad_link', ''),
                'mobileCode' => (string) Setting::get('channel_banner_ad_mobile_code', ''),
                'mobileImage' => (string) Setting::get('channel_banner_ad_mobile_image', ''),
                'mobileLink' => (string) Setting::get('channel_banner_ad_mobile_link', ''),
            ],
        ]);
    }

    public function likedVideos(User $user): Response
    {
        $isOwner = auth()->id() === $user->id;
        $settings = $user->settings ?? [];

        if (!$isOwner && empty($settings['show_liked_videos'])) {
            abort(404);
        }

        $videos = Video::query()
            ->whereIn('id', $user->likes()->likes()->pluck('video_id'))
            ->public()
            ->approved()
            ->processed()
            ->latest('published_at')
            ->paginate(24);

        return Inertia::render('Channel/LikedVideos', [
            'channel' => $user->load('channel'),
            'videos' => $videos,
            'isOwner' => $isOwner,
            'showLikedVideos' => true,
            'showWatchHistory' => $isOwner || !empty($settings['show_watch_history']),
        ]);
    }

    public function watchHistory(User $user): Response
    {
        $isOwner = auth()->id() === $user->id;
        $settings = $user->settings ?? [];

        if (!$isOwner && empty($settings['show_watch_history'])) {
            abort(404);
        }

        $videoIds = $user->watchHistory()
            ->latest()
            ->pluck('video_id')
            ->unique();

        $videos = Video::query()
            ->whereIn('id', $videoIds)
            ->public()
            ->approved()
            ->processed()
            ->paginate(24);

        return Inertia::render('Channel/WatchHistory', [
            'channel' => $user->load('channel'),
            'videos' => $videos,
            'isOwner' => $isOwner,
            'showLikedVideos' => $isOwner || !empty($settings['show_liked_videos']),
            'showWatchHistory' => true,
        ]);
    }

    public function videos(User $user): Response
    {
        $isOwner = auth()->id() === $user->id;
        $settings = $user->settings ?? [];

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
            'showLikedVideos' => $isOwner || !empty($settings['show_liked_videos']),
            'showWatchHistory' => $isOwner || !empty($settings['show_watch_history']),
        ]);
    }

    public function playlists(User $user, Request $request): Response
    {
        $tab = $request->query('tab', 'user');

        $isOwner = auth()->id() === $user->id;
        $settings = $user->settings ?? [];

        $playlists = $user->playlists()
            ->withCount('videos')
            ->latest()
            ->paginate(24, ['*'], 'page');

        $favoritePlaylists = $user->favoritePlaylists()
            ->with('user')
            ->withCount('videos')
            ->latest('playlist_favorites.created_at')
            ->paginate(24, ['*'], 'fav_page');

        return Inertia::render('Channel/Playlists', [
            'channel' => $user->load('channel'),
            'playlists' => $playlists,
            'favoritePlaylists' => $favoritePlaylists,
            'activeTab' => $tab,
            'showLikedVideos' => $isOwner || !empty($settings['show_liked_videos']),
            'showWatchHistory' => $isOwner || !empty($settings['show_watch_history']),
        ]);
    }

    public function about(User $user): Response
    {
        $user->load('channel');

        $isOwner = auth()->id() === $user->id;
        $settings = $user->settings ?? [];

        return Inertia::render('Channel/About', [
            'channel' => $user,
            'stats' => [
                'totalViews' => $user->channel?->total_views ?? 0,
                'joinedAt' => $user->created_at,
                'videoCount' => $user->videos()->public()->approved()->count(),
            ],
            'showLikedVideos' => $isOwner || !empty($settings['show_liked_videos']),
            'showWatchHistory' => $isOwner || !empty($settings['show_watch_history']),
        ]);
    }
}

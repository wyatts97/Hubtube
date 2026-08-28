<?php

namespace App\Http\Controllers;

use App\Http\Resources\ChannelProfileResource;
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

    /**
     * Render one tab of a channel.
     *
     * Every tab shares the same header, tab strip, SEO block and banner-ad
     * config. Previously each of the six actions rebuilt those itself, which
     * is why only the landing tab had SEO tags and a banner ad, and why the
     * header disagreed with the About tab about the video count.
     */
    private function renderTab(User $user, string $page, string $activeTab, array $props = []): Response
    {
        $user->loadMissing('channel');
        $user->setAttribute('public_video_count', $user->publicVideoCount());

        $settings = $user->settings ?? [];
        $isOwner = auth()->id() === $user->id;

        // A locked channel returns before $props is touched. The actions pass
        // their data as closures precisely so those queries never run for a
        // viewer who is not allowed to see the results.
        if (! $user->isVisibleTo(auth()->user())) {
            return Inertia::render('Channel/Private', [
                'channel' => (new ChannelProfileResource($user))->resolve(),
                'isOwner' => $isOwner,
                'seo' => $this->seoService->forChannel($user),
            ]);
        }

        $subscription = auth()->check()
            ? auth()->user()->channelSubscriptions()->where('channel_id', $user->id)->first()
            : null;

        return Inertia::render('Channel/'.$page, array_merge([
            // resolve() rather than handing Inertia the resource object:
            // Inertia serialises a JsonResource through its Responsable path,
            // which applies the "data" wrapper and would nest the whole
            // payload under channel.data.
            'channel' => (new ChannelProfileResource($user))->resolve(),
            'activeTab' => $activeTab,
            'isOwner' => $isOwner,
            'isSubscribed' => $subscription !== null,
            'notificationsEnabled' => (bool) ($subscription?->notifications_enabled ?? true),
            'subscriberCount' => $user->subscriber_count,
            'showLikedVideos' => $isOwner || ! empty($settings['show_liked_videos']),
            'showWatchHistory' => $isOwner || ! empty($settings['show_watch_history']),
            'seo' => $this->seoService->forChannel($user),
            'bannerAd' => $this->bannerAdConfig(),
        ], $props));
    }

    /**
     * Pro users with the ad-free perk see no channel banner ad.
     */
    protected function shouldSuppressAds(): bool
    {
        $user = auth()->user();

        return $user && $user->is_pro && (bool) Setting::get('pro_ad_free', true);
    }

    /**
     * The channel banner ad slot. This used to be inlined in show() only, so
     * the other five tabs rendered no ad at all; it now applies to every tab.
     */
    protected function bannerAdConfig(): array
    {
        if ($this->shouldSuppressAds()) {
            return ['enabled' => false];
        }

        return [
            'enabled' => (bool) Setting::get('channel_banner_ad_enabled', false),
            'code' => (string) Setting::get('channel_banner_ad_html', ''),
            'image' => (string) Setting::get('channel_banner_ad_image', ''),
            'link' => (string) Setting::get('channel_banner_ad_link', ''),
            'mobileCode' => (string) Setting::get('channel_banner_ad_mobile_html', ''),
            'mobileImage' => (string) Setting::get('channel_banner_ad_mobile_image', ''),
            'mobileLink' => (string) Setting::get('channel_banner_ad_mobile_link', ''),
        ];
    }

    /**
     * The channel's own public videos, newest first.
     */
    protected function publicVideos(User $user)
    {
        return Video::query()
            ->where('user_id', $user->id)
            ->public()
            ->approved()
            ->processed()
            ->latest('published_at')
            ->paginate(24)
            ->withQueryString();
    }

    public function show(User $user): Response
    {
        return $this->renderTab($user, 'Show', 'videos', [
            'videos' => fn () => $this->publicVideos($user),
        ]);
    }

    /**
     * /channel/{user}/videos renders the same tab as /channel/{user}.
     *
     * The two used to be separate pages built from identical queries. The tab
     * strip only ever linked to the bare URL, so this route is effectively
     * orphaned — but it is publicly routable and may have external inbound
     * links, so it is kept as an alias rather than removed.
     */
    public function videos(User $user): Response
    {
        return $this->show($user);
    }

    public function playlists(User $user, Request $request): Response
    {
        return $this->renderTab($user, 'Playlists', 'playlists', [
            'playlists' => fn () => $user->playlists()
                ->withCount('videos')
                ->latest()
                ->paginate(24, ['*'], 'page')
                ->withQueryString(),
            'favoritePlaylists' => fn () => $user->favoritePlaylists()
                ->with('user')
                ->withCount('videos')
                ->latest('playlist_favorites.created_at')
                ->paginate(24, ['*'], 'fav_page')
                ->withQueryString(),
            'playlistTab' => $request->query('tab', 'user'),
        ]);
    }

    public function likedVideos(User $user): Response
    {
        $settings = $user->settings ?? [];

        if (auth()->id() !== $user->id && empty($settings['show_liked_videos'])) {
            abort(404);
        }

        return $this->renderTab($user, 'LikedVideos', 'liked', [
            'videos' => fn () => Video::query()
                ->whereIn('id', $user->likes()->likes()->pluck('video_id'))
                ->public()
                ->approved()
                ->processed()
                ->latest('published_at')
                ->paginate(24)
                ->withQueryString(),
        ]);
    }

    public function watchHistory(User $user): Response
    {
        $settings = $user->settings ?? [];

        if (auth()->id() !== $user->id && empty($settings['show_watch_history'])) {
            abort(404);
        }

        return $this->renderTab($user, 'WatchHistory', 'history', [
            'videos' => fn () => Video::query()
                ->whereIn('id', $user->watchHistory()->latest()->pluck('video_id')->unique())
                ->public()
                ->approved()
                ->processed()
                ->paginate(24)
                ->withQueryString(),
        ]);
    }

    public function about(User $user): Response
    {
        return $this->renderTab($user, 'About', 'about');
    }
}

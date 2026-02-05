<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\EmbeddedVideo;
use App\Models\LiveStream;
use App\Models\Setting;
use App\Models\Video;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    public function index(Request $request): Response
    {
        $perPage = Setting::get('videos_per_page', 24);

        // Get regular featured videos
        $featuredVideos = Video::query()
            ->with('user')
            ->featured()
            ->public()
            ->approved()
            ->processed()
            ->latest('published_at')
            ->limit(8)
            ->get();

        // Get featured embedded videos and merge
        $featuredEmbedded = EmbeddedVideo::published()
            ->featured()
            ->latest('imported_at')
            ->limit(4)
            ->get()
            ->map(fn ($v) => $v->toVideoFormat());

        $featuredVideos = $featuredVideos->concat($featuredEmbedded)->take(8);

        // Get regular latest videos
        $regularVideos = Video::query()
            ->with('user')
            ->public()
            ->approved()
            ->processed()
            ->latest('published_at')
            ->paginate($perPage);

        // Get embedded videos and merge into latest
        $embeddedVideos = EmbeddedVideo::published()
            ->latest('imported_at')
            ->get()
            ->map(fn ($v) => $v->toVideoFormat());

        // Merge and sort by date for latest videos data
        $mergedLatest = collect($regularVideos->items())
            ->concat($embeddedVideos)
            ->sortByDesc(fn ($v) => $v['published_at'] ?? $v['created_at'] ?? now())
            ->take($perPage)
            ->values();

        // Replace paginated data with merged data
        $latestVideos = $regularVideos;
        $latestVideos->setCollection($mergedLatest);

        // Get popular videos (regular + embedded)
        $popularVideos = Video::query()
            ->with('user')
            ->public()
            ->approved()
            ->processed()
            ->orderByDesc('views_count')
            ->limit(12)
            ->get();

        $popularEmbedded = EmbeddedVideo::published()
            ->orderByDesc('views_count')
            ->limit(6)
            ->get()
            ->map(fn ($v) => $v->toVideoFormat());

        $popularVideos = $popularVideos->concat($popularEmbedded)
            ->sortByDesc(fn ($v) => $v['views_count'] ?? $v->views_count ?? 0)
            ->take(12)
            ->values();

        $liveStreams = LiveStream::query()
            ->with('user')
            ->live()
            ->orderByDesc('viewer_count')
            ->limit(6)
            ->get();

        $categories = Category::active()
            ->parentCategories()
            ->orderBy('sort_order')
            ->get();

        // Get ad settings
        $adSettings = [
            'videoGridEnabled' => (bool) Setting::get('video_grid_ad_enabled', false),
            'videoGridCode' => (string) Setting::get('video_grid_ad_code', ''),
            'videoGridFrequency' => (int) Setting::get('video_grid_ad_frequency', 8),
        ];

        return Inertia::render('Home', [
            'featuredVideos' => $featuredVideos,
            'latestVideos' => $latestVideos,
            'popularVideos' => $popularVideos,
            'liveStreams' => $liveStreams,
            'categories' => $categories,
            'adSettings' => $adSettings,
        ]);
    }

    public function loadMoreVideos(Request $request): JsonResponse
    {
        $perPage = Setting::get('videos_per_page', 24);
        $page = $request->input('page', 1);

        $regularVideos = Video::query()
            ->with('user')
            ->public()
            ->approved()
            ->processed()
            ->latest('published_at')
            ->paginate($perPage, ['*'], 'page', $page);

        // Merge embedded videos into results for consistency with initial page load
        $embeddedVideos = EmbeddedVideo::published()
            ->latest('imported_at')
            ->get()
            ->map(fn ($v) => $v->toVideoFormat());

        $merged = collect($regularVideos->items())
            ->concat($embeddedVideos)
            ->sortByDesc(fn ($v) => $v['published_at'] ?? $v['created_at'] ?? now())
            ->take($perPage)
            ->values();

        $regularVideos->setCollection($merged);

        return response()->json($regularVideos);
    }

    public function trending(Request $request): Response|JsonResponse
    {
        $perPage = Setting::get('videos_per_page', 24);
        
        $videos = Video::query()
            ->with('user')
            ->public()
            ->approved()
            ->processed()
            ->where('published_at', '>=', now()->subDays(7))
            ->orderByDesc('views_count')
            ->paginate($perPage);

        // Return JSON for AJAX requests (infinite scroll)
        if ($request->wantsJson()) {
            return response()->json($videos);
        }

        return Inertia::render('Trending', [
            'videos' => $videos,
        ]);
    }

    public function shorts(): Response
    {
        $shorts = Video::query()
            ->with('user')
            ->shorts()
            ->public()
            ->approved()
            ->processed()
            ->latest('published_at')
            ->paginate(24);

        return Inertia::render('Shorts', [
            'shorts' => $shorts,
        ]);
    }

    public function feed(Request $request): Response
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

    public function dashboard(Request $request): Response
    {
        $user = $request->user();

        $totalVideos = $user->videos()->count();
        $totalViews = $user->videos()->sum('views_count');
        $totalLikes = $user->videos()->sum('likes_count');
        $subscriberCount = $user->subscribers()->count();

        $recentVideos = $user->videos()
            ->latest()
            ->limit(10)
            ->get();

        $topVideos = $user->videos()
            ->orderByDesc('views_count')
            ->limit(5)
            ->get();

        return Inertia::render('Dashboard', [
            'stats' => [
                'totalVideos' => $totalVideos,
                'totalViews' => $totalViews,
                'totalLikes' => $totalLikes,
                'subscriberCount' => $subscriberCount,
                'walletBalance' => $user->wallet_balance,
            ],
            'recentVideos' => $recentVideos,
            'topVideos' => $topVideos,
        ]);
    }
}

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

        // Single query for all published embedded videos (paginated, not ->get() all)
        $allEmbedded = EmbeddedVideo::published()
            ->latest('imported_at')
            ->limit($perPage)
            ->get();

        $embeddedFormatted = $allEmbedded->map(fn ($v) => $v->toVideoFormat());

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

        // Merge featured embedded videos from the single query
        $featuredEmbedded = $allEmbedded->filter(fn ($v) => $v->is_featured)
            ->take(4)
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

        // Merge and sort by date for latest videos data
        $mergedLatest = collect($regularVideos->items())
            ->concat($embeddedFormatted)
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

        $popularEmbedded = $allEmbedded->sortByDesc('views_count')
            ->take(6)
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

        // Shorts carousel
        $shortsCarouselEnabled = (bool) Setting::get('homepage_shorts_carousel', false);
        $shortsForCarousel = [];
        if ($shortsCarouselEnabled) {
            $shortsForCarousel = Video::query()
                ->with('user')
                ->shorts()
                ->public()
                ->approved()
                ->processed()
                ->latest('published_at')
                ->limit(20)
                ->get();
        }

        return Inertia::render('Home', [
            'featuredVideos' => $featuredVideos,
            'latestVideos' => $latestVideos,
            'popularVideos' => $popularVideos,
            'liveStreams' => $liveStreams,
            'categories' => $categories,
            'adSettings' => $adSettings,
            'shortsCarousel' => $shortsForCarousel,
            'shortsCarouselEnabled' => $shortsCarouselEnabled,
        ]);
    }

    public function loadMoreVideos(Request $request): JsonResponse
    {
        $perPage = Setting::get('videos_per_page', 24);
        $page = $request->input('page', 1);

        // Split the page budget between regular and embedded videos
        $embeddedPerPage = (int) ceil($perPage * 0.25); // ~25% embedded
        $regularPerPage = $perPage - $embeddedPerPage;

        $regularVideos = Video::query()
            ->with('user')
            ->public()
            ->approved()
            ->processed()
            ->latest('published_at')
            ->paginate($regularPerPage, ['*'], 'page', $page);

        // Paginate embedded videos with the same page offset
        $embeddedVideos = EmbeddedVideo::published()
            ->latest('imported_at')
            ->offset(($page - 1) * $embeddedPerPage)
            ->limit($embeddedPerPage)
            ->get()
            ->map(fn ($v) => $v->toVideoFormat());

        $merged = collect($regularVideos->items())
            ->concat($embeddedVideos)
            ->sortByDesc(fn ($v) => $v['published_at'] ?? $v['created_at'] ?? now())
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
            ->paginate(12);

        return Inertia::render('Shorts', [
            'shorts' => $shorts,
            'adSettings' => [
                'enabled' => (bool) Setting::get('shorts_ads_enabled', false),
                'frequency' => (int) Setting::get('shorts_ad_frequency', 3),
                'skipDelay' => (int) Setting::get('shorts_ad_skip_delay', 5),
                'code' => Setting::get('shorts_ad_code', ''),
            ],
        ]);
    }

    public function loadMoreShorts(Request $request): \Illuminate\Http\JsonResponse
    {
        $shorts = Video::query()
            ->with('user')
            ->shorts()
            ->public()
            ->approved()
            ->processed()
            ->latest('published_at')
            ->paginate(12);

        return response()->json($shorts);
    }

    public function categories(): Response
    {
        $categories = Category::active()
            ->parentCategories()
            ->withCount('videos')
            ->orderBy('sort_order')
            ->get()
            ->map(function ($category) {
                $latestVideo = Video::where('category_id', $category->id)
                    ->public()->approved()->processed()
                    ->latest('published_at')
                    ->first();
                $category->latest_thumbnail = $latestVideo?->thumbnail_url ?? $latestVideo?->thumbnail ?? null;
                return $category;
            });

        return Inertia::render('Categories/Index', [
            'categories' => $categories,
        ]);
    }

    public function category(Category $category): Response
    {
        $videos = Video::query()
            ->with('user')
            ->where('category_id', $category->id)
            ->public()
            ->approved()
            ->processed()
            ->latest('published_at')
            ->paginate(24);

        return Inertia::render('Categories/Show', [
            'category' => $category,
            'videos' => $videos,
        ]);
    }

    public function tag(string $tag): Response
    {
        $videos = Video::query()
            ->with('user')
            ->public()
            ->approved()
            ->processed()
            ->whereJsonContains('tags', $tag)
            ->latest('published_at')
            ->paginate(24);

        return Inertia::render('Tags/Show', [
            'tag' => $tag,
            'videos' => $videos,
        ]);
    }
}

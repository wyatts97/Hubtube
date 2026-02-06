<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\LiveStream;
use App\Models\Setting;
use App\Models\Video;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    public function index(Request $request): Response
    {
        $perPage = Setting::get('videos_per_page', 24);

        // Featured videos (includes both uploaded and imported)
        $featuredVideos = Video::query()
            ->with('user')
            ->featured()
            ->public()
            ->approved()
            ->processed()
            ->latest('published_at')
            ->limit(8)
            ->get();

        // Latest videos (includes both uploaded and imported)
        $latestVideos = Video::query()
            ->with('user')
            ->public()
            ->approved()
            ->processed()
            ->latest('published_at')
            ->paginate($perPage);

        // Popular videos
        $popularVideos = Video::query()
            ->with('user')
            ->public()
            ->approved()
            ->processed()
            ->orderByDesc('views_count')
            ->limit(12)
            ->get();

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

        $videos = Video::query()
            ->with('user')
            ->public()
            ->approved()
            ->processed()
            ->latest('published_at')
            ->paginate($perPage);

        return response()->json($videos);
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

    public function shorts(?Video $video = null): Response
    {
        $query = Video::query()
            ->with('user')
            ->shorts()
            ->public()
            ->approved()
            ->processed()
            ->latest('published_at');

        // If a specific short was requested, put it first
        $startingShort = null;
        if ($video && $video->is_short) {
            $startingShort = $video->load('user');
            $query->where('id', '!=', $video->id);
        }

        $shorts = $query->paginate(12);

        // Prepend the starting short to the first page
        if ($startingShort && $shorts->currentPage() === 1) {
            $items = collect([$startingShort])->merge($shorts->items());
            $shorts = new \Illuminate\Pagination\LengthAwarePaginator(
                $items,
                $shorts->total() + 1,
                $shorts->perPage(),
                $shorts->currentPage(),
                ['path' => route('shorts')]
            );
        }

        return Inertia::render('Shorts', [
            'shorts' => $shorts,
            'startingShortId' => $startingShort?->id,
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

<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\LiveStream;
use App\Models\Setting;
use App\Models\Video;
use App\Services\SeoService;
use App\Services\TranslationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    public function __construct(
        protected SeoService $seoService,
    ) {}
    public function index(Request $request): Response
    {
        $all = Setting::getAll();
        $s = fn (string $key, mixed $default = null) => $all[$key] ?? $default;

        $perPage = $s('videos_per_page', 24);

        // Featured videos — cached 2 minutes
        $featuredVideos = Cache::remember('home:featured', 120, fn () =>
            Video::query()
                ->with('user')
                ->featured()
                ->public()
                ->approved()
                ->processed()
                ->latest('published_at')
                ->limit(8)
                ->get()
        );

        // Latest videos — paginated, not cached (page-dependent)
        $latestVideos = Video::query()
            ->with('user')
            ->public()
            ->approved()
            ->processed()
            ->latest('published_at')
            ->paginate($perPage);

        // Popular videos — cached 5 minutes
        $popularVideos = Cache::remember('home:popular', 300, fn () =>
            Video::query()
                ->with('user')
                ->public()
                ->approved()
                ->processed()
                ->orderByDesc('views_count')
                ->limit(12)
                ->get()
        );

        // Live streams — cached 30 seconds (changes frequently)
        $liveStreams = Cache::remember('home:live', 30, fn () =>
            LiveStream::query()
                ->with('user')
                ->live()
                ->orderByDesc('viewer_count')
                ->limit(6)
                ->get()
        );

        // Categories — cached 10 minutes (rarely changes)
        $categories = Cache::remember('home:categories', 600, fn () =>
            Category::active()
                ->parentCategories()
                ->orderBy('sort_order')
                ->get()
        );

        $adSettings = [
            'videoGridEnabled' => (bool) $s('video_grid_ad_enabled', false),
            'videoGridCode' => (string) $s('video_grid_ad_code', ''),
            'videoGridFrequency' => (int) $s('video_grid_ad_frequency', 8),
        ];

        return Inertia::render('Home', [
            'featuredVideos' => $featuredVideos,
            'latestVideos' => $latestVideos,
            'popularVideos' => $popularVideos,
            'liveStreams' => $liveStreams,
            'categories' => $categories,
            'adSettings' => $adSettings,
            'seo' => $this->seoService->forHome(),
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
            'seo' => $this->seoService->forTrending(),
            'adSettings' => [
                'videoGridEnabled' => (bool) Setting::get('video_grid_ad_enabled', false),
                'videoGridCode' => (string) Setting::get('video_grid_ad_code', ''),
                'videoGridFrequency' => (int) Setting::get('video_grid_ad_frequency', 8),
            ],
        ]);
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

        $locale = App::getLocale();
        $defaultLocale = TranslationService::getDefaultLocale();

        if ($locale !== $defaultLocale) {
            $translationService = app(TranslationService::class);
            $categories->transform(function ($category) use ($translationService, $locale) {
                $category->name = $translationService->translateField(
                    Category::class, $category->id, 'name', $category->name, $locale
                );
                return $category;
            });
        }

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

        $locale = App::getLocale();
        $defaultLocale = TranslationService::getDefaultLocale();
        $translatedName = null;
        $translatedDescription = null;

        if ($locale !== $defaultLocale) {
            $translationService = app(TranslationService::class);
            $translatedName = $translationService->translateField(
                Category::class, $category->id, 'name', $category->name, $locale
            );
            if ($category->description) {
                $translatedDescription = $translationService->translateField(
                    Category::class, $category->id, 'description', $category->description, $locale
                );
            }
        }

        return Inertia::render('Categories/Show', [
            'category' => $category,
            'translatedName' => $translatedName,
            'translatedDescription' => $translatedDescription,
            'videos' => $videos,
            'seo' => $this->seoService->forCategory($category),
        ]);
    }

    public function localeCategory(string $locale, string $slug): Response
    {
        $category = Category::where('slug', $slug)->firstOrFail();

        return $this->category($category);
    }

    public function localeTag(string $locale, string $tag): Response
    {
        return $this->tag($tag);
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

        $locale = App::getLocale();
        $defaultLocale = TranslationService::getDefaultLocale();
        $translatedTag = null;

        if ($locale !== $defaultLocale) {
            $translationService = app(TranslationService::class);
            $translatedTag = $translationService->translateText($tag, $locale, $defaultLocale);
        }

        return Inertia::render('Tags/Show', [
            'tag' => $tag,
            'translatedTag' => $translatedTag,
            'videos' => $videos,
            'seo' => $this->seoService->forTag($tag),
        ]);
    }
}

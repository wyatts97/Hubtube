<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Setting;
use App\Models\SponsoredCard;
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
            'videoGridMobileCode' => (string) $s('video_grid_ad_mobile_code', ''),
            'videoGridFrequency' => (int) $s('video_grid_ad_frequency', 8),
        ];

        return Inertia::render('Home', [
            'featuredVideos' => $featuredVideos,
            'latestVideos' => $latestVideos,
            'popularVideos' => $popularVideos,
            'categories' => $categories,
            'adSettings' => $adSettings,
            'seo' => $this->seoService->forHome(),
            'sponsoredCards' => SponsoredCard::getForPage('home', auth()->user()?->role ?? 'guest'),
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
        $period = $request->get('period', 'week');

        $query = Video::query()
            ->with('user')
            ->public()
            ->approved()
            ->processed();

        // Apply time filter
        $query->where('published_at', '>=', match ($period) {
            'today' => now()->startOfDay(),
            'week' => now()->subDays(7),
            'month' => now()->subDays(30),
            'year' => now()->subYear(),
            'all' => now()->subYears(50),
            default => now()->subDays(7),
        });

        $videos = $query->orderByDesc('views_count')->paginate($perPage)->appends(['period' => $period]);

        // Return JSON for AJAX requests (infinite scroll)
        if ($request->wantsJson()) {
            return response()->json($videos);
        }

        return Inertia::render('Trending', [
            'videos' => $videos,
            'period' => $period,
            'seo' => $this->seoService->forTrending(),
            'adSettings' => [
                'videoGridEnabled' => (bool) Setting::get('video_grid_ad_enabled', false),
                'videoGridCode' => (string) Setting::get('video_grid_ad_code', ''),
                'videoGridMobileCode' => (string) Setting::get('video_grid_ad_mobile_code', ''),
                'videoGridFrequency' => (int) Setting::get('video_grid_ad_frequency', 8),
            ],
            'sponsoredCards' => SponsoredCard::getForPage('trending', auth()->user()?->role ?? 'guest'),
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
            'bannerAd' => [
                'enabled' => (bool) Setting::get('category_banner_ad_enabled', false),
                'code' => (string) Setting::get('category_banner_ad_code', ''),
                'image' => (string) Setting::get('category_banner_ad_image', ''),
                'link' => (string) Setting::get('category_banner_ad_link', ''),
                'mobileCode' => (string) Setting::get('category_banner_ad_mobile_code', ''),
                'mobileImage' => (string) Setting::get('category_banner_ad_mobile_image', ''),
                'mobileLink' => (string) Setting::get('category_banner_ad_mobile_link', ''),
            ],
            'sponsoredCards' => SponsoredCard::getForPage(
                'category',
                auth()->user()?->role ?? 'guest',
                $category->id,
            ),
        ]);
    }

    public function localeCategory(string $locale, string $slug): Response
    {
        $category = Category::where('slug', $slug)->firstOrFail();

        return $this->category($category);
    }

    public function tags(): Response
    {
        // Get all unique tags from public, approved, processed videos
        $videos = Video::query()
            ->public()
            ->approved()
            ->processed()
            ->whereNotNull('tags')
            ->select('tags', 'thumbnail', 'external_thumbnail_url', 'storage_disk', 'published_at')
            ->latest('published_at')
            ->get();

        $tagMap = [];
        foreach ($videos as $video) {
            if (!is_array($video->tags)) continue;
            foreach ($video->tags as $tag) {
                $tag = trim($tag);
                if (empty($tag)) continue;
                if (!isset($tagMap[$tag])) {
                    $tagMap[$tag] = [
                        'name' => $tag,
                        'count' => 0,
                        'thumbnail' => null,
                    ];
                }
                $tagMap[$tag]['count']++;
                if (!$tagMap[$tag]['thumbnail']) {
                    $tagMap[$tag]['thumbnail'] = $video->thumbnail_url ?? $video->thumbnail;
                }
            }
        }

        // Sort by count descending
        $tags = collect(array_values($tagMap))->sortByDesc('count')->values()->all();

        return Inertia::render('Tags/Index', [
            'tags' => $tags,
        ]);
    }

    public function localeTags(string $locale): Response
    {
        return $this->tags();
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

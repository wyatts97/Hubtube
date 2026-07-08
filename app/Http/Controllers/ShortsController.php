<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Setting;
use App\Models\Video;
use App\Services\SeoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Inertia\Response;

class ShortsController extends Controller
{
    public function __construct(
        protected SeoService $seoService,
    ) {}

    public function index(Request $request, ?string $video_uuid = null): Response
    {
        $video = $video_uuid ? Video::where('uuid', $video_uuid)->first() : null;
        $filters = $this->normalizeFilters($request);
        $perPage = (int) Setting::get('shorts_per_page', 10);

        $shorts = $this->buildQuery($filters)
            ->with(['user.channel', 'category'])
            ->latest('published_at')
            ->paginate($perPage)
            ->appends($request->query());

        $startIndex = 0;
        if ($video && $shorts->contains(fn ($s) => $s->id === $video->id)) {
            $startIndex = $shorts->search(fn ($s) => $s->id === $video->id);
        }

        $categories = Cache::remember('home:categories', 600, fn () => Category::active()->parentCategories()->orderBy('sort_order')->get()
        );

        return Inertia::render('Shorts/Index', [
            'initialShorts' => $shorts,
            'startIndex' => $startIndex,
            'filters' => $filters,
            'categories' => $categories,
            'currentShort' => $video,
            'adSettings' => $this->adSettings(),
            'seo' => $this->seoService->forShorts($filters),
        ]);
    }

    public function feed(Request $request): JsonResponse
    {
        $filters = $this->normalizeFilters($request);
        $perPage = (int) Setting::get('shorts_per_page', 10);
        $cursor = (int) $request->get('cursor', 1);
        $exclude = array_filter(array_map('intval', explode(',', $request->get('exclude', ''))));

        $query = $this->buildQuery($filters)
            ->with(['user.channel', 'category']);

        if (! empty($exclude)) {
            $query->whereNotIn('id', $exclude);
        }

        $shorts = $query->latest('published_at')
            ->paginate($perPage, ['*'], 'cursor', $cursor);

        return response()->json([
            'data' => $shorts->items(),
            'next_cursor' => $shorts->hasMorePages() ? $cursor + 1 : null,
        ]);
    }

    protected function buildQuery(array $filters)
    {
        $query = Video::query()
            ->public()->approved()->processed()->shorts();

        if (! empty($filters['category_id'])) {
            $query->where('category_id', (int) $filters['category_id']);
        }

        if (! empty($filters['tag'])) {
            $query->whereJsonContains('tags', $filters['tag']);
        }

        if (! empty($filters['date'])) {
            $query->where('published_at', '>=', match ($filters['date']) {
                'today' => now()->startOfDay(),
                'week' => now()->subDays(7),
                'month' => now()->subDays(30),
                'year' => now()->subYear(),
                default => now()->subDays(7),
            });
        }

        if (! empty($filters['sort']) && $filters['sort'] === 'popular') {
            $query->reorder()->orderByDesc('views_count');
        }

        return $query;
    }

    protected function normalizeFilters(Request $request): array
    {
        return [
            'category_id' => $request->filled('category') ? (int) $request->get('category') : null,
            'tag' => $request->filled('tag') ? trim($request->get('tag')) : null,
            'date' => in_array($request->get('date'), ['today', 'week', 'month', 'year']) ? $request->get('date') : null,
            'sort' => in_array($request->get('sort'), ['latest', 'popular']) ? $request->get('sort') : 'latest',
        ];
    }

    protected function adSettings(): array
    {
        $user = auth()->user();
        $suppressed = $user && $user->is_pro && Setting::get('pro_ad_free', true);

        if ($suppressed) {
            return [
                'shortsAdEnabled' => false,
                'shortsAdFrequency' => 0,
            ];
        }

        return [
            'shortsAdEnabled' => (bool) Setting::get('shorts_ad_enabled', false),
            'shortsAdFrequency' => (int) Setting::get('shorts_ad_frequency', 8),
        ];
    }
}

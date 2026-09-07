<?php

namespace App\Http\Controllers;

use App\Models\Hashtag;
use App\Models\Category;
use App\Models\Setting;
use App\Models\SponsoredCard;
use App\Models\User;
use App\Models\Video;
use App\Services\SeoService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SearchController extends Controller
{
    public function __construct(
        protected SeoService $seoService,
    ) {}

    protected function shouldSuppressAds(): bool
    {
        $user = auth()->user();
        return $user && $user->is_pro && (bool) Setting::get('pro_ad_free', true);
    }

    public function index(Request $request): Response
    {
        $query = $request->get('q', '');
        $type = $request->get('type', 'videos');

        $filters = $request->only(['duration', 'quality', 'date', 'category', 'sort']);

        $results = match ($type) {
            'channels' => $this->searchChannels($query),
            'hashtags' => $this->searchHashtags($query),
            default => $this->searchVideos($query, $filters),
        };

        return Inertia::render('Search', [
            'query' => $query,
            'type' => $type,
            'results' => $results,
            'filters' => $filters,
            'categories' => Category::active()->get(['id', 'name']),
            'seo' => $this->seoService->forSearch($query),
            'bannerAd' => $this->shouldSuppressAds() ? ['enabled' => false] : [
                'enabled' => (bool) Setting::get('search_banner_ad_enabled', false),
                'code' => (string) Setting::get('search_banner_ad_html', ''),
                'image' => (string) Setting::get('search_banner_ad_image', ''),
                'link' => (string) Setting::get('search_banner_ad_link', ''),
                'mobileCode' => (string) Setting::get('search_banner_ad_mobile_html', ''),
                'mobileImage' => (string) Setting::get('search_banner_ad_mobile_image', ''),
                'mobileLink' => (string) Setting::get('search_banner_ad_mobile_link', ''),
            ],
            'sponsoredCards' => $this->shouldSuppressAds() ? [] : SponsoredCard::getForPage('search', auth()->user()?->role ?? 'guest'),
        ]);
    }

    /**
     * @param array{duration?:string,quality?:string,date?:string,category?:string,sort?:string} $filters
     */
    private function searchVideos(?string $query, array $filters = [])
    {
        if (empty($query)) {
            return collect();
        }

        $escapedQuery = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $query);

        // Filters and sort are applied identically whether results come from
        // Scout or the LIKE fallback, so changing search driver cannot silently
        // change what the filter rail does.
        $applyFilters = fn ($q) => $q
            ->when($filters['category'] ?? null, fn ($qq, $cat) => $qq->where('category_id', $cat))
            ->ofDuration($filters['duration'] ?? null)
            ->ofQuality($filters['quality'] ?? null)
            ->uploadedWithin($filters['date'] ?? null);

        // Use Scout search if a real driver is configured, otherwise fallback to LIKE
        $driver = config('scout.driver');
        if ($driver && !in_array($driver, ['database', 'null', 'collection'])) {
            return Video::search($query)
                ->query(function ($q) use ($applyFilters, $filters) {
                    $q = $applyFilters($q->with(['user.channel'])->public()->approved()->processed());

                    // Relevance is Scout's own ordering, so only override it when
                    // the visitor explicitly picked a sort.
                    return ($filters['sort'] ?? '') !== '' ? $q->sortedBy($filters['sort']) : $q;
                })
                ->paginate(24)
                ->withQueryString();
        }

        $builder = Video::query()
            ->with(['user.channel'])
            ->public()
            ->approved()
            ->processed()
            ->where(function ($q) use ($escapedQuery, $query) {
                $q->where('title', 'like', "%{$escapedQuery}%")
                  ->orWhere('description', 'like', "%{$escapedQuery}%")
                  ->orWhereJsonContains('tags', $query);
            });

        return $applyFilters($builder)
            ->sortedBy($filters['sort'] ?? null)
            ->paginate(24)
            ->withQueryString();
    }

    private function searchChannels(?string $query)
    {
        if (empty($query)) {
            return collect();
        }

        $escapedQuery = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $query);

        return User::query()
            ->with('channel')
            ->where(function ($q) use ($escapedQuery) {
                $q->where('username', 'like', "%{$escapedQuery}%")
                  ->orWhereHas('channel', fn($sub) => $sub->where('name', 'like', "%{$escapedQuery}%"));
            })
            ->paginate(24);
    }

    private function searchHashtags(?string $query)
    {
        if (empty($query)) {
            return collect();
        }

        $escapedQuery = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $query);

        return Hashtag::query()
            ->where('name', 'like', "%{$escapedQuery}%")
            ->orderByDesc('usage_count')
            ->paginate(24);
    }

    /**
     * Live search autocomplete suggestions.
     * Returns top videos + channels for the navbar dropdown.
     */
    public function suggest(Request $request): \Illuminate\Http\JsonResponse
    {
        $query = $request->get('q', '');
        if (empty($query) || strlen($query) < 2) {
            return response()->json(['videos' => [], 'channels' => []]);
        }

        $escapedQuery = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $query);
        $driver = config('scout.driver');
        $useScout = $driver && !in_array($driver, ['database', 'null', 'collection']);

        // Videos: top 5
        if ($useScout) {
            $videos = Video::search($query)
                ->query(fn($q) => $q->with(['user'])->public()->approved()->processed())
                ->take(5)
                ->get();
        } else {
            $videos = Video::query()
                ->with(['user'])
                ->public()
                ->approved()
                ->processed()
                ->where(function ($q) use ($escapedQuery, $query) {
                    $q->where('title', 'like', "%{$escapedQuery}%")
                      ->orWhere('description', 'like', "%{$escapedQuery}%")
                      ->orWhereJsonContains('tags', $query);
                })
                ->latest('published_at')
                ->limit(5)
                ->get();
        }

        // Channels: top 3
        $channels = User::query()
            ->with('channel')
            ->where(function ($q) use ($escapedQuery) {
                $q->where('username', 'like', "%{$escapedQuery}%")
                  ->orWhereHas('channel', fn($sub) => $sub->where('name', 'like', "%{$escapedQuery}%"));
            })
            ->limit(3)
            ->get();

        return response()->json([
            'videos' => $videos->map(fn($v) => [
                'id' => $v->id,
                'slug' => $v->slug,
                'title' => $v->title,
                'thumbnail_url' => $v->thumbnail_url ?? $v->thumbnail,
                'duration_formatted' => $v->formatted_duration ?? null,
                'username' => $v->user?->username,
            ]),
            'channels' => $channels->map(fn($u) => [
                'id' => $u->id,
                'username' => $u->username,
                'avatar_url' => $u->avatar_url ?? $u->avatar,
                'channel_name' => $u->channel?->name ?? $u->username,
            ]),
        ]);
    }
}

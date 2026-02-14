<?php

namespace App\Http\Controllers;

use App\Models\Hashtag;
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

    public function index(Request $request): Response
    {
        $query = $request->get('q', '');
        $type = $request->get('type', 'videos');

        $results = match ($type) {
            'videos' => $this->searchVideos($query),
            'channels' => $this->searchChannels($query),
            'hashtags' => $this->searchHashtags($query),
            default => $this->searchVideos($query),
        };

        return Inertia::render('Search', [
            'query' => $query,
            'type' => $type,
            'results' => $results,
            'seo' => $this->seoService->forSearch($query),
        ]);
    }

    private function searchVideos(string $query)
    {
        if (empty($query)) {
            return collect();
        }

        $escapedQuery = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $query);

        // Use Scout search if a real driver is configured, otherwise fallback to LIKE
        $driver = config('scout.driver');
        if ($driver && !in_array($driver, ['database', 'null', 'collection'])) {
            return Video::search($query)
                ->query(fn($q) => $q->with('user')->public()->approved()->processed())
                ->paginate(24);
        }

        return Video::query()
            ->with('user')
            ->public()
            ->approved()
            ->processed()
            ->where(function ($q) use ($escapedQuery, $query) {
                $q->where('title', 'like', "%{$escapedQuery}%")
                  ->orWhere('description', 'like', "%{$escapedQuery}%")
                  ->orWhereJsonContains('tags', $query);
            })
            ->latest('published_at')
            ->paginate(24);
    }

    private function searchChannels(string $query)
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

    private function searchHashtags(string $query)
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
}

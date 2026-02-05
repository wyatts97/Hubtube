<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Video;
use App\Models\Hashtag;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SearchController extends Controller
{
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
        ]);
    }

    private function searchVideos(string $query)
    {
        if (empty($query)) {
            return collect();
        }

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
            ->where(function ($q) use ($query) {
                $q->where('title', 'like', "%{$query}%")
                  ->orWhere('description', 'like', "%{$query}%")
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

        return User::query()
            ->with('channel')
            ->where('username', 'like', "%{$query}%")
            ->orWhereHas('channel', fn($q) => $q->where('name', 'like', "%{$query}%"))
            ->paginate(24);
    }

    private function searchHashtags(string $query)
    {
        if (empty($query)) {
            return collect();
        }

        return Hashtag::query()
            ->where('name', 'like', "%{$query}%")
            ->orderByDesc('usage_count')
            ->paginate(24);
    }
}

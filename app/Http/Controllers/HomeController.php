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

        $featuredVideos = Video::query()
            ->with('user')
            ->featured()
            ->public()
            ->approved()
            ->processed()
            ->latest('published_at')
            ->limit(8)
            ->get();

        $latestVideos = Video::query()
            ->with('user')
            ->public()
            ->approved()
            ->processed()
            ->latest('published_at')
            ->paginate($perPage);

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

        return Inertia::render('Home', [
            'featuredVideos' => $featuredVideos,
            'latestVideos' => $latestVideos,
            'popularVideos' => $popularVideos,
            'liveStreams' => $liveStreams,
            'categories' => $categories,
        ]);
    }

    public function loadMoreVideos(Request $request): JsonResponse
    {
        $perPage = Setting::get('videos_per_page', 24);
        $page = $request->input('page', 1);

        $videos = Video::query()
            ->with('user')
            ->public()
            ->approved()
            ->processed()
            ->latest('published_at')
            ->paginate($perPage, ['*'], 'page', $page);

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
}

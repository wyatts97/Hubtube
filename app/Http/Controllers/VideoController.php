<?php

namespace App\Http\Controllers;

use App\Http\Requests\Video\StoreVideoRequest;
use App\Http\Requests\Video\UpdateVideoRequest;
use App\Models\Video;
use App\Models\Category;
use App\Models\Setting;
use App\Services\VideoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class VideoController extends Controller
{
    public function __construct(
        protected VideoService $videoService
    ) {}

    public function index(Request $request): Response
    {
        $videos = Video::query()
            ->with(['user', 'category'])
            ->public()
            ->approved()
            ->processed()
            ->when($request->category, fn($q, $cat) => $q->where('category_id', $cat))
            ->when($request->search, fn($q, $search) => $q->where('title', 'like', "%{$search}%"))
            ->when(
                $request->sort === 'popular',
                fn($q) => $q->orderByDesc('views_count'),
                fn($q) => $q->when(
                    $request->sort === 'oldest',
                    fn($q) => $q->oldest('published_at'),
                    fn($q) => $q->latest('published_at')
                )
            )
            ->paginate(24);

        return Inertia::render('Videos/Index', [
            'videos' => $videos,
            'categories' => Category::active()->get(),
            'filters' => $request->only(['category', 'search']),
        ]);
    }

    public function show(Video $video): Response
    {
        if (!$video->isAccessibleBy(auth()->user())) {
            abort(403);
        }

        $video->load(['user.channel', 'category']);
        $video->incrementViews();

        $relatedVideos = Video::query()
            ->with('user')
            ->where('id', '!=', $video->id)
            ->where('category_id', $video->category_id)
            ->public()
            ->approved()
            ->processed()
            ->limit(12)
            ->get();

        // Get sidebar ad settings
        $sidebarAd = [
            'enabled' => Setting::get('video_sidebar_ad_enabled', false),
            'code' => Setting::get('video_sidebar_ad_code', ''),
        ];

        // Get user's playlists with flag indicating if this video is already in each
        $userPlaylists = [];
        if (auth()->check()) {
            $userPlaylists = auth()->user()->playlists()
                ->select('id', 'title', 'slug')
                ->withCount('videos')
                ->get()
                ->map(function ($playlist) use ($video) {
                    $playlist->has_video = $playlist->videos()->where('video_id', $video->id)->exists();
                    return $playlist;
                });
        }

        return Inertia::render('Videos/Show', [
            'video' => $video,
            'relatedVideos' => $relatedVideos,
            'userLike' => auth()->check() 
                ? $video->likes()->where('user_id', auth()->id())->first()?->type 
                : null,
            'isSubscribed' => auth()->check() 
                ? auth()->user()->isSubscribedTo($video->user) 
                : false,
            'sidebarAd' => $sidebarAd,
            'userPlaylists' => $userPlaylists,
        ]);
    }

    public function create(): Response
    {
        Gate::authorize('upload-video');

        return Inertia::render('Videos/Create', [
            'categories' => Category::active()->get(),
        ]);
    }

    public function store(StoreVideoRequest $request): RedirectResponse
    {
        Gate::authorize('upload-video');

        $video = $this->videoService->create($request->validated(), $request->user());

        return redirect()
            ->route('videos.edit', $video)
            ->with('success', 'Video uploaded! Processing will begin shortly.');
    }

    public function edit(Video $video): Response
    {
        $this->authorize('update', $video);

        return Inertia::render('Videos/Edit', [
            'video' => $video,
            'categories' => Category::active()->get(),
        ]);
    }

    public function update(UpdateVideoRequest $request, Video $video): RedirectResponse
    {
        $this->authorize('update', $video);

        $this->videoService->update($video, $request->validated());

        return back()->with('success', 'Video updated successfully.');
    }

    public function destroy(Video $video): RedirectResponse
    {
        $this->authorize('delete', $video);

        $this->videoService->delete($video);

        return redirect()
            ->route('dashboard.videos')
            ->with('success', 'Video deleted successfully.');
    }

    public function processingStatus(Video $video): JsonResponse
    {
        $this->authorize('update', $video);

        $thumbnails = [];
        if ($video->video_path) {
            $dir = dirname(Storage::disk('public')->path($video->video_path)) . '/processed';
            for ($i = 0; $i < 4; $i++) {
                $thumbPath = "{$dir}/thumb_{$i}.jpg";
                if (file_exists($thumbPath)) {
                    $relative = str_replace(Storage::disk('public')->path(''), '', $thumbPath);
                    $thumbnails[] = asset('storage/' . $relative);
                }
            }
        }

        return response()->json([
            'status' => $video->status,
            'thumbnail_url' => $video->thumbnail_url,
            'thumbnails' => $thumbnails,
            'qualities_available' => $video->qualities_available,
        ]);
    }

    public function selectThumbnail(Request $request, Video $video): JsonResponse
    {
        $this->authorize('update', $video);

        $request->validate(['index' => 'required|integer|min:0|max:3']);

        $index = $request->input('index');
        $dir = dirname(Storage::disk('public')->path($video->video_path)) . '/processed';
        $thumbPath = "{$dir}/thumb_{$index}.jpg";

        if (!file_exists($thumbPath)) {
            return response()->json(['error' => 'Thumbnail not found'], 404);
        }

        $relative = str_replace(Storage::disk('public')->path(''), '', $thumbPath);
        $video->update(['thumbnail' => $relative]);

        return response()->json([
            'thumbnail_url' => asset('storage/' . $relative),
        ]);
    }
}

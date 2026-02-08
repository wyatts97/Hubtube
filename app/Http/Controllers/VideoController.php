<?php

namespace App\Http\Controllers;

use App\Http\Requests\Video\StoreVideoRequest;
use App\Http\Requests\Video\UpdateVideoRequest;
use App\Models\Video;
use App\Models\Category;
use App\Models\Setting;
use App\Services\StorageManager;
use App\Services\VideoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
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

        // Banner ads above/below player
        $bannerAbovePlayer = [
            'enabled' => (bool) Setting::get('banner_above_player_enabled', false),
            'type' => Setting::get('banner_above_player_type', 'html'),
            'html' => Setting::get('banner_above_player_html', ''),
            'image' => Setting::get('banner_above_player_image', ''),
            'link' => Setting::get('banner_above_player_link', ''),
            'mobile_type' => Setting::get('banner_above_player_mobile_type', 'html'),
            'mobile_html' => Setting::get('banner_above_player_mobile_html', ''),
            'mobile_image' => Setting::get('banner_above_player_mobile_image', ''),
            'mobile_link' => Setting::get('banner_above_player_mobile_link', ''),
        ];

        $bannerBelowPlayer = [
            'enabled' => (bool) Setting::get('banner_below_player_enabled', false),
            'type' => Setting::get('banner_below_player_type', 'html'),
            'html' => Setting::get('banner_below_player_html', ''),
            'image' => Setting::get('banner_below_player_image', ''),
            'link' => Setting::get('banner_below_player_link', ''),
            'mobile_type' => Setting::get('banner_below_player_mobile_type', 'html'),
            'mobile_html' => Setting::get('banner_below_player_mobile_html', ''),
            'mobile_image' => Setting::get('banner_below_player_mobile_image', ''),
            'mobile_link' => Setting::get('banner_below_player_mobile_link', ''),
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
            'bannerAbovePlayer' => $bannerAbovePlayer,
            'bannerBelowPlayer' => $bannerBelowPlayer,
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

        // Admin/Pro users go to the full edit page; default users go to the status page
        if ($request->user()->canEditVideo()) {
            return redirect()
                ->route('videos.edit', $video)
                ->with('success', 'Video uploaded! Processing will begin shortly.');
        }

        return redirect()
            ->route('videos.status', $video)
            ->with('success', 'Video uploaded! It will be published after processing and moderation.');
    }

    public function edit(Video $video): Response
    {
        $this->authorize('update', $video);

        return Inertia::render('Videos/Edit', [
            'video' => $video,
            'categories' => Category::active()->get(),
        ]);
    }

    public function status(Video $video): Response
    {
        $this->authorize('viewStatus', $video);

        return Inertia::render('Videos/Status', [
            'video' => $video,
            'canEdit' => auth()->user()->canEditVideo(),
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
            ->route('dashboard')
            ->with('success', 'Video deleted successfully.');
    }

    public function processingStatus(Video $video): JsonResponse
    {
        $this->authorize('viewStatus', $video);

        $thumbnails = [];
        $slugTitle = Str::slug($video->title, '_') ?: 'video';
        $videoDir = "videos/{$video->slug}";
        $count = (int) Setting::get('thumbnail_count', 4);

        // During processing, files are always on local disk
        // After cloud offload, storage_disk changes — but thumbnails are checked on both
        for ($i = 0; $i < $count; $i++) {
            $thumbRelative = "{$videoDir}/{$slugTitle}_thumb_{$i}.jpg";

            // Check local disk first (processing happens locally)
            $localPath = Storage::disk('public')->path($thumbRelative);
            if (file_exists($localPath)) {
                $thumbnails[] = asset('storage/' . $thumbRelative);
            } elseif ($video->storage_disk && $video->storage_disk !== 'public') {
                // After cloud offload, check cloud disk
                if (StorageManager::exists($thumbRelative, $video->storage_disk)) {
                    $thumbnails[] = StorageManager::url($thumbRelative, $video->storage_disk);
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

        $count = (int) Setting::get('thumbnail_count', 4);
        $request->validate(['index' => "required|integer|min:0|max:" . ($count - 1)]);

        $index = $request->input('index');
        $slugTitle = Str::slug($video->title, '_') ?: 'video';
        $videoDir = "videos/{$video->slug}";
        $thumbRelative = "{$videoDir}/{$slugTitle}_thumb_{$index}.jpg";

        // Check local first, then cloud
        $localPath = Storage::disk('public')->path($thumbRelative);
        $disk = $video->storage_disk ?? 'public';

        if (!file_exists($localPath) && !StorageManager::exists($thumbRelative, $disk)) {
            return response()->json(['error' => 'Thumbnail not found'], 404);
        }

        $video->update(['thumbnail' => $thumbRelative]);

        // Return URL from whichever disk has the file
        if ($disk !== 'public' && StorageManager::exists($thumbRelative, $disk)) {
            $url = StorageManager::url($thumbRelative, $disk);
        } else {
            $url = asset('storage/' . $thumbRelative);
        }

        return response()->json([
            'thumbnail_url' => $url,
        ]);
    }
}

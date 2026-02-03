<?php

namespace App\Http\Controllers;

use App\Http\Requests\Video\StoreVideoRequest;
use App\Http\Requests\Video\UpdateVideoRequest;
use App\Models\Video;
use App\Models\Category;
use App\Services\VideoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
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
            ->when($request->sort === 'popular', fn($q) => $q->orderByDesc('views_count'))
            ->when($request->sort === 'oldest', fn($q) => $q->oldest('published_at'))
            ->when($request->search, fn($q, $search) => $q->where('title', 'like', "%{$search}%"))
            ->latest('published_at')
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
            ->where('id', '!=', $video->id)
            ->where('category_id', $video->category_id)
            ->public()
            ->approved()
            ->processed()
            ->limit(12)
            ->get();

        return Inertia::render('Videos/Show', [
            'video' => $video,
            'relatedVideos' => $relatedVideos,
            'userLike' => auth()->check() 
                ? $video->likes()->where('user_id', auth()->id())->first()?->type 
                : null,
            'isSubscribed' => auth()->check() 
                ? auth()->user()->isSubscribedTo($video->user) 
                : false,
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
}

<?php

namespace App\Http\Controllers;

use App\Models\Playlist;
use App\Models\Video;
use App\Services\SeoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class PlaylistController extends Controller
{
    public function __construct(
        protected SeoService $seoService,
    ) {}

    public function index(Request $request): Response
    {
        $playlists = $request->user()
            ->playlists()
            ->withCount('videos')
            ->latest()
            ->paginate(24);

        return Inertia::render('Playlists', [
            'playlists' => $playlists,
        ]);
    }

    public function show(Playlist $playlist): Response
    {
        if (!$this->canView($playlist)) {
            abort(403);
        }

        $playlist->load(['user', 'videos.user']);
        $playlist->loadCount(['videos', 'favoritedBy']);

        return Inertia::render('Playlists/Show', [
            'playlist' => $playlist,
            'isFavorited' => auth()->check() ? $playlist->isFavoritedBy(auth()->user()) : false,
            'seo' => $this->seoService->forPlaylist($playlist),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:150',
            'description' => 'nullable|string|max:5000',
        ]);

        $playlist = $request->user()->playlists()->create([
            ...$validated,
            'privacy' => 'public',
            'slug' => Str::slug($validated['title']) . '-' . Str::random(8),
        ]);

        return response()->json($playlist, 201);
    }

    public function update(Request $request, Playlist $playlist): JsonResponse
    {
        $this->authorize('update', $playlist);

        $validated = $request->validate([
            'title' => 'required|string|max:150',
            'description' => 'nullable|string|max:5000',
        ]);

        $playlist->update($validated);

        return response()->json($playlist);
    }

    public function destroy(Playlist $playlist): RedirectResponse
    {
        $this->authorize('delete', $playlist);

        $playlist->delete();

        return redirect()
            ->route('playlists.index')
            ->with('success', 'Playlist deleted.');
    }

    public function addVideo(Request $request, Playlist $playlist): JsonResponse
    {
        $this->authorize('update', $playlist);

        $validated = $request->validate([
            'video_id' => 'required|exists:videos,id',
        ]);

        $video = Video::findOrFail($validated['video_id']);

        // Verify the video is accessible (public or owned by user)
        if (!$video->isAccessibleBy($request->user())) {
            return response()->json(['error' => 'Video is not accessible'], 403);
        }

        if (!$playlist->videos()->where('video_id', $video->id)->exists()) {
            $playlist->addVideo($video);
        }

        return response()->json(['success' => true]);
    }

    public function removeVideo(Request $request, Playlist $playlist): JsonResponse
    {
        $this->authorize('update', $playlist);

        $validated = $request->validate([
            'video_id' => 'required|exists:videos,id',
        ]);

        $video = Video::findOrFail($validated['video_id']);
        $playlist->removeVideo($video);

        return response()->json(['success' => true]);
    }

    public function toggleFavorite(Request $request, Playlist $playlist): JsonResponse
    {
        if (!$this->canView($playlist)) {
            return response()->json(['error' => 'Playlist not accessible'], 403);
        }

        // Don't allow favoriting own playlists
        if ($playlist->user_id === $request->user()->id) {
            return response()->json(['error' => 'Cannot favorite your own playlist'], 422);
        }

        $user = $request->user();
        $isFavorited = $playlist->isFavoritedBy($user);

        if ($isFavorited) {
            $user->favoritePlaylists()->detach($playlist->id);
        } else {
            $user->favoritePlaylists()->attach($playlist->id);
        }

        return response()->json([
            'isFavorited' => !$isFavorited,
            'favoritesCount' => $playlist->favoritedBy()->count(),
        ]);
    }

    private function canView(Playlist $playlist): bool
    {
        return true;
    }
}

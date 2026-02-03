<?php

namespace App\Http\Controllers;

use App\Models\Playlist;
use App\Models\Video;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class PlaylistController extends Controller
{
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

        return Inertia::render('Playlists/Show', [
            'playlist' => $playlist,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:150',
            'description' => 'nullable|string|max:5000',
            'privacy' => 'required|in:public,private,unlisted',
        ]);

        $playlist = $request->user()->playlists()->create([
            ...$validated,
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
            'privacy' => 'required|in:public,private,unlisted',
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

    private function canView(Playlist $playlist): bool
    {
        if ($playlist->privacy === 'public') {
            return true;
        }

        if (!auth()->check()) {
            return false;
        }

        return $playlist->user_id === auth()->id();
    }
}

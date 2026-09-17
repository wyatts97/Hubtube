<?php

namespace App\Http\Controllers;

use App\Models\Playlist;
use App\Models\SponsoredCard;
use App\Models\Video;
use App\Services\SeoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class PlaylistController extends Controller
{
    public function __construct(
        protected SeoService $seoService,
    ) {}

    public function index(Request $request): Response
    {
        $user = $request->user();

        // Every account has a Watch Later list; this is where it comes into
        // existence for accounts that predate the feature.
        Playlist::watchLaterFor($user);

        $playlists = $user->playlists()
            ->withCount('videos')
            ->orderByDesc('is_default')
            ->latest()
            ->paginate(24);

        return Inertia::render('Playlists', [
            'playlists' => $playlists,
            'privacyOptions' => Playlist::PRIVACIES,
        ]);
    }

    public function publicIndex(Request $request): Response
    {
        $sort = $request->query('sort', 'newest');

        // Public only: this listing used to include unlisted and private
        // playlists, which put someone's private list of videos on a browsable
        // page.
        $query = Playlist::query()
            ->public()
            ->with('user:id,username')
            ->where('video_count', '>', 0)
            ->withCount(['videos', 'favoritedBy']);

        switch ($sort) {
            case 'popular':
                $query->orderByDesc('favorited_by_count');
                break;
            case 'oldest':
                $query->orderBy('created_at', 'asc');
                break;
            case 'newest':
            default:
                $query->orderByDesc('created_at');
                break;
        }

        return Inertia::render('PublicPlaylists', [
            'playlists' => $query->paginate(24),
            'currentSort' => $sort,
            'seo' => $this->seoService->forPublicPlaylists($sort),
        ]);
    }

    public function show(Request $request, Playlist $playlist): Response
    {
        // 404 rather than 403: a private playlist should not confirm that it
        // exists to someone who cannot open it.
        abort_unless($playlist->isVisibleTo($request->user()), 404);

        $playlist->load(['user', 'videos.user']);
        $playlist->loadCount(['videos', 'favoritedBy']);

        return Inertia::render('Playlists/Show', [
            'playlist' => $playlist,
            'isFavorited' => $request->user() ? $playlist->isFavoritedBy($request->user()) : false,
            'canEdit' => $request->user()?->can('update', $playlist) ?? false,
            'seo' => $this->seoService->forPlaylist($playlist),
            'adSettings' => $this->gridAdSettings(),
            'sponsoredCards' => $this->shouldSuppressAds() ? [] : SponsoredCard::getForPage('playlist', $this->adTargetRole()),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:150',
            'description' => 'nullable|string|max:5000',
            'privacy' => ['nullable', Rule::in(Playlist::PRIVACIES)],
        ]);

        $playlist = $request->user()->playlists()->create([
            ...$validated,
            'privacy' => $validated['privacy'] ?? 'public',
            'slug' => Str::slug($validated['title']).'-'.Str::random(8),
        ]);

        return response()->json($playlist, 201);
    }

    public function update(Request $request, Playlist $playlist): JsonResponse
    {
        $this->authorize('update', $playlist);

        $validated = $request->validate([
            'title' => 'required|string|max:150',
            'description' => 'nullable|string|max:5000',
            'privacy' => ['nullable', Rule::in(Playlist::PRIVACIES)],
        ]);

        // Watch Later keeps its name — the sidebar and the save menu refer to
        // it by that title — but its privacy and description are the owner's.
        if ($playlist->is_default) {
            unset($validated['title']);
        }

        $playlist->update(array_filter(
            $validated,
            fn ($value) => $value !== null,
        ));

        return response()->json($playlist->fresh());
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
        if (! $video->isAccessibleBy($request->user())) {
            return response()->json(['error' => 'Video is not accessible'], 403);
        }

        if (! $playlist->videos()->where('video_id', $video->id)->exists()) {
            $playlist->addVideo($video);
        }

        return response()->json([
            'success' => true,
            'video_count' => $playlist->fresh()->video_count,
        ]);
    }

    public function removeVideo(Request $request, Playlist $playlist): JsonResponse
    {
        $this->authorize('update', $playlist);

        $validated = $request->validate([
            'video_id' => 'required|exists:videos,id',
        ]);

        $video = Video::findOrFail($validated['video_id']);

        // Only decrement for a video that was actually in the list, or a
        // double-submit would walk video_count below the real total.
        if ($playlist->videos()->where('video_id', $video->id)->exists()) {
            $playlist->removeVideo($video);
        }

        return response()->json([
            'success' => true,
            'video_count' => $playlist->fresh()->video_count,
        ]);
    }

    /**
     * Save a new running order for the playlist.
     *
     * The whole order is sent rather than a single moved item: drag-and-drop
     * produces the final list anyway, and one write per reorder is easier to
     * reason about than a shuffle of neighbouring positions.
     */
    public function reorder(Request $request, Playlist $playlist): JsonResponse
    {
        $this->authorize('update', $playlist);

        $validated = $request->validate([
            'video_ids' => 'required|array|min:1',
            'video_ids.*' => 'integer',
        ]);

        $playlist->reorder($validated['video_ids']);

        return response()->json(['success' => true]);
    }

    /**
     * Add or remove a video from the viewer's Watch Later list.
     *
     * Its own endpoint because it is the one playlist a viewer should be able
     * to use without first having made a playlist.
     */
    public function toggleWatchLater(Request $request, Video $video): JsonResponse
    {
        if (! $video->isAccessibleBy($request->user())) {
            return response()->json(['error' => 'Video is not accessible'], 403);
        }

        $playlist = Playlist::watchLaterFor($request->user());
        $saved = $playlist->videos()->where('video_id', $video->id)->exists();

        $saved
            ? $playlist->removeVideo($video)
            : $playlist->addVideo($video);

        return response()->json([
            'saved' => ! $saved,
            'playlist_id' => $playlist->id,
            'video_count' => $playlist->fresh()->video_count,
        ]);
    }

    public function toggleFavorite(Request $request, Playlist $playlist): JsonResponse
    {
        if (! $playlist->isVisibleTo($request->user())) {
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
            'isFavorited' => ! $isFavorited,
            'favoritesCount' => $playlist->favoritedBy()->count(),
        ]);
    }
}

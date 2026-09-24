<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Video;
use App\Rules\KnownTags;
use App\Services\TagSyncService;
use App\Services\VideoAnalytics;
use App\Support\VideoPrivacy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Creator Studio: one place to manage everything you have uploaded.
 *
 * The dashboard answers "how am I doing?"; this answers "where is that video
 * and what do I need to change about it". Before this existed the only list of
 * a creator's own videos was the ten most recent on the dashboard, and the
 * "Manage" link next to it went to the account settings page.
 */
class StudioController extends Controller
{
    /** Videos per page in the manager. */
    public const PER_PAGE = 20;

    public function __construct(
        protected TagSyncService $tagSync,
        protected VideoAnalytics $analytics,
    ) {}

    public function videos(Request $request): Response
    {
        $user = $request->user();

        $filters = [
            'q' => trim((string) $request->query('q', '')),
            'status' => $request->query('status', ''),
            'privacy' => $request->query('privacy', ''),
            'category' => $request->query('category', ''),
            'sort' => $request->query('sort', 'newest'),
        ];

        $videos = $user->videos()
            ->with('category:id,name')
            ->when($filters['q'] !== '', function ($query) use ($filters) {
                $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $filters['q']);
                $query->where('title', 'like', "%{$escaped}%");
            })
            ->when(
                in_array($filters['status'], Video::STUDIO_STATUSES, true),
                fn ($query) => $query->studioStatus($filters['status'])
            )
            ->when(
                in_array($filters['privacy'], ['public', 'unlisted', 'private'], true),
                fn ($query) => $query->where('privacy', $filters['privacy'])
            )
            ->when($filters['category'] !== '', fn ($query) => $query->where('category_id', $filters['category']))
            ->orderBy(...$this->ordering($filters['sort']))
            ->paginate(self::PER_PAGE)
            ->withQueryString()
            ->through(fn (Video $video) => [
                'id' => $video->id,
                'title' => $video->title,
                'slug' => $video->slug,
                'thumbnail_url' => $video->thumbnail_url,
                'duration' => $video->formatted_duration,
                'privacy' => $video->privacy,
                'studio_status' => $video->studioStatus(),
                'views_count' => (int) $video->views_count,
                'likes_count' => (int) $video->likes_count,
                'comments_count' => (int) $video->comments_count,
                'category' => $video->category?->name,
                'created_at' => $video->created_at?->toIso8601String(),
                'scheduled_at' => $video->scheduled_at?->toIso8601String(),
                'can_edit' => $request->user()->can('update', $video),
            ]);

        return Inertia::render('Studio/Videos', [
            'videos' => $videos,
            'filters' => $filters,
            'statuses' => Video::STUDIO_STATUSES,
            'categories' => Category::active()->get(['id', 'name']),
            // The same two admin switches that gate the upload form decide what
            // a bulk privacy change may set things to.
            'privacyOptions' => VideoPrivacy::allowedFor($user),
            'counts' => $this->statusCounts($request),
        ]);
    }

    /**
     * Apply one action to a set of the creator's videos.
     *
     * Ownership is re-checked here from the ids rather than trusted from the
     * page: the list is filtered server-side, but the ids come back from the
     * browser.
     */
    public function bulk(Request $request): RedirectResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'action' => ['required', Rule::in(['privacy', 'category', 'tags', 'delete'])],
            'video_ids' => 'required|array|min:1|max:100',
            'video_ids.*' => 'integer',
            'privacy' => ['required_if:action,privacy', Rule::in(VideoPrivacy::allowedFor($user))],
            'category_id' => ['nullable', 'required_if:action,category', 'exists:categories,id'],
            'tags' => ['required_if:action,tags', 'array', 'max:20', new KnownTags],
            'tags.*' => 'string|max:50',
        ]);

        // Category and tags are metadata edits, which VideoPolicy::update
        // limits to users who may edit videos; privacy and delete are not.
        if (in_array($validated['action'], ['category', 'tags'], true) && ! $user->is_admin && ! $user->canEditVideo()) {
            abort(403);
        }

        $videos = $user->videos()->whereIn('id', $validated['video_ids'])->get();

        if ($videos->isEmpty()) {
            return back()->with('error', __('Nothing was selected.'));
        }

        foreach ($videos as $video) {
            match ($validated['action']) {
                'privacy' => $video->update(['privacy' => $validated['privacy']]),
                'category' => $video->update(['category_id' => $validated['category_id']]),
                'tags' => $this->addTags($video, $validated['tags']),
                'delete' => $video->delete(),
            };
        }

        return back()->with('success', trans_choice(
            '{1} :count video updated.|[2,*] :count videos updated.',
            $videos->count(),
            ['count' => $videos->count()],
        ));
    }

    public function analytics(Request $request, Video $video): Response
    {
        // viewStatus, not update: editing a video's metadata is a pro/admin
        // right, but seeing how your own upload is doing is not.
        $this->authorize('viewStatus', $video);

        return Inertia::render('Studio/Analytics', [
            'video' => [
                'id' => $video->id,
                'title' => $video->title,
                'slug' => $video->slug,
                'thumbnail_url' => $video->thumbnail_url,
                'duration' => $video->formatted_duration,
                'studio_status' => $video->studioStatus(),
                'published_at' => $video->published_at?->toIso8601String(),
            ],
            'analytics' => $this->analytics->for($video, (int) $request->query('days', VideoAnalytics::DEFAULT_RANGE)),
            'retentionDays' => (int) config('hubtube.video.view_log_retention_days', 90),
        ]);
    }

    /**
     * Add tags to a video without discarding the ones already on it.
     *
     * Bulk tagging is additive on purpose: the alternative — replacing the tag
     * list on twenty videos at once — is a destructive action disguised as a
     * convenience.
     */
    protected function addTags(Video $video, array $tags): void
    {
        $merged = Video::normalizeTagsInput(array_merge((array) $video->tags, $tags));

        $video->update(['tags' => array_slice($merged, 0, 20)]);
        $this->tagSync->syncVideo($video);
    }

    /** @return array{0: string, 1: string} column and direction for orderBy */
    protected function ordering(string $sort): array
    {
        return match ($sort) {
            'oldest' => ['created_at', 'asc'],
            'views' => ['views_count', 'desc'],
            'likes' => ['likes_count', 'desc'],
            'comments' => ['comments_count', 'desc'],
            'title' => ['title', 'asc'],
            default => ['created_at', 'desc'],
        };
    }

    /**
     * How many of the creator's videos are in each state, for the filter chips.
     *
     * One indexed COUNT per state rather than a single pass of conditional
     * sums: the sums would restate every rule in scopeStudioStatus in raw SQL,
     * which is exactly the drift those scopes exist to prevent. All of them
     * ride the videos(user_id, created_at) index.
     *
     * @return array<string, int>
     */
    protected function statusCounts(Request $request): array
    {
        $counts = ['all' => $request->user()->videos()->count()];

        foreach (Video::STUDIO_STATUSES as $status) {
            $counts[$status] = $request->user()->videos()->studioStatus($status)->count();
        }

        return $counts;
    }
}

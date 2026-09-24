<?php

namespace App\Http\Controllers;

use App\Models\Comment;
use App\Models\CommentLike;
use App\Models\Setting;
use App\Models\User;
use App\Models\Video;
use App\Notifications\CommentReplyNotification;
use App\Notifications\NewCommentNotification;
use App\Services\CommentFilter;
use App\Services\CommentMentions;
use App\Services\PointsService;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class CommentController extends Controller
{
    /** Top-level comments per page. */
    public const PER_PAGE = 20;

    /** Replies shown under a comment before "show more replies". */
    public const REPLY_PREVIEW = 3;

    /** Replies fetched per "show more replies". */
    public const REPLIES_PER_PAGE = 10;

    public function __construct(
        protected CommentFilter $filter,
        protected CommentMentions $mentions,
    ) {}

    /**
     * One page of top-level comments, each with the first few replies.
     *
     * The whole comment tree used to be returned in a single response, which
     * meant a popular video shipped thousands of rows — each one carrying a
     * full serialised User, email address included. The payload is now shaped
     * explicitly and paginated.
     */
    public function index(Request $request, Video $video): JsonResponse
    {
        $viewer = $request->user();

        // A private, draft or unapproved video's comments are as private as the video.
        if (! $video->isViewableBy($viewer)) {
            abort(404);
        }

        if (! self::commentsEnabled()) {
            return response()->json([
                'comments' => [],
                'meta' => ['current_page' => 1, 'last_page' => 1, 'total' => 0],
            ]);
        }

        $comments = $video->comments()
            ->with([
                'user',
                'replies' => fn ($query) => $query
                    ->with('user')
                    ->visibleTo($viewer)
                    ->oldest()
                    ->limit(self::REPLY_PREVIEW),
            ])
            ->withCount(['replies' => fn ($query) => $query->visibleTo($viewer)])
            ->topLevel()
            ->visibleTo($viewer)
            ->orderByDesc('is_pinned')
            ->latest()
            ->paginate(self::PER_PAGE);

        return response()->json([
            'comments' => $this->shapeTree($comments->getCollection(), $video, $viewer),
            'meta' => [
                'current_page' => $comments->currentPage(),
                'last_page' => $comments->lastPage(),
                'total' => $comments->total(),
            ],
        ]);
    }

    /**
     * Further replies under one comment, continuing from $after.
     *
     * Keyset rather than page numbers: replies are appended to a list the
     * viewer is already reading, and a new reply arriving mid-read would shift
     * every offset-based page by one.
     */
    public function replies(Request $request, Comment $comment): JsonResponse
    {
        $viewer = $request->user();
        $after = (int) $request->query('after', 0);

        $comment->loadMissing('video');
        $video = $comment->video;

        if (! $video || ! $video->isAccessibleBy($viewer)) {
            abort(404);
        }

        $replies = $comment->replies()
            ->with('user')
            ->visibleTo($viewer)
            ->when($after > 0, fn ($query) => $query->where('id', '>', $after))
            ->oldest()
            ->limit(self::REPLIES_PER_PAGE + 1)
            ->get();

        // One extra row is fetched purely to answer "is there more?" without a
        // second count query.
        $hasMore = $replies->count() > self::REPLIES_PER_PAGE;
        $replies = $replies->take(self::REPLIES_PER_PAGE);

        return response()->json([
            'replies' => $this->shapeMany($replies, $video, $viewer),
            'has_more' => $hasMore,
        ]);
    }

    /** Whether the site is accepting comments at all. */
    public static function commentsEnabled(): bool
    {
        return filter_var(Setting::get('comments_enabled', true), FILTER_VALIDATE_BOOLEAN);
    }

    public function store(Request $request, Video $video): JsonResponse
    {
        abort_unless(self::commentsEnabled(), 403, 'Comments are turned off.');
        abort_unless($video->isViewableBy($request->user()), 404);

        $validated = $request->validate([
            'content' => 'required|string|max:5000',
            // A comment can only reply to a comment on the same video.
            'parent_id' => [
                'nullable',
                Rule::exists('comments', 'id')->where('video_id', $video->id),
            ],
        ]);

        $author = $request->user();
        $parent = $this->threadRoot($validated['parent_id'] ?? null);

        // A comment that trips the spam filter is held for a moderator instead
        // of being refused, so the author is not handed a list of the words
        // that would have worked.
        $holdReason = $this->filter->holdReason($validated['content']);
        $requiresApproval = (bool) Setting::get('comments_require_approval', false);
        $approved = ! $requiresApproval && $holdReason === null;

        $comment = $video->comments()->create([
            'user_id' => $author->id,
            'content' => $validated['content'],
            'parent_id' => $parent?->id,
            'is_approved' => $approved,
        ]);

        // videos.comments_count is maintained by the Comment model itself.
        if ($approved) {
            app(PointsService::class)->awardCommentPoints($author, $comment);
        }

        $comment->setRelation('user', $author);
        $comment->setRelation('video', $video);

        if ($approved) {
            $this->notifyAbout($comment, $video, $parent, $author);
        }

        return response()->json([
            'comment' => $this->shape($comment, $video, $author),
            'pending' => ! $approved,
        ], 201);
    }

    public function update(Request $request, Comment $comment): JsonResponse
    {
        $this->authorize('update', $comment);

        $validated = $request->validate([
            'content' => 'required|string|max:5000',
        ]);

        $comment->loadMissing(['user', 'video']);

        // An edit goes back through the spam filter: otherwise a clean comment
        // could be posted and then edited into anything.
        $holdReason = $this->filter->holdReason($validated['content']);

        $comment->update([
            'content' => $validated['content'],
            'edited_at' => now(),
            'is_approved' => $holdReason === null && $comment->is_approved,
        ]);

        return response()->json([
            'comment' => $this->shape($comment, $comment->video, $request->user()),
            'pending' => ! $comment->is_approved,
        ]);
    }

    public function destroy(Comment $comment): JsonResponse
    {
        // The delete policy also lets the video's owner remove a comment, so
        // the relation has to be there before the check runs.
        $comment->loadMissing('video');

        $this->authorize('delete', $comment);

        // Replies are cascade-deleted by the database on a hard delete, but a
        // soft delete leaves them orphaned and visible, so take them together.
        $comment->replies()->get()->each->delete();
        $comment->delete();

        return response()->json(['success' => true]);
    }

    public function like(Request $request, Comment $comment): JsonResponse
    {
        return $this->react($request, $comment, 'like');
    }

    public function dislike(Request $request, Comment $comment): JsonResponse
    {
        return $this->react($request, $comment, 'dislike');
    }

    /**
     * Apply a like or dislike, treating a repeat of the same one as "undo".
     *
     * Both endpoints always report both counts and both flags, so the caller
     * never has to infer the other side of the pair.
     */
    protected function react(Request $request, Comment $comment, string $type): JsonResponse
    {
        abort_unless($comment->video?->isViewableBy($request->user()), 404);

        return DB::transaction(function () use ($request, $comment, $type) {
            $existing = $comment->likes()
                ->where('user_id', $request->user()->id)
                ->lockForUpdate()
                ->first();

            $liked = false;
            $disliked = false;

            if ($existing && $existing->type === $type) {
                $existing->delete();
                $comment->decrement($type === 'like' ? 'likes_count' : 'dislikes_count');
            } elseif ($existing) {
                $existing->update(['type' => $type]);
                $comment->increment($type === 'like' ? 'likes_count' : 'dislikes_count');
                $comment->decrement($type === 'like' ? 'dislikes_count' : 'likes_count');
                $liked = $type === 'like';
                $disliked = $type === 'dislike';
            } else {
                $comment->likes()->create([
                    'user_id' => $request->user()->id,
                    'type' => $type,
                ]);
                $comment->increment($type === 'like' ? 'likes_count' : 'dislikes_count');
                $liked = $type === 'like';
                $disliked = $type === 'dislike';
            }

            $fresh = $comment->fresh();

            return response()->json([
                'liked' => $liked,
                'disliked' => $disliked,
                'likesCount' => $fresh->likes_count,
                'dislikesCount' => $fresh->dislikes_count,
            ]);
        });
    }

    /**
     * The top-level comment a reply belongs under.
     *
     * Threads stay one level deep: replying to a reply attaches to the same
     * root, the way it reads in the UI, rather than nesting indefinitely.
     */
    protected function threadRoot(?int $parentId): ?Comment
    {
        if (! $parentId) {
            return null;
        }

        $parent = Comment::with('user')->find($parentId);

        if ($parent?->parent_id) {
            $parent = Comment::with('user')->find($parent->parent_id) ?? $parent;
        }

        return $parent;
    }

    /**
     * Tell the people a new comment concerns, each at most once.
     *
     * Order matters: the reply or new-comment notification is sent first, and
     * whoever received it is then excluded from the mention pass so a comment
     * that both replies to and @-names the same person only notifies once.
     */
    protected function notifyAbout(Comment $comment, Video $video, ?Comment $parent, User $author): void
    {
        $notified = [$author->id];

        if ($parent) {
            $parent->loadMissing('user');

            if ($parent->user && $parent->user_id !== $author->id) {
                $parent->user->notify(new CommentReplyNotification($comment, $parent));
                $notified[] = $parent->user_id;
            }
        } elseif ($video->user_id !== $author->id) {
            $video->loadMissing('user');

            if ($video->user) {
                $video->user->notify(new NewCommentNotification($comment));
                $notified[] = $video->user_id;
            }
        }

        $this->mentions->notify($comment, $notified);
    }

    /**
     * Shape a page of comments together with their preview replies.
     *
     * @param  EloquentCollection<int, Comment>  $comments
     */
    protected function shapeTree(EloquentCollection $comments, Video $video, ?User $viewer): array
    {
        $reactions = $this->reactionsFor(
            $comments->pluck('id')->merge($comments->pluck('replies.*.id')->flatten()),
            $viewer
        );

        return $comments->map(function (Comment $comment) use ($video, $viewer, $reactions) {
            $shaped = $this->shape($comment, $video, $viewer, $reactions);
            $shaped['replies'] = $comment->replies
                ->map(fn (Comment $reply) => $this->shape($reply, $video, $viewer, $reactions))
                ->all();
            $shaped['replies_count'] = (int) ($comment->replies_count ?? 0);

            return $shaped;
        })->all();
    }

    /**
     * @param  Collection<int, Comment>  $comments
     */
    protected function shapeMany($comments, Video $video, ?User $viewer): array
    {
        $reactions = $this->reactionsFor($comments->pluck('id'), $viewer);

        return $comments
            ->map(fn (Comment $comment) => $this->shape($comment, $video, $viewer, $reactions))
            ->values()
            ->all();
    }

    /**
     * The viewer's own like/dislike on each of $commentIds, keyed by comment.
     *
     * One query for the page rather than one per comment; guests get nothing,
     * which is also why the flags are always false for them.
     *
     * @return array<int, string>
     */
    protected function reactionsFor($commentIds, ?User $viewer): array
    {
        $ids = collect($commentIds)->filter()->unique();

        if (! $viewer || $ids->isEmpty()) {
            return [];
        }

        return CommentLike::query()
            ->where('user_id', $viewer->id)
            ->whereIn('comment_id', $ids)
            ->pluck('type', 'comment_id')
            ->all();
    }

    /**
     * One comment as the front end needs it.
     *
     * Explicit rather than a serialised model: the User relation carries the
     * commenter's email address and notification preferences, none of which
     * belong in a public comment feed.
     *
     * @param  array<int, string>  $reactions
     */
    protected function shape(Comment $comment, ?Video $video, ?User $viewer, array $reactions = []): array
    {
        $comment->loadMissing('user');

        if ($video) {
            // The delete policy reads $comment->video; setting it here keeps
            // strict mode from tripping over a lazy load per comment.
            $comment->setRelation('video', $video);
        }

        $reaction = $reactions[$comment->id] ?? null;

        return [
            'id' => $comment->id,
            'parent_id' => $comment->parent_id,
            'content' => $comment->content,
            'likes_count' => (int) $comment->likes_count,
            'dislikes_count' => (int) $comment->dislikes_count,
            'is_pinned' => (bool) $comment->is_pinned,
            'is_approved' => (bool) $comment->is_approved,
            'created_at' => $comment->created_at?->toIso8601String(),
            'edited_at' => $comment->edited_at?->toIso8601String(),
            'user_id' => $comment->user_id,
            'user' => $comment->user ? [
                'id' => $comment->user->id,
                'username' => $comment->user->username,
                'avatar_url' => $comment->user->avatar_url,
                'avatar_alt' => $comment->user->avatar_alt,
                'is_pro' => (bool) $comment->user->is_pro,
            ] : null,
            'user_liked' => $reaction === 'like',
            'user_disliked' => $reaction === 'dislike',
            'can_edit' => $viewer !== null && $viewer->id === $comment->user_id,
            'can_delete' => $viewer !== null && $viewer->can('delete', $comment),
        ];
    }
}

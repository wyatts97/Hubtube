<?php

namespace App\Http\Controllers;

use App\Models\Comment;
use App\Models\Video;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CommentController extends Controller
{
    public function index(Video $video): JsonResponse
    {
        $comments = $video->comments()
            ->with(['user', 'replies.user'])
            ->topLevel()
            ->approved()
            ->orderByDesc('is_pinned')
            ->latest()
            ->get();

        return response()->json(['comments' => $comments]);
    }

    public function store(Request $request, Video $video): JsonResponse
    {
        $validated = $request->validate([
            'content' => 'required|string|max:5000',
            'parent_id' => 'nullable|exists:comments,id',
        ]);

        $comment = $video->comments()->create([
            'user_id' => $request->user()->id,
            'content' => $validated['content'],
            'parent_id' => $validated['parent_id'] ?? null,
            'is_approved' => true,
        ]);

        $video->increment('comments_count');

        $comment->load('user');

        return response()->json(['comment' => $comment], 201);
    }

    public function update(Request $request, Comment $comment): JsonResponse
    {
        $this->authorize('update', $comment);

        $validated = $request->validate([
            'content' => 'required|string|max:5000',
        ]);

        $comment->update(['content' => $validated['content']]);

        return response()->json(['comment' => $comment]);
    }

    public function destroy(Comment $comment): JsonResponse
    {
        $this->authorize('delete', $comment);

        $video = $comment->video;
        $comment->delete();
        $video->decrement('comments_count');

        return response()->json(['success' => true]);
    }

    public function like(Request $request, Comment $comment): JsonResponse
    {
        return DB::transaction(function () use ($request, $comment) {
            $existing = $comment->likes()
                ->where('user_id', $request->user()->id)
                ->lockForUpdate()
                ->first();

            if ($existing) {
                if ($existing->type === 'like') {
                    $existing->delete();
                    $comment->decrement('likes_count');
                    return response()->json([
                        'liked' => false, 
                        'disliked' => false,
                        'likesCount' => $comment->fresh()->likes_count,
                    ]);
                } else {
                    $existing->update(['type' => 'like']);
                    $comment->increment('likes_count');
                    $comment->decrement('dislikes_count');
                    return response()->json([
                        'liked' => true, 
                        'disliked' => false,
                        'likesCount' => $comment->fresh()->likes_count,
                    ]);
                }
            }

            $comment->likes()->create([
                'user_id' => $request->user()->id,
                'type' => 'like',
            ]);
            $comment->increment('likes_count');

            return response()->json([
                'liked' => true, 
                'disliked' => false,
                'likesCount' => $comment->fresh()->likes_count,
            ]);
        });
    }

    public function dislike(Request $request, Comment $comment): JsonResponse
    {
        return DB::transaction(function () use ($request, $comment) {
            $existing = $comment->likes()
                ->where('user_id', $request->user()->id)
                ->lockForUpdate()
                ->first();

            if ($existing) {
                if ($existing->type === 'dislike') {
                    $existing->delete();
                    $comment->decrement('dislikes_count');
                    return response()->json([
                        'liked' => false, 
                        'disliked' => false,
                        'dislikesCount' => $comment->fresh()->dislikes_count,
                    ]);
                } else {
                    $existing->update(['type' => 'dislike']);
                    $comment->decrement('likes_count');
                    $comment->increment('dislikes_count');
                    return response()->json([
                        'liked' => false, 
                        'disliked' => true,
                        'dislikesCount' => $comment->fresh()->dislikes_count,
                    ]);
                }
            }

            $comment->likes()->create([
                'user_id' => $request->user()->id,
                'type' => 'dislike',
            ]);
            $comment->increment('dislikes_count');

            return response()->json([
                'liked' => false, 
                'disliked' => true,
                'dislikesCount' => $comment->fresh()->dislikes_count,
            ]);
        });
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\Like;
use App\Models\Video;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LikeController extends Controller
{
    public function like(Request $request, Video $video): JsonResponse
    {
        return $this->retryTransaction(function () use ($request, $video) {
            $existing = Like::where([
                'user_id' => $request->user()->id,
                'video_id' => $video->id,
            ])->lockForUpdate()->first();

            if ($existing) {
                if ($existing->type === 'like') {
                    $existing->delete();
                    $video->decrement('likes_count');
                    $video = $video->fresh();
                    return response()->json([
                        'liked' => false,
                        'disliked' => false,
                        'likesCount' => $video->likes_count,
                        'dislikesCount' => $video->dislikes_count,
                    ]);
                } else {
                    $existing->update(['type' => 'like']);
                    $video->increment('likes_count');
                    $video->decrement('dislikes_count');
                }
            } else {
                Like::create([
                    'user_id' => $request->user()->id,
                    'video_id' => $video->id,
                    'type' => 'like',
                ]);
                $video->increment('likes_count');
            }

            return response()->json([
                'liked' => true,
                'disliked' => false,
                'likesCount' => $video->fresh()->likes_count,
                'dislikesCount' => $video->fresh()->dislikes_count,
            ]);
        });
    }

    public function dislike(Request $request, Video $video): JsonResponse
    {
        return $this->retryTransaction(function () use ($request, $video) {
            $existing = Like::where([
                'user_id' => $request->user()->id,
                'video_id' => $video->id,
            ])->lockForUpdate()->first();

            if ($existing) {
                if ($existing->type === 'dislike') {
                    $existing->delete();
                    $video->decrement('dislikes_count');
                    $video = $video->fresh();
                    return response()->json([
                        'liked' => false,
                        'disliked' => false,
                        'likesCount' => $video->likes_count,
                        'dislikesCount' => $video->dislikes_count,
                    ]);
                } else {
                    $existing->update(['type' => 'dislike']);
                    $video->decrement('likes_count');
                    $video->increment('dislikes_count');
                }
            } else {
                Like::create([
                    'user_id' => $request->user()->id,
                    'video_id' => $video->id,
                    'type' => 'dislike',
                ]);
                $video->increment('dislikes_count');
            }

            return response()->json([
                'liked' => false,
                'disliked' => true,
                'likesCount' => $video->fresh()->likes_count,
                'dislikesCount' => $video->fresh()->dislikes_count,
            ]);
        });
    }

    /**
     * Retry a transaction with exponential backoff on deadlock.
     */
    private function retryTransaction(callable $callback, int $maxAttempts = 3): mixed
    {
        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            try {
                return DB::transaction($callback);
            } catch (\Illuminate\Database\QueryException $e) {
                // Check if it's a deadlock (SQLSTATE 40001)
                if ($e->getCode() === '40001' && $attempt < $maxAttempts) {
                    // Exponential backoff: 100ms, 200ms, 400ms
                    usleep(100000 * (2 ** ($attempt - 1)));
                    continue;
                }
                throw $e;
            }
        }
    }
}

<?php

namespace App\Http\Controllers;

use Illuminate\Database\QueryException;
use App\Models\Like;
use App\Models\Video;
use App\Notifications\VideoLikeNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LikeController extends Controller
{
    public function like(Request $request, Video $video): JsonResponse
    {
        abort_unless($video->isViewableBy($request->user()), 404);

        return $this->retryTransaction(function () use ($request, $video) {
            $existing = Like::where([
                'user_id' => $request->user()->id,
                'video_id' => $video->id,
            ])->lockForUpdate()->first();

            if ($existing) {
                if ($existing->type === 'like') {
                    $existing->delete();
                    $video->decrementQuietly('likes_count');
                    $video = $video->fresh();
                    return response()->json([
                        'liked' => false,
                        'disliked' => false,
                        'likesCount' => $video->likes_count,
                        'dislikesCount' => $video->dislikes_count,
                    ]);
                } else {
                    $existing->update(['type' => 'like']);
                    $video->incrementQuietly('likes_count');
                    $video->decrementQuietly('dislikes_count');
                }
            } else {
                Like::create([
                    'user_id' => $request->user()->id,
                    'video_id' => $video->id,
                    'type' => 'like',
                ]);
                $video->incrementQuietly('likes_count');

                if ($video->user_id !== $request->user()->id) {
                    $video->loadMissing('user');
                    if ($video->user) {
                        $video->user->notify(new VideoLikeNotification($video, $request->user()));
                    }
                }
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
        abort_unless($video->isViewableBy($request->user()), 404);

        return $this->retryTransaction(function () use ($request, $video) {
            $existing = Like::where([
                'user_id' => $request->user()->id,
                'video_id' => $video->id,
            ])->lockForUpdate()->first();

            if ($existing) {
                if ($existing->type === 'dislike') {
                    $existing->delete();
                    $video->decrementQuietly('dislikes_count');
                    $video = $video->fresh();
                    return response()->json([
                        'liked' => false,
                        'disliked' => false,
                        'likesCount' => $video->likes_count,
                        'dislikesCount' => $video->dislikes_count,
                    ]);
                } else {
                    $existing->update(['type' => 'dislike']);
                    $video->decrementQuietly('likes_count');
                    $video->incrementQuietly('dislikes_count');
                }
            } else {
                Like::create([
                    'user_id' => $request->user()->id,
                    'video_id' => $video->id,
                    'type' => 'dislike',
                ]);
                $video->incrementQuietly('dislikes_count');
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
     * Retry a transaction with exponential backoff on deadlock, or when a
     * concurrent first like won the unique key (the retry then finds it).
     */
    private function retryTransaction(callable $callback, int $maxAttempts = 3): mixed
    {
        for ($attempt = 1; ; $attempt++) {
            try {
                return DB::transaction($callback);
            } catch (QueryException $e) {
                // 40001 = deadlock, 23000 = unique key violation
                if (in_array($e->getCode(), ['40001', '23000'], true) && $attempt < $maxAttempts) {
                    // Exponential backoff: 100ms, 200ms, 400ms
                    usleep(100000 * (2 ** ($attempt - 1)));
                    continue;
                }
                throw $e;
            }
        }
    }
}

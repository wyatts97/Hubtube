<?php

namespace App\Http\Controllers;

use App\Models\WatchHistory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use App\Models\SponsoredCard;

class HistoryController extends Controller
{
    public function index(Request $request): Response
    {
        $videos = $request->user()
            ->watchHistory()
            ->with('video.user')
            ->whereHas('video')
            ->latest('updated_at')
            ->paginate(24);

        $transformedVideos = $videos->through(function ($history) {
            return $history->video;
        });

        return Inertia::render('History', [
            'videos' => $transformedVideos,
            'adSettings' => $this->gridAdSettings(),
            'sponsoredCards' => $this->shouldSuppressAds() ? [] : SponsoredCard::getForPage('history', $this->adTargetRole()),
        ]);
    }

    public function destroy(Request $request): JsonResponse
    {
        $request->user()->watchHistory()->delete();

        return response()->json(['success' => true]);
    }
}

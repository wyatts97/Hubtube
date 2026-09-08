<?php

namespace App\Http\Controllers;

use App\Services\ActivityFeedService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use App\Models\SponsoredCard;

class FeedController extends Controller
{
    public function __construct(
        protected ActivityFeedService $feed,
    ) {}

    /**
     * The subscription feed.
     *
     * Previously a flat, offset-paginated grid of subscribed channels' videos.
     * It now shows activity — uploads grouped per creator, plus new public
     * playlists — on a keyset cursor, so items don't shuffle between pages as
     * new videos publish while the reader is paging.
     */
    public function __invoke(Request $request): Response
    {
        $page = $this->feed->forSubscriber($request->user(), $request->query('cursor'));

        return Inertia::render('Feed', [
            'activity' => $page['items'],
            'nextCursor' => $page['next_cursor'],
            'hasSubscriptions' => $request->user()->channelSubscriptions()->exists(),
            // The feed groups videos into per-creator sections, so ads are
            // interleaved between sections rather than between cards.
            'adSettings' => $this->gridAdSettings(),
            'sponsoredCards' => $this->shouldSuppressAds() ? [] : SponsoredCard::getForPage('feed', $this->adTargetRole()),
        ]);
    }

    /**
     * Cursor-paged JSON for infinite scroll.
     */
    public function more(Request $request): JsonResponse
    {
        $page = $this->feed->forSubscriber($request->user(), $request->query('cursor'));

        return response()->json([
            'activity' => $page['items'],
            'nextCursor' => $page['next_cursor'],
        ]);
    }
}

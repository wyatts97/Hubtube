<?php

namespace App\Services;

use App\Models\Playlist;
use App\Models\User;
use App\Models\Video;
use Illuminate\Support\Collection;

/**
 * Builds the subscription activity feed.
 *
 * Deliberately a query over existing tables rather than a materialised
 * activities table. The only events that exist today are video publishes and
 * playlist creations, both already timestamped and indexed on user_id — a
 * denormalised feed table would add write amplification on the upload path
 * and a backfill for no present benefit. Revisit if the event vocabulary
 * grows beyond what a couple of indexed reads can serve.
 */
class ActivityFeedService
{
    public const TYPE_VIDEO = 'video';

    public const TYPE_PLAYLIST = 'playlist';

    /**
     * One page of activity from the channels this user subscribes to.
     *
     * Uses a keyset cursor on the timestamp rather than an offset: the feed is
     * append-heavy, so OFFSET paging shifts items between pages as new videos
     * publish and the reader sees duplicates.
     *
     * @return array{items: Collection, next_cursor: ?string}
     */
    public function forSubscriber(User $user, ?string $cursor = null, int $perPage = 24): array
    {
        $before = $this->parseCursor($cursor);

        // A subquery, not pluck(): a user with thousands of subscriptions
        // should not load every channel id into PHP just to build an IN list.
        $subscribedIds = fn ($query) => $query
            ->select('channel_id')
            ->from('subscriptions')
            ->where('subscriber_id', $user->id);

        $videos = Video::query()
            ->with('user')
            ->whereIn('user_id', $subscribedIds)
            ->public()
            ->approved()
            ->processed()
            ->when($before, fn ($q) => $q->where('published_at', '<', $before))
            ->whereNotNull('published_at')
            ->latest('published_at')
            // Over-fetch by one so we can tell whether another page exists
            // without a second COUNT query.
            ->limit($perPage + 1)
            ->get();

        $playlists = Playlist::query()
            ->with('user')
            ->withCount('videos')
            ->whereIn('user_id', $subscribedIds)
            ->public()
            ->where('is_default', false)
            ->when($before, fn ($q) => $q->where('created_at', '<', $before))
            ->latest('created_at')
            ->limit($perPage + 1)
            ->get();

        $merged = $this->merge($videos, $playlists)
            ->sortByDesc('occurred_at')
            ->values();

        $hasMore = $merged->count() > $perPage;
        $items = $merged->take($perPage);

        return [
            'items' => $this->groupConsecutiveUploads($items),
            'next_cursor' => $hasMore && $items->isNotEmpty()
                ? $items->last()['occurred_at']->toIso8601String()
                : null,
        ];
    }

    /**
     * Normalise both sources into one shape the frontend can switch on.
     */
    private function merge(Collection $videos, Collection $playlists): Collection
    {
        return $videos
            ->map(fn (Video $video) => [
                'type' => self::TYPE_VIDEO,
                'occurred_at' => $video->published_at,
                'actor' => $this->actor($video->user),
                'subject' => $video,
            ])
            ->concat($playlists->map(fn (Playlist $playlist) => [
                'type' => self::TYPE_PLAYLIST,
                'occurred_at' => $playlist->created_at,
                'actor' => $this->actor($playlist->user),
                'subject' => $playlist,
            ]));
    }

    /**
     * Collapse a run of uploads by the same creator into one entry.
     *
     * Without this a channel that publishes twenty videos in a batch fills the
     * whole first page and buries everyone else.
     */
    private function groupConsecutiveUploads(Collection $items): Collection
    {
        // A plain array, not a Collection: appending into a nested key of a
        // Collection element is an indirect modification of an overloaded
        // offset, which PHP silently drops (and warns about under strict
        // error handling).
        $grouped = [];

        foreach ($items as $item) {
            $last = $grouped === [] ? null : $grouped[array_key_last($grouped)];

            $sameRun = $last
                && $item['type'] === self::TYPE_VIDEO
                && $last['type'] === self::TYPE_VIDEO
                && $last['actor']['id'] === $item['actor']['id'];

            if ($sameRun) {
                $grouped[array_key_last($grouped)]['videos'][] = $item['subject'];

                continue;
            }

            if ($item['type'] === self::TYPE_VIDEO) {
                $item['videos'] = [$item['subject']];
                unset($item['subject']);
            }

            $grouped[] = $item;
        }

        return collect($grouped);
    }

    private function actor(?User $user): array
    {
        return [
            'id' => $user?->id,
            'username' => $user?->username,
            'avatar_url' => $user?->avatar_url,
            'is_verified' => (bool) $user?->is_verified,
        ];
    }

    private function parseCursor(?string $cursor): ?string
    {
        if (! $cursor) {
            return null;
        }

        try {
            return \Illuminate\Support\Carbon::parse($cursor)->toDateTimeString();
        } catch (\Throwable) {
            // A malformed cursor is a bad request, not a reason to 500 — just
            // serve the first page.
            return null;
        }
    }
}

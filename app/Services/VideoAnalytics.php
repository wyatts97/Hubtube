<?php

namespace App\Services;

use App\Models\Video;
use App\Models\VideoView;
use App\Models\WatchHistory;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Per-video analytics, read from the rows the player and the view recorder
 * already write.
 *
 * Two honest limits are worth knowing when reading the numbers:
 *
 *  - video_views rows are pruned after hubtube.video.view_log_retention_days
 *    (90 by default), so the ranges offered stop there. The lifetime total
 *    comes from videos.views_count, which is never pruned.
 *  - watch time and completion come from watch_history, which only has rows for
 *    signed-in viewers. The UI says so rather than passing it off as everyone.
 */
class VideoAnalytics
{
    /** Ranges the studio may ask for, in days. */
    public const RANGES = [7, 28, 90];

    public const DEFAULT_RANGE = 28;

    public function for(Video $video, int $days = self::DEFAULT_RANGE): array
    {
        $days = in_array($days, self::RANGES, true) ? $days : self::DEFAULT_RANGE;
        $since = now()->subDays($days - 1)->startOfDay();

        return [
            'range_days' => $days,
            'ranges' => self::RANGES,
            'totals' => $this->totals($video, $since),
            'views_by_day' => $this->viewsByDay($video, $since, $days),
            'countries' => $this->topCountries($video, $since),
            'sources' => $this->trafficSources($video, $since),
            'engagement' => $this->engagement($video),
        ];
    }

    protected function totals(Video $video, Carbon $since): array
    {
        $views = VideoView::query()
            ->where('video_id', $video->id)
            ->where('created_at', '>=', $since);

        return [
            'views_in_range' => (clone $views)->count(),
            // COALESCE so a guest's IP stands in for the user id they do not
            // have; without it every guest view would collapse into one.
            'viewers_in_range' => (clone $views)
                ->distinct()
                ->count(DB::raw("COALESCE(CAST(user_id AS CHAR), ip_address, '')")),
            'signed_in_views_in_range' => (clone $views)->whereNotNull('user_id')->count(),
            'views_lifetime' => (int) $video->views_count,
            'likes' => (int) $video->likes_count,
            'dislikes' => (int) $video->dislikes_count,
            'comments' => (int) $video->comments_count,
        ];
    }

    /**
     * Daily view counts, with the empty days filled in.
     *
     * A chart drawn straight from GROUP BY would silently close the gaps and
     * make a quiet week look like a busy one.
     *
     * @return array<int, array{date: string, views: int}>
     */
    protected function viewsByDay(Video $video, Carbon $since, int $days): array
    {
        $counts = VideoView::query()
            ->where('video_id', $video->id)
            ->where('created_at', '>=', $since)
            ->selectRaw('DATE(created_at) as day, COUNT(*) as views')
            ->groupBy('day')
            ->pluck('views', 'day');

        $series = [];

        for ($offset = 0; $offset < $days; $offset++) {
            $date = $since->copy()->addDays($offset)->toDateString();
            $series[] = [
                'date' => $date,
                'views' => (int) ($counts[$date] ?? 0),
            ];
        }

        return $series;
    }

    /** @return array<int, array{country: ?string, views: int}> */
    protected function topCountries(Video $video, Carbon $since): array
    {
        return VideoView::query()
            ->where('video_id', $video->id)
            ->where('created_at', '>=', $since)
            ->whereNotNull('country')
            ->selectRaw('country, COUNT(*) as views')
            ->groupBy('country')
            ->orderByDesc('views')
            ->limit(10)
            ->get()
            ->map(fn ($row) => ['country' => $row->country, 'views' => (int) $row->views])
            ->all();
    }

    /**
     * Where the views came from, grouped by referrer host.
     *
     * Hosts are extracted in PHP: the SQL to do it differs between MySQL and
     * SQLite, and the grouped row count is small enough that it does not need
     * to happen in the database.
     *
     * @return array<int, array{source: string, views: int}>
     */
    protected function trafficSources(Video $video, Carbon $since): array
    {
        $rows = VideoView::query()
            ->where('video_id', $video->id)
            ->where('created_at', '>=', $since)
            ->selectRaw('referrer, COUNT(*) as views')
            ->groupBy('referrer')
            ->orderByDesc('views')
            ->limit(200)
            ->get();

        $ownHost = parse_url(config('app.url'), PHP_URL_HOST);
        $totals = [];

        foreach ($rows as $row) {
            $host = $row->referrer ? parse_url($row->referrer, PHP_URL_HOST) : null;

            $source = match (true) {
                ! $host => 'direct',
                $host === $ownHost => 'internal',
                default => preg_replace('/^www\./', '', $host),
            };

            $totals[$source] = ($totals[$source] ?? 0) + (int) $row->views;
        }

        arsort($totals);

        return collect($totals)
            ->take(10)
            ->map(fn ($views, $source) => ['source' => $source, 'views' => $views])
            ->values()
            ->all();
    }

    /**
     * Watch time and completion, over the life of the video.
     *
     * watch_history keeps one row per viewer per video and is updated in place,
     * so these are lifetime figures and not affected by the selected range.
     */
    protected function engagement(Video $video): array
    {
        $row = WatchHistory::query()
            ->where('video_id', $video->id)
            ->selectRaw('COUNT(*) as viewers, COALESCE(SUM(watched_seconds), 0) as total_seconds, COALESCE(AVG(watched_seconds), 0) as average_seconds, SUM(CASE WHEN completed THEN 1 ELSE 0 END) as completions')
            ->first();

        $viewers = (int) ($row->viewers ?? 0);
        $averageSeconds = (int) round((float) ($row->average_seconds ?? 0));
        $duration = (int) $video->duration;

        return [
            'tracked_viewers' => $viewers,
            'total_watch_seconds' => (int) ($row->total_seconds ?? 0),
            'average_watch_seconds' => $averageSeconds,
            'completions' => (int) ($row->completions ?? 0),
            'completion_rate' => $viewers > 0
                ? round(((int) ($row->completions ?? 0)) / $viewers * 100, 1)
                : null,
            // Null rather than zero when the duration is unknown: a percentage
            // of an unknown length would be a made-up number.
            'average_watched_percent' => $duration > 0
                ? min(100.0, round($averageSeconds / $duration * 100, 1))
                : null,
        ];
    }
}

<?php

namespace App\Services;

use App\Models\Video;
use App\Models\VideoView;
use App\Support\VisitorCountry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Counts a watch-page visit as a view, at most once per viewer per window.
 *
 * Previously every page load added one to views_count, so a refresh, a bot or
 * a crawler each inflated the count. A view now needs a non-bot user agent and
 * a viewer (signed-in user, else IP + user agent) that has not already been
 * counted for this video within hubtube.video.view_dedup_minutes.
 */
class VideoViewRecorder
{
    /**
     * Substrings of user agents that are never human viewers. Deliberately
     * broad: undercounting a rare odd browser costs less than counting
     * every crawler.
     */
    protected const BOT_PATTERN = '/bot|crawl|spider|slurp|mediapartners|facebookexternalhit|embedly|quora link preview|'
        .'whatsapp|telegram|discord|skypeuripreview|preview|headless|lighthouse|pagespeed|pingdom|uptime|'
        .'monitor|curl|wget|python|java\/|go-http|okhttp|axios|node-fetch|libwww|httpclient|scrapy/i';

    /** @return bool Whether the visit was counted. */
    public function record(Video $video, Request $request): bool
    {
        if (static::isBot($request) || $this->isPrefetch($request)) {
            return false;
        }

        $viewer = $request->user()
            ? 'u:'.$request->user()->id
            : 'g:'.hash('sha256', ($request->ip() ?? '').'|'.($request->userAgent() ?? ''));

        $minutes = (int) config('hubtube.video.view_dedup_minutes', 360);

        // add() is atomic, so two simultaneous loads by the same viewer count once.
        if (! Cache::add("video-view:{$video->id}:{$viewer}", true, now()->addMinutes($minutes))) {
            return false;
        }

        $video->incrementViews();

        try {
            VideoView::create([
                'video_id' => $video->id,
                'user_id' => $request->user()?->id,
                'ip_address' => $request->ip(),
                'user_agent' => Str::limit((string) $request->userAgent(), 497),
                'country' => VisitorCountry::fromRequest($request),
                'referrer' => Str::limit((string) $request->headers->get('referer', ''), 497) ?: null,
            ]);
        } catch (Throwable $e) {
            // The count already went up; a failed analytics row must not break
            // the watch page.
            Log::warning('VideoViewRecorder: failed to write video_views row', [
                'video_id' => $video->id,
                'error' => $e->getMessage(),
            ]);
        }

        return true;
    }

    public static function isBot(Request $request): bool
    {
        $agent = (string) $request->userAgent();

        return $agent === '' || preg_match(self::BOT_PATTERN, $agent) === 1;
    }

    /** Link prefetches (Inertia, browser speculation rules) are not visits. */
    protected function isPrefetch(Request $request): bool
    {
        $purpose = strtolower((string) ($request->header('Purpose') ?? $request->header('Sec-Purpose', '')));

        return str_contains($purpose, 'prefetch') || str_contains($purpose, 'prerender');
    }
}

<?php

namespace App\Services;

use App\Models\AdStatDaily;
use App\Models\Setting;
use App\Support\DeviceType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Writes per-day ad delivery counters.
 *
 * Modelled on App\Http\Middleware\TrackVisitor: a single query-builder upsert
 * with a DB::raw increment, wrapped so a tracking failure can never surface to
 * the visitor. Recording an impression is not worth a 500.
 */
class AdStatsRecorder
{
    public const EVENT_IMPRESSION = 'impressions';
    public const EVENT_CLICK = 'clicks';

    /**
     * Seconds within which a repeat of the same ad, in the same slot, from the
     * same session counts once.
     *
     * The route throttles are per-IP, not per-ad, so without this a viewer who
     * re-renders a slot (a filter change, a back navigation) inflates their own
     * impression count. One minute matches how ad networks generally window a
     * repeat view.
     */
    public const DEDUPE_SECONDS = 60;

    /**
     * Record one event. Returns false when it was suppressed as a duplicate or
     * swallowed as an error — callers use this for tests, not for control flow.
     */
    public function record(
        Request $request,
        string $source,
        ?int $adId,
        string $placement,
        string $event = self::EVENT_IMPRESSION,
    ): bool {
        try {
            if (! in_array($event, [self::EVENT_IMPRESSION, self::EVENT_CLICK], true)) {
                return false;
            }

            // 0 is the "no creative" sentinel: the unique index cannot dedupe
            // on a NULL, so network slots are recorded against 0.
            $adId = $adId ?: 0;
            $placement = substr($placement, 0, 40) ?: 'unknown';
            $country = $this->country($request);
            $device = DeviceType::detect($request->userAgent());
            $date = $this->today();

            if (! $this->claim($request, $source, $adId, $placement, $event)) {
                return false;
            }

            $now = now();

            DB::table('ad_stats_daily')->upsert(
                [[
                    'date' => $date,
                    'source' => $source,
                    'ad_id' => $adId,
                    'placement' => $placement,
                    'country' => $country,
                    'device' => $device,
                    'impressions' => $event === self::EVENT_IMPRESSION ? 1 : 0,
                    'clicks' => $event === self::EVENT_CLICK ? 1 : 0,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]],
                ['date', 'source', 'ad_id', 'placement', 'country', 'device'],
                [
                    $event => DB::raw("{$event} + 1"),
                    'updated_at' => $now,
                ]
            );

            return true;
        } catch (Throwable) {
            // Never let tracking break the request.
            return false;
        }
    }

    /**
     * Atomically reserve this event, returning false if an identical one was
     * already counted inside the dedupe window.
     *
     * Cache::add is a single atomic check-and-set on the Redis store the site
     * runs, so two concurrent requests cannot both win.
     *
     * The viewer is identified by an IP+UA hash rather than a session id, for
     * the same reason TrackVisitor builds its visitor_hash that way: the
     * session id is not guaranteed stable across requests (it rotates whenever
     * the cookie does not round-trip), and a key that changes every request
     * de-duplicates nothing. The trade is that several people behind one NAT
     * with an identical user agent share a key — but they would have to view
     * the same creative in the same slot inside the same minute to collide, and
     * a slight under-count is much better than the unbounded over-count that
     * exists today.
     */
    protected function claim(Request $request, string $source, int $adId, string $placement, string $event): bool
    {
        $viewer = sha1(($request->ip() ?? '') . '|' . ($request->userAgent() ?? ''));

        $key = "adstat:{$viewer}:{$source}:{$adId}:{$placement}:{$event}";

        try {
            return Cache::add($key, 1, self::DEDUPE_SECONDS);
        } catch (Throwable) {
            // A cache outage must not stop counting; over-counting beats
            // silently recording nothing.
            return true;
        }
    }

    /**
     * ISO-3166-1 alpha-2 from Cloudflare.
     *
     * bootstrap/app.php trusts proxies at '*', so this header is set by the CDN
     * and cannot be spoofed past it. Requests that never went through Cloudflare
     * (local dev, direct-to-origin) have no header and bucket as 'XX'.
     * Cloudflare itself sends 'XX' for anonymised or unknown clients and 'T1'
     * for Tor, both of which are already two characters.
     */
    protected function country(Request $request): string
    {
        $code = strtoupper(trim((string) $request->header('CF-IPCountry', '')));

        return preg_match('/^[A-Z0-9]{2}$/', $code) ? $code : 'XX';
    }

    /**
     * The reporting day, in the admin's configured timezone.
     *
     * Matches TrackVisitor and StatsOverview. Using config('app.timezone')
     * instead would put ad stats and visitor stats on different days for any
     * site whose operators are not on UTC.
     */
    protected function today(): string
    {
        $timezone = config('app.timezone');

        try {
            $timezone = Setting::get('site_timezone', $timezone) ?: $timezone;
        } catch (Throwable) {
            // Settings table may be unavailable during install.
        }

        return now($timezone)->toDateString();
    }

    /** Convenience wrappers, so call sites read as prose. */
    public function impression(Request $request, string $source, ?int $adId, string $placement): bool
    {
        return $this->record($request, $source, $adId, $placement, self::EVENT_IMPRESSION);
    }

    public function click(Request $request, string $source, ?int $adId, string $placement): bool
    {
        return $this->record($request, $source, $adId, $placement, self::EVENT_CLICK);
    }

    /** @return list<string> */
    public static function sources(): array
    {
        return [
            AdStatDaily::SOURCE_VIDEO_AD,
            AdStatDaily::SOURCE_SPONSORED_CARD,
            AdStatDaily::SOURCE_NETWORK,
        ];
    }
}

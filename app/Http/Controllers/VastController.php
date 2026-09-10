<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Models\VideoAd;
use App\Services\VastBuilder;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Serves the site's own ad inventory as VAST.
 *
 * One endpoint per placement, called by the player rather than pre-fetched into
 * the page. That is the important difference from /api/video-ads, which hands
 * the client a list of every eligible creative up front: here each break asks
 * for an ad at the moment it needs one, so selection stays on the server and
 * the payload never contains creatives the viewer will not see.
 *
 * It also means the weighted pick runs per break, which naturally rotates
 * creatives across mid-rolls. The old client-side overlay had to walk its
 * pre-fetched list by hand to avoid replaying the same creative three times.
 */
class VastController extends Controller
{
    /**
     * Placements servable as VAST, mapped to the setting that enables them.
     *
     * `outstream` and `shorts` are deliberately absent: they are rendered by
     * their own Vue components from /api/video-ads, not by the video player.
     */
    protected const PLACEMENTS = [
        'pre_roll' => 'video_ad_pre_roll_enabled',
        'mid_roll' => 'video_ad_mid_roll_enabled',
        'post_roll' => 'video_ad_post_roll_enabled',
        'on_pause_roll' => 'video_ad_on_pause_roll_enabled',
    ];

    /** Skip-delay setting per placement. Non-linear on-pause ads have no skip. */
    protected const SKIP_SETTINGS = [
        'pre_roll' => 'video_ad_pre_roll_skip_after',
        'mid_roll' => 'video_ad_mid_roll_skip_after',
        'post_roll' => 'video_ad_post_roll_skip_after',
    ];

    public function __construct(
        protected VastBuilder $vast,
    ) {}

    /**
     * GET /api/vast/{placement}
     *
     * Always answers with a well-formed VAST document, including when there is
     * nothing to serve. Returning an error status instead would make the player
     * sit through its VAST timeout before falling back to content, turning "no
     * ad booked" into several seconds of black screen.
     */
    public function serve(Request $request, string $placement): Response
    {
        if (! array_key_exists($placement, self::PLACEMENTS)) {
            return $this->xml($this->vast->empty());
        }

        // Gate before selecting anything. This is a public endpoint, so the
        // server withholding the document is the only real paywall — a
        // client-side check would still have shipped the creative.
        if ($this->shouldSuppressAds()) {
            return $this->xml($this->vast->empty());
        }

        if (! Setting::get(self::PLACEMENTS[$placement], false)) {
            return $this->xml($this->vast->empty());
        }

        $ad = VideoAd::pickForPlacement(
            $placement,
            $request->integer('category_id') ?: null,
            $this->adTargetRole(),
        );

        return $this->xml(
            $this->vast->build($ad, $placement, $this->skipAfter($placement))
        );
    }

    protected function skipAfter(string $placement): int
    {
        $setting = self::SKIP_SETTINGS[$placement] ?? null;

        return $setting ? (int) Setting::get($setting, 5) : 0;
    }

    /**
     * `no-store` is load-bearing: selection is weighted-random per request, so a
     * cached document would pin every viewer to whichever creative was drawn
     * first and quietly starve the rest of the rotation.
     */
    protected function xml(string $body): Response
    {
        return response($body, 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
        ]);
    }
}

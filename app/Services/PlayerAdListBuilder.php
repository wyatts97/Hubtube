<?php

namespace App\Services;

use App\Models\Setting;
use App\Models\Video;

/**
 * Builds the player's ad break schedule.
 *
 * Fluid Player takes its whole `adList` at construction and does not accept
 * changes afterwards, so every break — including each mid-roll and the time it
 * fires at — has to be decided before playback starts. That is a change from
 * the old Vue overlay, which re-evaluated the mid-roll rules on every
 * `timeupdate`; the rules themselves are unchanged, they just run once here.
 *
 * Built on the server for the same reason ad suppression is: the schedule is
 * derived from settings and viewer role, and assembling it in the browser would
 * put a second copy of that logic somewhere AdService cannot reach.
 */
class PlayerAdListBuilder
{
    /**
     * Mid-rolls stop this many seconds before the end.
     *
     * A break that lands in the closing seconds interrupts the payoff of the
     * video and then hands straight over to the post-roll, which reads as one
     * long ad. Carried over verbatim from the overlay's mid-roll guard.
     */
    public const MID_ROLL_END_GUARD = 30;

    public function __construct(
        protected AdService $ads,
    ) {}

    /**
     * @return list<array<string, mixed>> Fluid Player adList entries, empty when
     *                                    the viewer is ad-free or nothing is enabled.
     */
    public function build(Video $video, ?int $categoryId = null): array
    {
        if ($this->ads->shouldSuppress(auth()->user())) {
            return [];
        }

        $adList = [];

        if (Setting::get('video_ad_pre_roll_enabled', false)) {
            $adList[] = $this->entry('preRoll', 'pre_roll', $categoryId);
        }

        foreach ($this->midRollTimers((int) $video->duration) as $index => $timer) {
            $adList[] = $this->entry('midRoll', 'mid_roll', $categoryId, $index) + ['timer' => $timer];
        }

        if (Setting::get('video_ad_post_roll_enabled', false)) {
            $adList[] = $this->entry('postRoll', 'post_roll', $categoryId);
        }

        if (Setting::get('video_ad_on_pause_roll_enabled', false)) {
            $adList[] = $this->entry('onPauseRoll', 'on_pause_roll', $categoryId) + [
                'size' => '300x250',
                'vAlign' => 'bottom',
            ];
        }

        return $adList;
    }

    /**
     * Seconds at which each mid-roll fires.
     *
     * Same three rules the overlay enforced: wait a full interval before the
     * first break, cap the count, and stay clear of the end. A video shorter
     * than one interval gets no mid-rolls at all.
     *
     * @return list<int>
     */
    protected function midRollTimers(int $duration): array
    {
        if (! Setting::get('video_ad_mid_roll_enabled', false)) {
            return [];
        }

        $interval = max(1, (int) Setting::get('video_ad_mid_roll_interval', 300));
        $maxCount = max(0, (int) Setting::get('video_ad_mid_roll_max_count', 3));

        if ($maxCount === 0 || $duration <= 0) {
            return [];
        }

        $latest = $duration - self::MID_ROLL_END_GUARD;
        $timers = [];

        for ($i = 1; $i <= $maxCount; $i++) {
            $at = $interval * $i;

            if ($at > $latest) {
                break;
            }

            $timers[] = $at;
        }

        return $timers;
    }

    /**
     * One adList entry.
     *
     * `b` is a break index rather than anything the server reads: the VAST
     * endpoint picks a fresh weighted-random creative per request, and a
     * distinct URL per break stops an intermediate cache collapsing several
     * mid-rolls onto one response — which is how every break ended up playing
     * the same creative before.
     */
    protected function entry(string $roll, string $placement, ?int $categoryId, ?int $break = null): array
    {
        $params = [];

        if ($categoryId) {
            $params['category_id'] = $categoryId;
        }

        if ($break !== null) {
            $params['b'] = $break;
        }

        return [
            'roll' => $roll,
            'vastTag' => route('vast.serve', ['placement' => $placement] + $params),
        ];
    }
}

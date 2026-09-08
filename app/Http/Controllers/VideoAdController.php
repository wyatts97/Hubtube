<?php

namespace App\Http\Controllers;

use App\Models\AdStatDaily;
use App\Models\Setting;
use App\Models\SponsoredCard;
use App\Models\VideoAd;
use App\Services\AdStatsRecorder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VideoAdController extends Controller
{
    public function __construct(
        protected AdStatsRecorder $stats,
    ) {}

    /**
     * Record a video ad impression (fire-and-forget, rate-limited per ad per IP).
     *
     * Two writes on purpose: the lifetime counter on the creative, which the
     * Filament resources render, and the dimensioned daily bucket, which is the
     * only thing that can answer "which placement, which country, which day".
     */
    public function recordImpression(Request $request): JsonResponse
    {
        $adId = $request->integer('ad_id');
        if ($adId) {
            VideoAd::where('id', $adId)->increment('impressions_count');
            $this->stats->impression(
                $request,
                AdStatDaily::SOURCE_VIDEO_AD,
                $adId,
                $this->placement($request, 'video_ad'),
            );
        }

        return response()->json(['ok' => true]);
    }

    /**
     * Record a video ad click (fire-and-forget, rate-limited per ad per IP).
     */
    public function recordClick(Request $request): JsonResponse
    {
        $adId = $request->integer('ad_id');
        if ($adId) {
            VideoAd::where('id', $adId)->increment('clicks_count');
            $this->stats->click(
                $request,
                AdStatDaily::SOURCE_VIDEO_AD,
                $adId,
                $this->placement($request, 'video_ad'),
            );
        }

        return response()->json(['ok' => true]);
    }

    /**
     * Record a sponsored card click and redirect to the target URL.
     */
    public function recordSponsoredClick(Request $request, int $cardId): JsonResponse
    {
        SponsoredCard::where('id', $cardId)->increment('clicks_count');
        $this->stats->click(
            $request,
            AdStatDaily::SOURCE_SPONSORED_CARD,
            $cardId,
            $this->placement($request, 'sponsored_card'),
        );

        return response()->json(['ok' => true]);
    }

    public function recordSponsoredImpression(Request $request, int $cardId): JsonResponse
    {
        SponsoredCard::where('id', $cardId)->increment('impressions_count');
        $this->stats->impression(
            $request,
            AdStatDaily::SOURCE_SPONSORED_CARD,
            $cardId,
            $this->placement($request, 'sponsored_card'),
        );

        return response()->json(['ok' => true]);
    }

    /**
     * Impression beacon for the settings-based ad codes.
     *
     * Banners, grid slots, homepage rails and the footer are pasted network
     * codes with no creative row, so nothing in the app has ever known whether
     * they rendered. Without this the only measurable ads are the ones served
     * from the database, which is a small minority of the inventory.
     */
    public function recordSlotImpression(Request $request): JsonResponse
    {
        $placement = (string) $request->input('placement', '');

        if ($placement !== '') {
            $this->stats->impression($request, AdStatDaily::SOURCE_NETWORK, null, $placement);
        }

        return response()->json(['ok' => true]);
    }

    /**
     * Placement label for a tracking call.
     *
     * The client sends one where it knows it (the player sends 'pre_roll' and
     * friends); older callers that predate this parameter fall back to a label
     * naming the subsystem rather than dropping the row.
     */
    protected function placement(Request $request, string $fallback): string
    {
        $placement = trim((string) $request->input('placement', ''));

        return $placement !== '' ? $placement : $fallback;
    }

    public function getAds(Request $request): JsonResponse
    {
        $categoryId = $request->integer('category_id');

        // Pro users with ad-free enabled get no ads at all.
        if ($this->shouldSuppressAds()) {
            return response()->json([
                'ads' => [
                    'pre_roll' => [],
                    'mid_roll' => [],
                    'post_roll' => [],
                    'outstream' => [],
                    'shorts' => [],
                ],
                'config' => [
                    'pre_roll_skip_after' => (int) Setting::get('video_ad_pre_roll_skip_after', 5),
                    'mid_roll_skip_after' => (int) Setting::get('video_ad_mid_roll_skip_after', 5),
                    'post_roll_skip_after' => (int) Setting::get('video_ad_post_roll_skip_after', 0),
                    'mid_roll_interval' => (int) Setting::get('video_ad_mid_roll_interval', 300),
                    'mid_roll_max_count' => (int) Setting::get('video_ad_mid_roll_max_count', 3),
                    'outstream_frequency' => (int) Setting::get('video_outstream_ad_frequency', 6),
                    'shorts_ad_enabled' => false,
                    'shorts_ad_frequency' => (int) Setting::get('shorts_ad_frequency', 8),
                ],
            ]);
        }

        // Determine user role for targeting
        $userRole = $this->adTargetRole();

        // Check global enable flags
        $preRollEnabled = (bool) Setting::get('video_ad_pre_roll_enabled', false);
        $midRollEnabled = (bool) Setting::get('video_ad_mid_roll_enabled', false);
        $postRollEnabled = (bool) Setting::get('video_ad_post_roll_enabled', false);
        $shuffle = (bool) Setting::get('video_ad_shuffle', true);

        $outstreamEnabled = (bool) Setting::get('video_outstream_ad_enabled', false);

        $ads = [
            'pre_roll' => [],
            'mid_roll' => [],
            'post_roll' => [],
            'outstream' => [],
            'shorts' => [],
        ];

        $shortsAdEnabled = (bool) Setting::get('shorts_ad_enabled', false);

        $config = [
            'pre_roll_skip_after' => (int) Setting::get('video_ad_pre_roll_skip_after', 5),
            'mid_roll_skip_after' => (int) Setting::get('video_ad_mid_roll_skip_after', 5),
            'post_roll_skip_after' => (int) Setting::get('video_ad_post_roll_skip_after', 0),
            'mid_roll_interval' => (int) Setting::get('video_ad_mid_roll_interval', 300),
            'mid_roll_max_count' => (int) Setting::get('video_ad_mid_roll_max_count', 3),
            'outstream_frequency' => (int) Setting::get('video_outstream_ad_frequency', 6),
            'shorts_ad_enabled' => $shortsAdEnabled,
            'shorts_ad_frequency' => (int) Setting::get('shorts_ad_frequency', 8),
        ];

        if ($preRollEnabled) {
            $ads['pre_roll'] = VideoAd::getAdsForPlacement('pre_roll', $categoryId, $userRole, $shuffle);
        }

        if ($midRollEnabled) {
            $ads['mid_roll'] = VideoAd::getAdsForPlacement('mid_roll', $categoryId, $userRole, $shuffle);
        }

        if ($postRollEnabled) {
            $ads['post_roll'] = VideoAd::getAdsForPlacement('post_roll', $categoryId, $userRole, $shuffle);
        }

        if ($outstreamEnabled) {
            $ads['outstream'] = VideoAd::getAdsForPlacement('outstream', $categoryId, $userRole, false);
        }

        if ($shortsAdEnabled) {
            $ads['shorts'] = VideoAd::getAdsForPlacement('shorts', $categoryId, $userRole, $shuffle);
        }

        return response()->json([
            'ads' => $ads,
            'config' => $config,
        ]);
    }
}

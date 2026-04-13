<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Models\SponsoredCard;
use App\Models\VideoAd;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VideoAdController extends Controller
{
    /**
     * Record a video ad impression (fire-and-forget, rate-limited per ad per IP).
     */
    public function recordImpression(Request $request): JsonResponse
    {
        $adId = $request->integer('ad_id');
        if ($adId) {
            VideoAd::where('id', $adId)->increment('impressions_count');
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
        }
        return response()->json(['ok' => true]);
    }

    /**
     * Record a sponsored card click and redirect to the target URL.
     */
    public function recordSponsoredClick(Request $request, int $cardId): JsonResponse
    {
        SponsoredCard::where('id', $cardId)->increment('clicks_count');
        return response()->json(['ok' => true]);
    }

    public function getAds(Request $request): JsonResponse
    {
        $categoryId = $request->integer('category_id');
        $user = $request->user();

        // Determine user role for targeting
        $userRole = 'guest';
        if ($user) {
            if ($user->is_admin) {
                $userRole = 'admin';
            } elseif ($user->is_pro) {
                $userRole = 'pro';
            } else {
                $userRole = 'default';
            }
        }

        // Check global enable flags
        $preRollEnabled = (bool) Setting::get('video_ad_pre_roll_enabled', false);
        $midRollEnabled = (bool) Setting::get('video_ad_mid_roll_enabled', false);
        $postRollEnabled = (bool) Setting::get('video_ad_post_roll_enabled', false);
        $shuffle = (bool) Setting::get('video_ad_shuffle', true);

        $ads = [
            'pre_roll' => [],
            'mid_roll' => [],
            'post_roll' => [],
        ];

        $config = [
            'pre_roll_skip_after' => (int) Setting::get('video_ad_pre_roll_skip_after', 5),
            'mid_roll_skip_after' => (int) Setting::get('video_ad_mid_roll_skip_after', 5),
            'post_roll_skip_after' => (int) Setting::get('video_ad_post_roll_skip_after', 0),
            'mid_roll_interval' => (int) Setting::get('video_ad_mid_roll_interval', 300),
            'mid_roll_max_count' => (int) Setting::get('video_ad_mid_roll_max_count', 3),
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

        return response()->json([
            'ads' => $ads,
            'config' => $config,
        ]);
    }
}

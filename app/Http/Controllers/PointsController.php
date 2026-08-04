<?php

namespace App\Http\Controllers;

use App\Models\PointsTransaction;
use App\Models\Setting;
use App\Services\PointsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class PointsController extends Controller
{
    public function __construct(
        protected PointsService $pointsService
    ) {}

    public function index(Request $request): Response
    {
        if (!Setting::get('points_enabled', true)) {
            throw new NotFoundHttpException();
        }

        $user = $request->user();

        $transactions = $user->pointsTransactions()
            ->with('reference')
            ->latest()
            ->paginate(20)
            ->through(fn (PointsTransaction $t) => [
                'id' => $t->id,
                'type' => $t->type,
                'points' => $t->points,
                'balance_after' => $t->balance_after,
                'description' => $t->description,
                'reference_title' => $t->reference?->title ?? null,
                'created_at' => $t->created_at->toIso8601String(),
            ]);

        $earnMethods = [];
        if (Setting::get('points_video_upload_enabled', true)) {
            $earnMethods[] = [
                'label' => 'Upload a video (after approval)',
                'points' => (int) Setting::get('points_per_video_upload', 100),
                'icon' => 'video',
            ];
        }
        if (Setting::get('points_image_upload_enabled', true)) {
            $earnMethods[] = [
                'label' => 'Upload an image (after approval)',
                'points' => (int) Setting::get('points_per_image_upload', 25),
                'icon' => 'image',
            ];
        }
        if (Setting::get('points_comment_enabled', true)) {
            $dailyCap = (int) Setting::get('points_comment_daily_cap', 50);
            $earnMethods[] = [
                'label' => $dailyCap > 0
                    ? "Post comments (up to {$dailyCap} pts/day)"
                    : 'Post comments',
                'points' => (int) Setting::get('points_per_comment', 5),
                'icon' => 'comment',
            ];
        }

        return Inertia::render('Rewards/Index', [
            'balance' => $user->points_balance,
            'transactions' => $transactions,
            'redemptionCost' => (int) Setting::get('points_per_redemption_cost', 3000),
            'proGrantDays' => (int) Setting::get('points_pro_grant_days', 30),
            'proExpiresAt' => $user->pro_source === 'points' ? $user->pro_expires_at?->toIso8601String() : null,
            'redemptionEnabled' => (bool) Setting::get('points_redemption_enabled', true),
            'earnMethods' => $earnMethods,
        ]);
    }

    public function redeem(Request $request): RedirectResponse
    {
        if (!Setting::get('points_enabled', true) || !Setting::get('points_redemption_enabled', true)) {
            return back()->with('error', 'Point redemption is currently disabled.');
        }

        try {
            $redemption = $this->pointsService->redeemForPro($request->user());
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "Redeemed {$redemption->days_granted} days of Ad-Free Pro! Enjoy an ad-free experience until {$redemption->ends_at->format('M j, Y')}.");
    }
}

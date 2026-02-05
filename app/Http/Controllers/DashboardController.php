<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $user = $request->user();

        $totalVideos = $user->videos()->count();
        $totalViews = $user->videos()->sum('views_count');
        $totalLikes = $user->videos()->sum('likes_count');
        $subscriberCount = $user->subscribers()->count();

        $recentVideos = $user->videos()
            ->latest()
            ->limit(10)
            ->get();

        $topVideos = $user->videos()
            ->orderByDesc('views_count')
            ->limit(5)
            ->get();

        return Inertia::render('Dashboard', [
            'stats' => [
                'totalVideos' => $totalVideos,
                'totalViews' => $totalViews,
                'totalLikes' => $totalLikes,
                'subscriberCount' => $subscriberCount,
                'walletBalance' => $user->wallet_balance,
            ],
            'recentVideos' => $recentVideos,
            'topVideos' => $topVideos,
        ]);
    }
}

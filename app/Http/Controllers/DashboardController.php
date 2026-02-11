<?php

namespace App\Http\Controllers;

use App\Models\Video;
use App\Services\TranslationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct(
        protected TranslationService $translationService,
    ) {}

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

        // Translate video titles for the current locale
        $locale = App::getLocale();
        $defaultLocale = TranslationService::getDefaultLocale();

        if ($locale !== $defaultLocale) {
            $recentVideos = collect(
                $this->translationService->translateBatch(
                    Video::class,
                    $recentVideos->toArray(),
                    ['title'],
                    $locale
                )
            );

            $topVideos = collect(
                $this->translationService->translateBatch(
                    Video::class,
                    $topVideos->toArray(),
                    ['title'],
                    $locale
                )
            );
        }

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

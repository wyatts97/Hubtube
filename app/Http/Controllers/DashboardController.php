<?php

namespace App\Http\Controllers;

use App\Jobs\TranslateModelJob;
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
            // Cache-only: translateBatch() would call the throttled provider
            // inline and hold this request for the duration. Anything missing
            // is queued and appears on a later visit.
            $recentVideos = collect($this->translatedOrQueued($recentVideos, $locale));
            $topVideos = collect($this->translatedOrQueued($topVideos, $locale));
        }

        return Inertia::render('Dashboard', [
            'stats' => [
                'totalVideos' => $totalVideos,
                'totalViews' => $totalViews,
                'totalLikes' => $totalLikes,
                'subscriberCount' => $subscriberCount,
                'walletBalance' => $user->wallet_balance,
                'pointsBalance' => $user->points_balance,
            ],
            'recentVideos' => $recentVideos,
            'topVideos' => $topVideos,
        ]);
    }

    /**
     * Apply stored title translations to a video collection and queue the rest.
     */
    protected function translatedOrQueued(iterable $videos, string $locale): array
    {
        $items = collect($videos)->toArray();

        if (empty($items)) {
            return [];
        }

        $cached = $this->translationService->batchFromCache(Video::class, $items, ['title'], $locale);

        if (TranslationService::autoTranslateEnabled()) {
            foreach ($cached['missing'] as $id) {
                TranslateModelJob::dispatch(Video::class, $id, ['title'], $locale);
            }
        }

        return $cached['items'];
    }
}

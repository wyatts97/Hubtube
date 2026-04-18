<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\Analytics\CategoryViewsChartWidget;
use App\Filament\Widgets\Analytics\RevenueChartWidget;
use App\Filament\Widgets\Analytics\SignupsChartWidget;
use App\Filament\Widgets\Analytics\UploadsChartWidget;
use App\Models\User;
use App\Models\Video;
use App\Models\VideoAd;
use Filament\Pages\Page;

class Analytics extends Page
{
    protected static ?string $navigationIcon  = 'heroicon-o-chart-bar';
    protected static ?string $navigationLabel = 'Analytics';
    protected static ?string $navigationGroup = 'Overview';
    protected static ?int    $navigationSort  = 2;
    protected static string  $view            = 'filament.pages.analytics';

    /**
     * Host the ApexChart widgets in a 2-column grid.
     */
    public function getHeaderWidgets(): array
    {
        $widgets = [];

        if (class_exists(UploadsChartWidget::class)) {
            $widgets[] = UploadsChartWidget::class;
            $widgets[] = SignupsChartWidget::class;
            $widgets[] = CategoryViewsChartWidget::class;
            $widgets[] = RevenueChartWidget::class;
        }

        return $widgets;
    }

    public function getHeaderWidgetsColumns(): int|array
    {
        return 2;
    }

    public function getSummaryStats(): array
    {
        return [
            'total_videos'      => Video::count(),
            'total_users'       => User::count(),
            'total_views'       => Video::sum('views_count'),
            'videos_this_week'  => Video::where('created_at', '>=', now()->subWeek())->count(),
            'users_this_week'   => User::where('created_at', '>=', now()->subWeek())->count(),
            'total_impressions' => VideoAd::sum('impressions_count'),
            'total_clicks'      => VideoAd::sum('clicks_count'),
        ];
    }

    public function getAdPerformance(): array
    {
        return VideoAd::active()
            ->select('id', 'name', 'placement', 'type', 'impressions_count', 'clicks_count')
            ->orderByDesc('impressions_count')
            ->limit(20)
            ->get()
            ->map(function ($ad) {
                $ctr = $ad->impressions_count > 0
                    ? round(($ad->clicks_count / $ad->impressions_count) * 100, 2)
                    : 0;
                return [
                    'id'          => $ad->id,
                    'name'        => $ad->name,
                    'placement'   => $ad->placement,
                    'type'        => $ad->type,
                    'impressions' => $ad->impressions_count,
                    'clicks'      => $ad->clicks_count,
                    'ctr'         => $ctr,
                ];
            })
            ->toArray();
    }
}

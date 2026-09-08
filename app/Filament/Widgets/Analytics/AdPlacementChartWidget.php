<?php

namespace App\Filament\Widgets\Analytics;

use App\Filament\Widgets\Analytics\Concerns\DarkThemeOptions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Leandrocfe\FilamentApexCharts\Widgets\ApexChartWidget;

/**
 * Impressions by placement over the last 30 days.
 *
 * This is the comparison the ad system could not make before: network slots
 * (banners, grid, rails, footer) had no tracking at all, so the only measurable
 * inventory was the database-backed creatives.
 */
class AdPlacementChartWidget extends ApexChartWidget
{
    use DarkThemeOptions;

    protected static ?string $chartId = 'adPlacementChart';
    protected static ?int $contentHeight = 260;

    protected function getHeading(): ?string
    {
        return 'Impressions by Placement · Last 30 Days';
    }

    public static function canView(): bool
    {
        return Schema::hasTable('ad_stats_daily');
    }

    protected function getOptions(): array
    {
        $rows = DB::table('ad_stats_daily')
            ->selectRaw('placement, SUM(impressions) as impressions')
            ->where('date', '>=', now()->subDays(29)->toDateString())
            ->groupBy('placement')
            ->orderByDesc('impressions')
            ->limit(12)
            ->get();

        $labels = $rows->pluck('placement')
            ->map(fn ($p) => ucwords(str_replace('_', ' ', (string) $p)))
            ->all();
        $values = $rows->pluck('impressions')->map(fn ($v) => (int) $v)->all();

        // ApexCharts v5 crashes in getyAxisLabelsCoords when the series is all
        // zeroes; a hair above zero renders identically and avoids it.
        if (empty($values)) {
            $labels = ['No data'];
            $values = [0.0001];
        } elseif (max($values) === 0) {
            $values[0] = 0.0001;
        }

        return $this->mergeTheme($this->darkThemeBase(), [
            'chart' => ['type' => 'bar', 'height' => 260],
            'series' => [['name' => 'Impressions', 'data' => $values]],
            'xaxis' => ['categories' => $labels],
            'yaxis' => [
                'min' => 0,
                'labels' => [
                    'style' => ['colors' => '#64748b', 'fontSize' => '11px', 'fontFamily' => 'Inter'],
                ],
            ],
            'plotOptions' => ['bar' => ['borderRadius' => 4, 'horizontal' => true]],
            'colors' => ['#b8524d'],
            'dataLabels' => ['enabled' => false],
            'tooltip' => ['theme' => 'dark'],
        ]);
    }
}

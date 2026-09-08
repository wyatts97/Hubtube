<?php

namespace App\Filament\Widgets\Analytics;

use App\Filament\Widgets\Analytics\Concerns\DarkThemeOptions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Leandrocfe\FilamentApexCharts\Widgets\ApexChartWidget;

/**
 * Impressions and clicks per day, across every ad source.
 *
 * Before ad_stats_daily existed there was no per-day ad data at all — only
 * lifetime counters — so no trend of any kind could be shown.
 */
class AdDeliveryChartWidget extends ApexChartWidget
{
    use DarkThemeOptions;

    protected static ?string $chartId = 'adDeliveryChart';
    protected static ?int $contentHeight = 260;

    protected function getHeading(): ?string
    {
        return 'Ad Delivery · Last 30 Days';
    }

    public static function canView(): bool
    {
        return Schema::hasTable('ad_stats_daily');
    }

    protected function getOptions(): array
    {
        [$labels, $impressions, $clicks] = $this->buildSeries();

        // ApexCharts v5 crashes in getyAxisLabelsCoords when every value is
        // zero; a hair above zero renders identically and avoids it.
        if (max($impressions) === 0 && max($clicks) === 0) {
            $impressions[0] = 0.0001;
        }

        return $this->mergeTheme($this->darkThemeBase(), [
            'chart' => [
                'type' => 'area',
                'height' => 260,
                'stacked' => false,
            ],
            'series' => [
                ['name' => 'Impressions', 'data' => $impressions],
                ['name' => 'Clicks', 'data' => $clicks],
            ],
            'xaxis' => [
                'categories' => $labels,
                'tickAmount' => 8,
            ],
            'yaxis' => [
                'min' => 0,
                'labels' => [
                    'style' => ['colors' => '#64748b', 'fontSize' => '11px', 'fontFamily' => 'Inter'],
                ],
            ],
            'stroke' => ['curve' => 'smooth', 'width' => 2],
            'colors' => ['#b8524d', '#f59e0b'],
            'fill' => [
                'type' => 'gradient',
                'gradient' => [
                    'shadeIntensity' => 1,
                    'opacityFrom' => 0.40,
                    'opacityTo' => 0.05,
                    'stops' => [0, 90, 100],
                ],
            ],
            'markers' => ['size' => 0, 'hover' => ['size' => 4]],
            'legend' => ['show' => true, 'position' => 'top', 'horizontalAlign' => 'right'],
            'tooltip' => ['theme' => 'dark'],
        ]);
    }

    protected function buildSeries(): array
    {
        $rows = DB::table('ad_stats_daily')
            ->selectRaw('date, SUM(impressions) as impressions, SUM(clicks) as clicks')
            ->where('date', '>=', now()->subDays(29)->toDateString())
            ->groupBy('date')
            ->get()
            ->keyBy(fn ($row) => (string) $row->date);

        $labels = [];
        $impressions = [];
        $clicks = [];

        for ($i = 29; $i >= 0; $i--) {
            $d = now()->subDays($i)->format('Y-m-d');
            $labels[] = Carbon::parse($d)->format('M j');
            $impressions[] = (int) ($rows[$d]->impressions ?? 0);
            $clicks[] = (int) ($rows[$d]->clicks ?? 0);
        }

        return [$labels, $impressions, $clicks];
    }
}

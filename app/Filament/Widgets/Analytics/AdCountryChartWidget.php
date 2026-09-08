<?php

namespace App\Filament\Widgets\Analytics;

use App\Filament\Widgets\Analytics\Concerns\DarkThemeOptions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Leandrocfe\FilamentApexCharts\Widgets\ApexChartWidget;

/**
 * Top countries by ad impressions over the last 30 days.
 *
 * Country comes from Cloudflare's CF-IPCountry header. 'XX' covers requests
 * that never passed through the CDN (local dev, direct-to-origin) as well as
 * Cloudflare's own unknown or anonymised clients, so expect a real bucket
 * under that label rather than treating it as an error.
 */
class AdCountryChartWidget extends ApexChartWidget
{
    use DarkThemeOptions;

    protected static ?string $chartId = 'adCountryChart';
    protected static ?int $contentHeight = 260;

    protected function getHeading(): ?string
    {
        return 'Top Countries · Last 30 Days';
    }

    public static function canView(): bool
    {
        return Schema::hasTable('ad_stats_daily');
    }

    protected function getOptions(): array
    {
        $rows = DB::table('ad_stats_daily')
            ->selectRaw('country, SUM(impressions) as impressions')
            ->where('date', '>=', now()->subDays(29)->toDateString())
            ->groupBy('country')
            ->orderByDesc('impressions')
            ->limit(10)
            ->get();

        $labels = $rows->pluck('country')->map(fn ($c) => (string) $c)->all();
        $values = $rows->pluck('impressions')->map(fn ($v) => (int) $v)->all();

        // ApexCharts v5 crashes in getyAxisLabelsCoords on an all-zero series.
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
            'plotOptions' => ['bar' => ['borderRadius' => 4]],
            'colors' => ['#3b82f6'],
            'dataLabels' => ['enabled' => false],
            'tooltip' => ['theme' => 'dark'],
        ]);
    }
}

<?php

namespace App\Filament\Widgets\Analytics;

use App\Filament\Widgets\Analytics\Concerns\DarkThemeOptions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Leandrocfe\FilamentApexCharts\Widgets\ApexChartWidget;

/** Desktop / mobile / tablet split of ad impressions over the last 30 days. */
class AdDeviceChartWidget extends ApexChartWidget
{
    use DarkThemeOptions;

    protected static ?string $chartId = 'adDeviceChart';
    protected static ?int $contentHeight = 260;

    protected function getHeading(): ?string
    {
        return 'Impressions by Device · Last 30 Days';
    }

    public static function canView(): bool
    {
        return Schema::hasTable('ad_stats_daily');
    }

    protected function getOptions(): array
    {
        $rows = DB::table('ad_stats_daily')
            ->selectRaw('device, SUM(impressions) as impressions')
            ->where('date', '>=', now()->subDays(29)->toDateString())
            ->groupBy('device')
            ->orderByDesc('impressions')
            ->get();

        $labels = $rows->pluck('device')->map(fn ($d) => ucfirst((string) $d))->all();
        $values = $rows->pluck('impressions')->map(fn ($v) => (int) $v)->all();

        // A donut with an empty series renders as a blank box. One placeholder
        // slice keeps the card the same shape as its neighbours when there is
        // nothing to show yet.
        if (empty($values) || array_sum($values) === 0) {
            $labels = ['No data'];
            $values = [1];
        }

        return $this->mergeTheme($this->darkThemeBase(), [
            'chart' => ['type' => 'donut', 'height' => 260],
            'series' => $values,
            'labels' => $labels,
            'colors' => ['#b8524d', '#f59e0b', '#3b82f6'],
            'legend' => ['show' => true, 'position' => 'bottom'],
            'dataLabels' => ['enabled' => true],
            'tooltip' => ['theme' => 'dark'],
        ]);
    }
}

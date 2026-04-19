<?php

namespace App\Filament\Widgets\Analytics;

use App\Filament\Widgets\Analytics\Concerns\DarkThemeOptions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Leandrocfe\FilamentApexCharts\Widgets\ApexChartWidget;

class SignupsChartWidget extends ApexChartWidget
{
    use DarkThemeOptions;

    protected static ?string $chartId = 'signupsChart';
    protected static ?string $pollingInterval = null;
    protected static ?int $contentHeight = 260;

    protected function getHeading(): ?string
    {
        return 'New Registrations · Last 30 Days';
    }

    protected function getOptions(): array
    {
        [$labels, $values] = $this->buildSeries();
        $hasData = max($values) > 0;

        if (! $hasData) {
            $values[0] = 0.0001;
        }

        return $this->mergeTheme($this->darkThemeBase(), [
            'chart' => [
                'type'   => 'area',
                'height' => 260,
            ],
            'series' => [[
                'name' => 'Signups',
                'data' => $values,
            ]],
            'xaxis' => [
                'categories' => $labels,
                'tickAmount' => 8,
            ],
            'yaxis' => [
                'min' => 0,
            ],
            'stroke' => [
                'curve' => 'smooth',
                'width' => 2,
            ],
            'colors' => ['#10b981'],
            'fill' => [
                'type' => 'gradient',
                'gradient' => [
                    'shadeIntensity' => 1,
                    'opacityFrom'    => 0.45,
                    'opacityTo'      => 0.05,
                    'stops'          => [0, 90, 100],
                ],
            ],
            'markers' => ['size' => 0, 'hover' => ['size' => 4]],
        ]);
    }

    protected function buildSeries(): array
    {
        $rows = DB::table('users')
            ->selectRaw('DATE(created_at) as date, COUNT(*) as count')
            ->where('created_at', '>=', now()->subDays(29)->startOfDay())
            ->groupBy('date')
            ->pluck('count', 'date');

        $labels = [];
        $values = [];
        for ($i = 29; $i >= 0; $i--) {
            $d = now()->subDays($i)->format('Y-m-d');
            $labels[] = Carbon::parse($d)->format('M j');
            $values[] = (int) ($rows[$d] ?? 0);
        }
        return [$labels, $values];
    }
}

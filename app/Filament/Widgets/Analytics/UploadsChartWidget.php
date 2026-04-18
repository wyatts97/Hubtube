<?php

namespace App\Filament\Widgets\Analytics;

use App\Filament\Widgets\Analytics\Concerns\DarkThemeOptions;
use App\Models\Video;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Leandrocfe\FilamentApexCharts\Widgets\ApexChartWidget;

class UploadsChartWidget extends ApexChartWidget
{
    use DarkThemeOptions;

    protected static ?string $chartId = 'uploadsChart';
    protected static ?string $pollingInterval = '60s';
    protected static ?int $contentHeight = 260;

    protected function getHeading(): ?string
    {
        return 'Video Uploads · Last 30 Days';
    }

    protected function getOptions(): array
    {
        [$labels, $values] = $this->buildSeries();

        return $this->mergeTheme($this->darkThemeBase(), [
            'chart' => [
                'type'   => 'area',
                'height' => 260,
                'sparkline' => ['enabled' => false],
            ],
            'series' => [[
                'name' => 'Uploads',
                'data' => $values,
            ]],
            'xaxis' => [
                'categories' => $labels,
                'tickAmount' => 8,
            ],
            'yaxis' => [
                'labels' => ['formatter' => null],
                'min'    => 0,
                'forceNiceScale' => true,
            ],
            'stroke' => [
                'curve' => 'smooth',
                'width' => 2,
            ],
            'colors' => ['#6366f1'],
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

    /**
     * @return array{0: array<string>, 1: array<int>}
     */
    protected function buildSeries(): array
    {
        $rows = DB::table('videos')
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

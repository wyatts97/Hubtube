<?php

namespace App\Filament\Widgets\Analytics;

use App\Filament\Widgets\Analytics\Concerns\DarkThemeOptions;
use Illuminate\Support\Facades\DB;
use Leandrocfe\FilamentApexCharts\Widgets\ApexChartWidget;

class CategoryViewsChartWidget extends ApexChartWidget
{
    use DarkThemeOptions;

    protected static ?string $chartId = 'categoryViewsChart';
    protected static ?string $pollingInterval = '120s';
    protected static ?int $contentHeight = 260;

    protected function getHeading(): ?string
    {
        return 'Views by Category · Top 10';
    }

    protected function getOptions(): array
    {
        $rows = DB::table('videos')
            ->join('categories', 'videos.category_id', '=', 'categories.id')
            ->selectRaw('categories.name as category, SUM(videos.views_count) as total_views')
            ->whereNotNull('videos.category_id')
            ->groupBy('categories.id', 'categories.name')
            ->orderByDesc('total_views')
            ->limit(10)
            ->get();

        $labels = $rows->pluck('category')->map(fn ($v) => (string) $v)->toArray();
        $values = $rows->pluck('total_views')->map(fn ($v) => (int) $v)->toArray();

        return $this->mergeTheme($this->darkThemeBase(), [
            'chart' => [
                'type'   => 'bar',
                'height' => 260,
            ],
            'series' => [[
                'name' => 'Views',
                'data' => $values,
            ]],
            'xaxis' => [
                'categories' => $labels,
            ],
            'plotOptions' => [
                'bar' => [
                    'horizontal'   => true,
                    'borderRadius' => 4,
                    'barHeight'    => '70%',
                ],
            ],
            'colors' => ['#6366f1'],
            'fill' => [
                'type' => 'gradient',
                'gradient' => [
                    'shade'           => 'dark',
                    'type'            => 'horizontal',
                    'gradientToColors' => ['#a855f7'],
                    'stops'           => [0, 100],
                ],
            ],
        ]);
    }
}

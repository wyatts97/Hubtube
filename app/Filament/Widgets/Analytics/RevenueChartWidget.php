<?php

namespace App\Filament\Widgets\Analytics;

use App\Filament\Widgets\Analytics\Concerns\DarkThemeOptions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Leandrocfe\FilamentApexCharts\Widgets\ApexChartWidget;

class RevenueChartWidget extends ApexChartWidget
{
    use DarkThemeOptions;

    protected static ?string $chartId = 'revenueChart';
    protected static ?string $pollingInterval = '120s';
    protected static ?int $contentHeight = 260;

    protected function getHeading(): ?string
    {
        return 'Deposit Revenue · Last 30 Days';
    }

    public static function canView(): bool
    {
        return Schema::hasTable('wallet_transactions');
    }

    protected function getOptions(): array
    {
        [$labels, $values] = $this->buildSeries();
        $hasData = max($values) > 0;

        return $this->mergeTheme($this->darkThemeBase(), [
            'chart' => [
                'type'   => 'area',
                'height' => 260,
            ],
            'series' => [[
                'name' => 'Revenue',
                'data' => $values,
            ]],
            'xaxis' => [
                'categories' => $labels,
                'tickAmount' => 8,
            ],
            'yaxis' => array_filter([
                'min'            => 0,
                'max'            => $hasData ? null : 100,
                'forceNiceScale' => $hasData,
                'labels' => [
                    'style' => ['colors' => '#64748b', 'fontSize' => '11px', 'fontFamily' => 'Inter'],
                ],
            ], fn ($v) => $v !== null),
            'stroke' => [
                'curve' => 'smooth',
                'width' => 2,
            ],
            'colors' => ['#f59e0b'],
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
            'tooltip' => [
                'theme' => 'dark',
            ],
        ]);
    }

    protected function buildSeries(): array
    {
        $rows = DB::table('wallet_transactions')
            ->selectRaw('DATE(created_at) as date, SUM(amount) as total')
            ->where('created_at', '>=', now()->subDays(29)->startOfDay())
            ->where('type', 'deposit')
            ->groupBy('date')
            ->pluck('total', 'date');

        $labels = [];
        $values = [];
        for ($i = 29; $i >= 0; $i--) {
            $d = now()->subDays($i)->format('Y-m-d');
            $labels[] = Carbon::parse($d)->format('M j');
            $values[] = round((float) ($rows[$d] ?? 0), 2);
        }
        return [$labels, $values];
    }
}

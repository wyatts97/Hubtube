<?php

namespace App\Filament\Widgets\Analytics\Concerns;

/**
 * Shared ApexCharts theme options for the HubTube admin.
 *
 * Produces a tasteful dark-futuristic look: transparent bg, muted
 * gridlines, Inter font, rounded tooltips, hidden toolbar. Consumers
 * merge additional chart-type-specific options on top.
 */
trait DarkThemeOptions
{
    protected function darkThemeBase(): array
    {
        return [
            'chart' => [
                'toolbar'      => ['show' => false],
                'background'   => 'transparent',
                'foreColor'    => '#9ca3af',
                'animations'   => ['enabled' => true, 'speed' => 350],
                'dropShadow'   => ['enabled' => false],
                'fontFamily'   => 'Inter, ui-sans-serif, system-ui, sans-serif',
                'zoom'         => ['enabled' => false],
            ],
            'grid' => [
                'borderColor'  => 'rgba(148, 163, 184, 0.12)',
                'strokeDashArray' => 4,
                'xaxis' => ['lines' => ['show' => false]],
                'yaxis' => ['lines' => ['show' => true]],
                'padding' => ['top' => 0, 'right' => 12, 'bottom' => 0, 'left' => 12],
            ],
            'dataLabels' => ['enabled' => false],
            'tooltip' => [
                'theme' => 'dark',
                'style' => ['fontSize' => '12px', 'fontFamily' => 'Inter'],
                'x'     => ['show' => true],
            ],
            'legend' => [
                'show'           => false,
                'labels'         => ['colors' => '#9ca3af'],
                'fontFamily'     => 'Inter',
                'fontSize'       => '12px',
            ],
            'xaxis' => [
                'labels' => [
                    'style' => ['colors' => '#64748b', 'fontSize' => '11px', 'fontFamily' => 'Inter'],
                ],
                'axisBorder' => ['show' => false],
                'axisTicks'  => ['show' => false],
            ],
            'yaxis' => [
                'labels' => [
                    'style' => ['colors' => '#64748b', 'fontSize' => '11px', 'fontFamily' => 'Inter'],
                ],
            ],
        ];
    }

    /**
     * Recursively merge arrays so callers can override any nested option.
     */
    protected function mergeTheme(array ...$arrays): array
    {
        $merged = array_shift($arrays) ?? [];
        foreach ($arrays as $arr) {
            foreach ($arr as $k => $v) {
                if (is_array($v) && isset($merged[$k]) && is_array($merged[$k])) {
                    $merged[$k] = $this->mergeTheme($merged[$k], $v);
                } else {
                    $merged[$k] = $v;
                }
            }
        }
        return $merged;
    }
}

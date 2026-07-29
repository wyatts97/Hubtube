<?php

namespace App\Filament\Widgets;

use App\Models\VisitorDaily;
use Carbon\Carbon;
use Filament\Widgets\Widget;

class VisitorsWidget extends Widget
{
    protected static string $view = 'filament.widgets.visitors-widget';

    protected int | string | array $columnSpan = 1;

    protected static ?int $sort = 1;

    public string $visitorRange = '7d';

    public function setVisitorRange(string $range): void
    {
        if (!in_array($range, ['1d', '7d', '14d'])) {
            return;
        }
        $this->visitorRange = $range;
    }

    public function getVisitorData(): array
    {
        $days = match ($this->visitorRange) {
            '1d'   => 1,
            '7d'   => 7,
            '14d'  => 14,
            default => 7,
        };

        $startDate = now()->subDays($days - 1)->startOfDay();

        // Unique visitors in range
        $uniqueCount = VisitorDaily::sinceDate($startDate)
            ->distinct('visitor_hash')
            ->count('visitor_hash');

        // Total visits in range
        $totalVisits = VisitorDaily::sinceDate($startDate)
            ->sum('visit_count');

        // Build chart data
        $chartData = VisitorDaily::sinceDate($startDate)
            ->selectRaw('date, COUNT(DISTINCT visitor_hash) as unique_visitors')
            ->groupBy('date')
            ->pluck('unique_visitors', 'date')
            ->toArray();

        $chart = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $date = now()->copy()->subDays($i)->toDateString();
            $chart[] = $chartData[$date] ?? 0;
        }

        $label = match ($this->visitorRange) {
            '1d'   => 'Today',
            '7d'   => 'Last 7 days',
            '14d'  => 'Last 14 days',
            default => 'Last 7 days',
        };

        return [
            'count'       => $uniqueCount,
            'total_visits' => $totalVisits,
            'label'        => $label,
            'chart'        => $chart,
        ];
    }
}

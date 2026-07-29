<?php

namespace App\Filament\Widgets;

use App\Models\VisitorDaily;
use Carbon\Carbon;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Schema;
use Throwable;

class VisitorsWidget extends Widget
{
    protected string $view = 'filament.widgets.visitors-widget';

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

        $label = match ($this->visitorRange) {
            '1d'   => 'Today',
            '7d'   => 'Last 7 days',
            '14d'  => 'Last 14 days',
            default => 'Last 7 days',
        };

        $empty = [
            'count'        => 0,
            'total_visits' => 0,
            'label'        => $label,
            'chart'        => array_fill(0, $days, 0),
        ];

        try {
            if (!Schema::hasTable('visitor_daily')) {
                return $empty;
            }

            $startDate = now()->subDays($days - 1)->startOfDay();

            $uniqueCount = VisitorDaily::sinceDate($startDate)
                ->distinct('visitor_hash')
                ->count('visitor_hash');

            $totalVisits = (int) VisitorDaily::sinceDate($startDate)
                ->sum('visit_count');

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

            return [
                'count'        => $uniqueCount,
                'total_visits' => $totalVisits,
                'label'        => $label,
                'chart'        => $chart,
            ];
        } catch (Throwable) {
            return $empty;
        }
    }
}

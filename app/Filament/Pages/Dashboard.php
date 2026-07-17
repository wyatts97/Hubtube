<?php

namespace App\Filament\Pages;

use Awcodes\Overlook\Widgets\OverlookWidget;
use App\Filament\Widgets\RecentSignupsTable;
use App\Filament\Widgets\RecentUploadsTable;
use App\Filament\Widgets\StatsOverview;
use App\Filament\Widgets\TrendingVideosTable;
use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{

    protected static string | \UnitEnum | null $navigationGroup = 'Overview';

    public function getGreeting(): string
    {
        $hour = now()->hour;
        $name = auth()->user()?->username ?? 'Admin';
        $greeting = match (true) {
            $hour < 12 => 'Good morning',
            $hour < 17 => 'Good afternoon',
            default    => 'Good evening',
        };
        return "{$greeting}, {$name}";
    }

    public function getSubheading(): ?string
    {
        return $this->getGreeting();
    }

    /**
     * Dashboard widgets rendered in Filament's default responsive grid.
     */
    public function getWidgets(): array
    {
        $widgets = [StatsOverview::class];

        if (class_exists(OverlookWidget::class)) {
            $widgets[] = OverlookWidget::class;
        }

        return array_merge($widgets, [
            TrendingVideosTable::class,
            RecentUploadsTable::class,
            RecentSignupsTable::class,
        ]);
    }
}

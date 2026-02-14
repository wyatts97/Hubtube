<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\RecentSignupsTable;
use App\Filament\Widgets\RecentUploadsTable;
use App\Filament\Widgets\StatsOverview;
use App\Filament\Widgets\TrendingVideosTable;
use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    public function getColumns(): int|string|array
    {
        return 2;
    }

    public function getWidgets(): array
    {
        return [
            StatsOverview::class,
            TrendingVideosTable::class,
            RecentUploadsTable::class,
            RecentSignupsTable::class,
        ];
    }
}

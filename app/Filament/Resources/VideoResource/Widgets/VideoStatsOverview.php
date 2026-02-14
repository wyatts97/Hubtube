<?php

namespace App\Filament\Resources\VideoResource\Widgets;

use App\Models\Video;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class VideoStatsOverview extends BaseWidget
{
    protected function getStats(): array
    {
        return [
            Stat::make('Total Videos', Video::count())
                ->icon('heroicon-o-video-camera')
                ->color('primary'),

            Stat::make('Awaiting Moderation', Video::where('is_approved', false)->where('status', 'processed')->count())
                ->icon('heroicon-o-shield-check')
                ->color('warning')
                ->description('Published but not yet approved'),

            Stat::make('Processing', Video::whereIn('status', ['pending', 'processing'])->count())
                ->icon('heroicon-o-arrow-path')
                ->color('info')
                ->description('In queue or actively processing'),

            Stat::make('Total Views', number_format(Video::sum('views_count')))
                ->icon('heroicon-o-eye')
                ->color('success'),
        ];
    }
}

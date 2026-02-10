<?php

namespace App\Filament\Widgets;

use App\Models\LiveStream;
use App\Models\User;
use App\Models\Video;
use App\Models\WalletTransaction;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StatsOverview extends BaseWidget
{
    protected function getStats(): array
    {
        try {
            return [
                Stat::make('Total Users', User::count())
                    ->description('Registered users')
                    ->descriptionIcon('heroicon-m-users')
                    ->color('primary'),
                Stat::make('Total Videos', Video::count())
                    ->description('Uploaded videos')
                    ->descriptionIcon('heroicon-m-video-camera')
                    ->color('success'),
                Stat::make('Live Streams', LiveStream::where('status', 'live')->count())
                    ->description('Currently live')
                    ->descriptionIcon('heroicon-m-signal')
                    ->color('danger'),
                Stat::make('Revenue', '$' . number_format(
                    WalletTransaction::where('type', 'deposit')
                        ->where('status', 'completed')
                        ->sum('amount'), 2
                ))
                    ->description('Total deposits')
                    ->descriptionIcon('heroicon-m-banknotes')
                    ->color('warning'),
            ];
        } catch (\Throwable $e) {
            return [];
        }
    }
}

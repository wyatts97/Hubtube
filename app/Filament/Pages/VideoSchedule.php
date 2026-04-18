<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\VideoScheduleCalendarWidget;
use Filament\Pages\Page;

class VideoSchedule extends Page
{
    protected static ?string $navigationIcon  = 'heroicon-o-calendar-days';
    protected static ?string $navigationLabel = 'Publish Schedule';
    protected static ?string $navigationGroup = 'Content';
    protected static ?int    $navigationSort  = 5;
    protected static string  $view            = 'filament.pages.video-schedule';

    public function getHeaderWidgets(): array
    {
        return [
            VideoScheduleCalendarWidget::class,
        ];
    }

    public function getHeaderWidgetsColumns(): int|array
    {
        return 1;
    }
}

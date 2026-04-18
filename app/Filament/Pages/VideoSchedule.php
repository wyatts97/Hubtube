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

    public static function shouldRegisterNavigation(): bool
    {
        // Only show in sidebar if the FullCalendar package has been installed.
        return class_exists(\Saade\FilamentFullCalendar\Widgets\FullCalendarWidget::class);
    }

    protected function getHeaderWidgets(): array
    {
        if (!class_exists(VideoScheduleCalendarWidget::class)) {
            return [];
        }
        if (!class_exists(\Saade\FilamentFullCalendar\Widgets\FullCalendarWidget::class)) {
            return [];
        }
        return [VideoScheduleCalendarWidget::class];
    }
}

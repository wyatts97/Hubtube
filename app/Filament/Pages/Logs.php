<?php

namespace App\Filament\Pages;

use LaBoiteACode\FilamentLogsExplorer\Pages\LogsExplorer as BaseLogsExplorer;

class Logs extends BaseLogsExplorer
{
    public static function getNavigationLabel(): string
    {
        return 'Logs';
    }

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return 'Tools';
    }

    public static function getNavigationSort(): ?int
    {
        return 95;
    }
}

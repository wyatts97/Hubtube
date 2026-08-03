<?php

namespace App\Filament\Pages;

use LaBoiteACode\FilamentLogsExplorer\Pages\LogsExplorer as BaseLogsExplorer;

class Logs extends BaseLogsExplorer
{
    protected static ?string $navigationLabel = 'Logs';

    protected static ?string $navigationIcon = 'heroicon-o-document-magnifying-glass';

    protected static string|\UnitEnum|null $navigationGroup = 'Tools';

    protected static ?int $navigationSort = 95;
}

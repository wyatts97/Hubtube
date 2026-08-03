<?php

namespace App\Filament\Pages;

use LaBoiteACode\FilamentLogsExplorer\Pages\LogsExplorer as BaseLogsExplorer;

class Logs extends BaseLogsExplorer
{
    protected static $navigationLabel = 'Logs';

    protected static $navigationIcon = 'heroicon-o-document-magnifying-glass';

    protected static $navigationGroup = 'Tools';

    protected static $navigationSort = 95;
}

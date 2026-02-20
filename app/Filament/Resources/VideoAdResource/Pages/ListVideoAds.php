<?php

namespace App\Filament\Resources\VideoAdResource\Pages;

use App\Filament\Resources\VideoAdResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListVideoAds extends ListRecords
{
    protected static string $resource = VideoAdResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}

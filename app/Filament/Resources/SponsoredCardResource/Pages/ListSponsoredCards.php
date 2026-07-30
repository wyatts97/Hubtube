<?php

namespace App\Filament\Resources\SponsoredCardResource\Pages;

use Filament\Actions\CreateAction;
use App\Filament\Resources\SponsoredCardResource;
use Filament\Resources\Pages\ListRecords;

class ListSponsoredCards extends ListRecords
{
    protected static string $resource = SponsoredCardResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}

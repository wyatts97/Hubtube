<?php

namespace App\Filament\Resources\SponsoredCardResource\Pages;

use App\Filament\Resources\SponsoredCardResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListSponsoredCards extends ListRecords
{
    protected static string $resource = SponsoredCardResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}

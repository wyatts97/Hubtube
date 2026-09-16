<?php

namespace App\Filament\Resources\EncodeProfileResource\Pages;

use App\Filament\Resources\EncodeProfileResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListEncodeProfiles extends ListRecords
{
    protected static string $resource = EncodeProfileResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Add')->icon('phosphor-plus'),
        ];
    }
}

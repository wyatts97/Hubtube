<?php

namespace App\Filament\Resources\DmcaRequestResource\Pages;

use App\Filament\Resources\DmcaRequestResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\ViewRecord;

class ViewDmcaRequest extends ViewRecord
{
    protected static string $resource = DmcaRequestResource::class;

    protected function getHeaderActions(): array
    {
        return array_merge(
            DmcaRequestResource::resolutionActions(),
            [
                DeleteAction::make()
                    ->label('Delete')
                    ->icon('phosphor-trash')
                    ->color('danger')
                    ->requiresConfirmation(),
            ],
        );
    }
}

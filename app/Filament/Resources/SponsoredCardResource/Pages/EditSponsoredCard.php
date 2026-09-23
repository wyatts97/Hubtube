<?php

namespace App\Filament\Resources\SponsoredCardResource\Pages;

use App\Filament\Resources\SponsoredCardResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditSponsoredCard extends EditRecord
{
    protected static string $resource = SponsoredCardResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}

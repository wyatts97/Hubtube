<?php

namespace App\Filament\Resources\EncodeProfileResource\Pages;

use App\Filament\Resources\EncodeProfileResource;
use Filament\Resources\Pages\CreateRecord;

class CreateEncodeProfile extends CreateRecord
{
    protected static string $resource = EncodeProfileResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}

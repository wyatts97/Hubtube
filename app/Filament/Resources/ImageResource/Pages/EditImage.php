<?php

namespace App\Filament\Resources\ImageResource\Pages;

use Filament\Actions\DeleteAction;
use App\Filament\Resources\ImageResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditImage extends EditRecord
{
    protected static string $resource = ImageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function resolveRecord($key): Model
    {
        return ImageResource::getModel()::where('id', $key)->firstOrFail();
    }
}

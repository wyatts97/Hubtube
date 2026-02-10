<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Extract privileged fields that aren't mass-assignable
        $privileged = ['is_admin', 'is_pro', 'is_verified'];
        foreach ($privileged as $field) {
            if (array_key_exists($field, $data)) {
                $this->record->forceFill([$field => $data[$field]]);
                unset($data[$field]);
            }
        }

        return $data;
    }
}

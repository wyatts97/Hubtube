<?php

namespace App\Filament\Resources\UserResource\Pages;

use Filament\Actions\DeleteAction;
use Illuminate\Support\Facades\Auth;
use App\Filament\Resources\UserResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Extract privileged fields that aren't mass-assignable
        $privileged = ['is_admin', 'is_super_admin', 'is_pro', 'is_verified'];
        foreach ($privileged as $field) {
            if (array_key_exists($field, $data)) {
                $this->record->forceFill([$field => $data[$field]]);
                unset($data[$field]);
            }
        }

        // Only a super-admin may grant or revoke the super-admin tier. The form
        // field is disabled and undehydrated for everyone else, but re-check here
        // so a crafted Livewire payload cannot set it either.
        if (! Auth::user()?->isSuperAdmin()) {
            $this->record->forceFill([
                'is_super_admin' => $this->record->getOriginal('is_super_admin'),
            ]);
        }

        return $data;
    }
}

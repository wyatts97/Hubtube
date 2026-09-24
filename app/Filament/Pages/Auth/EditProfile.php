<?php

namespace App\Filament\Pages\Auth;

use Filament\Auth\Pages\EditProfile as BaseEditProfile;

class EditProfile extends BaseEditProfile
{
    protected function mutateFormDataBeforeFill(array $data): array
    {
        return [...$data, ...$this->getUser()->attributesForAdminForm()];
    }
}

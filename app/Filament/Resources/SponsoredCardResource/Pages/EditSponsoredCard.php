<?php

namespace App\Filament\Resources\SponsoredCardResource\Pages;

use Filament\Actions\DeleteAction;
use App\Filament\Resources\SponsoredCardResource;
use Filament\Actions;
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

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['category_ids'] = !empty($data['category_ids']) ? array_map('intval', $data['category_ids']) : null;
        $data['target_roles'] = !empty($data['target_roles']) ? $data['target_roles'] : null;
        $data['target_pages'] = !empty($data['target_pages']) ? $data['target_pages'] : null;
        return $data;
    }
}

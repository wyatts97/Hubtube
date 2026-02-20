<?php

namespace App\Filament\Resources\VideoAdResource\Pages;

use App\Filament\Resources\VideoAdResource;
use Filament\Resources\Pages\CreateRecord;

class CreateVideoAd extends CreateRecord
{
    protected static string $resource = VideoAdResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['category_ids'] = !empty($data['category_ids']) ? array_map('intval', $data['category_ids']) : null;
        $data['target_roles'] = !empty($data['target_roles']) ? $data['target_roles'] : null;

        // If a file was uploaded, clear the external URL field
        if (!empty($data['file_path'])) {
            $data['content'] = '';
        }

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}

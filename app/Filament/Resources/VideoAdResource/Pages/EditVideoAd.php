<?php

namespace App\Filament\Resources\VideoAdResource\Pages;

use App\Filament\Resources\VideoAdResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Storage;

class EditVideoAd extends EditRecord
{
    protected static string $resource = VideoAdResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->after(function () {
                    $record = $this->getRecord();
                    if ($record->file_path && Storage::disk('public')->exists($record->file_path)) {
                        Storage::disk('public')->delete($record->file_path);
                    }
                }),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['category_ids'] = $data['category_ids'] ?? [];
        $data['target_roles'] = $data['target_roles'] ?? [];
        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['category_ids'] = !empty($data['category_ids']) ? array_map('intval', $data['category_ids']) : null;
        $data['target_roles'] = !empty($data['target_roles']) ? $data['target_roles'] : null;

        // Ensure content is never null (DB column is NOT NULL)
        $data['content'] = $data['content'] ?? '';

        // If a new file was uploaded, clear the external URL
        if (!empty($data['file_path']) && $data['file_path'] !== $this->getRecord()->file_path) {
            // Delete old file if it existed
            $oldPath = $this->getRecord()->file_path;
            if ($oldPath && Storage::disk('public')->exists($oldPath)) {
                Storage::disk('public')->delete($oldPath);
            }
            $data['content'] = '';
        }

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}

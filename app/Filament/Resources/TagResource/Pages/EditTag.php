<?php

namespace App\Filament\Resources\TagResource\Pages;

use App\Filament\Resources\TagResource;
use App\Services\TagSyncService;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditTag extends EditRecord
{
    protected static string $resource = TagResource::class;

    protected ?string $oldSlug = null;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->oldSlug = (string) $this->record->slug;

        return $data;
    }

    protected function afterSave(): void
    {
        app(TagSyncService::class)->renameTagBySlug(
            $this->oldSlug ?: (string) $this->record->slug,
            (string) $this->record->name,
            (string) $this->record->slug,
            $this->record,
        );
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->before(fn () => app(TagSyncService::class)->deleteTag($this->record)),
        ];
    }
}

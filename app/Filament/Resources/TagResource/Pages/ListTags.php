<?php

namespace App\Filament\Resources\TagResource\Pages;

use Filament\Actions\Action;
use App\Filament\Resources\TagResource;
use App\Services\TagSyncService;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListTags extends ListRecords
{
    protected static string $resource = TagResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('syncFromVideos')
                ->label('Sync From Videos')
                ->icon('phosphor-arrows-clockwise')
                ->requiresConfirmation()
                ->action(function (): void {
                    app(TagSyncService::class)->syncAllFromVideoJson();

                    Notification::make()
                        ->success()
                        ->title('Tags synchronized from videos.')
                        ->send();
                }),
        ];
    }
}

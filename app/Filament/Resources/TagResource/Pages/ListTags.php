<?php

namespace App\Filament\Resources\TagResource\Pages;

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
            Actions\Action::make('syncFromVideos')
                ->label('Sync From Videos')
                ->icon('heroicon-o-arrow-path')
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

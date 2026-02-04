<?php

namespace App\Filament\Resources\EmbeddedVideoResource\Pages;

use App\Filament\Resources\EmbeddedVideoResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListEmbeddedVideos extends ListRecords
{
    protected static string $resource = EmbeddedVideoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('embedder')
                ->label('Import Videos')
                ->icon('heroicon-o-arrow-down-tray')
                ->url(fn () => route('filament.admin.pages.video-embedder')),
        ];
    }
}

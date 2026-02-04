<?php

namespace App\Filament\Resources\EmbeddedVideoResource\Pages;

use App\Filament\Resources\EmbeddedVideoResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditEmbeddedVideo extends EditRecord
{
    protected static string $resource = EmbeddedVideoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('preview')
                ->label('Preview on Source')
                ->icon('heroicon-o-play')
                ->url(fn () => $this->record->source_url)
                ->openUrlInNewTab(),
            Actions\DeleteAction::make(),
        ];
    }
}

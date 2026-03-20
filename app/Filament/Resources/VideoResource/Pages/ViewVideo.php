<?php

namespace App\Filament\Resources\VideoResource\Pages;

use App\Filament\Resources\VideoResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewVideo extends ViewRecord
{
    protected static string $resource = VideoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),

            Actions\Action::make('view_frontend')
                ->label('View on Site')
                ->icon('heroicon-o-eye')
                ->color('gray')
                ->url(fn () => url('/' . $this->record->slug))
                ->openUrlInNewTab()
                ->visible(fn () => $this->record->status === 'processed' && $this->record->is_approved),
        ];
    }
}

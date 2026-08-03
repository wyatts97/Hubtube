<?php

namespace App\Filament\Resources\ReportResource\Pages;

use App\Filament\Resources\ReportResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\ViewRecord;

class ViewReport extends ViewRecord
{
    protected static string $resource = ReportResource::class;

    protected function getHeaderActions(): array
    {
        return array_merge(
            ReportResource::resolutionActions(),
            [
                DeleteAction::make()
                    ->label('Delete')
                    ->icon('phosphor-trash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalDescription('This will permanently delete the report and its associated inbox entry. This action cannot be undone.'),
            ],
        );
    }
}

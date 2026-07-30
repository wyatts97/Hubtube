<?php

namespace App\Filament\Resources\SponsoredCardResource\Pages;

use Filament\Actions\CreateAction;
use App\Filament\Resources\SponsoredCardResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ListSponsoredCards extends ListRecords
{
    protected static string $resource = SponsoredCardResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Add')->icon('phosphor-plus'),
        ];
    }

    public function table(Table $table): Table
    {
        return parent::table($table);
    }

    protected function getTableQuery(): Builder
    {
        return parent::getTableQuery();
    }
}

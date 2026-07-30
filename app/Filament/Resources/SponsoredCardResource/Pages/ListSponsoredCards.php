<?php

namespace App\Filament\Resources\SponsoredCardResource\Pages;

use Filament\Actions\CreateAction;
use App\Filament\Resources\SponsoredCardResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Tables\Table;

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

    protected function getTableQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return parent::getTableQuery()->withoutGlobalScopes();
    }
}

<?php

namespace App\Filament\Resources\ImageResource\Pages;

use Filament\Schemas\Components\Tabs\Tab;
use App\Filament\Resources\ImageResource;
use App\Models\Image;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListImages extends ListRecords
{
    protected static string $resource = ImageResource::class;

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('All Images')
                ->icon('phosphor-image')
                ->badge(Image::count()),

            'moderation' => Tab::make('Needs Moderation')
                ->icon('phosphor-shield-check')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('is_approved', false))
                ->badge(Image::where('is_approved', false)->count() ?: null)
                ->badgeColor('warning'),

            'animated' => Tab::make('Animated')
                ->icon('phosphor-play')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('is_animated', true)),
        ];
    }
}

<?php

namespace App\Filament\Resources\VideoResource\Pages;

use App\Filament\Resources\VideoResource;
use App\Filament\Resources\VideoResource\Widgets\VideoStatsOverview;
use App\Models\Video;
use Filament\Actions;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListVideos extends ListRecords
{
    protected static string $resource = VideoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('All Videos')
                ->icon('heroicon-o-video-camera')
                ->badge(Video::count()),

            'moderation' => Tab::make('Needs Moderation')
                ->icon('heroicon-o-shield-check')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('is_approved', false)->where('status', 'processed'))
                ->badge(Video::where('is_approved', false)->where('status', 'processed')->count())
                ->badgeColor('warning'),

            'processing' => Tab::make('Processing')
                ->icon('heroicon-o-arrow-path')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereIn('status', ['pending', 'processing']))
                ->badge(Video::whereIn('status', ['pending', 'processing'])->count())
                ->badgeColor('info'),

            'failed' => Tab::make('Failed')
                ->icon('heroicon-o-x-circle')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'failed'))
                ->badge(Video::where('status', 'failed')->count() ?: null)
                ->badgeColor('danger'),

            'featured' => Tab::make('Featured')
                ->icon('heroicon-o-star')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('is_featured', true)),

            'shorts' => Tab::make('Shorts')
                ->icon('heroicon-o-device-phone-mobile')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('is_short', true)),

            'embedded' => Tab::make('Embedded')
                ->icon('heroicon-o-link')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('is_embedded', true)),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            VideoStatsOverview::class,
        ];
    }
}

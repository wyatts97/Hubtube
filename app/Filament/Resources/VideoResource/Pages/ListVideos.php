<?php

namespace App\Filament\Resources\VideoResource\Pages;

use Filament\Actions\CreateAction;
use Filament\Schemas\Components\Tabs\Tab;
use App\Filament\Resources\VideoResource;
use App\Filament\Resources\VideoResource\Widgets\VideoStatsOverview;
use App\Models\Video;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListVideos extends ListRecords
{
    protected static string $resource = VideoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('All Videos')
            ->icon('phosphor-video-camera')
            ->badge(fn () => Video::count()),

            'moderation' => Tab::make('Needs Moderation')
            ->icon('phosphor-shield-check')
            ->modifyQueryUsing(fn(Builder $query) => $query->where('is_approved', false)->where('status', 'processed')->whereNull('queue_order'))
            ->badge(fn () => Video::where('is_approved', false)->where('status', 'processed')->whereNull('queue_order')->count())
            ->badgeColor('warning'),

            'scheduled' => Tab::make('Scheduled')
            ->icon('phosphor-calendar')
            ->modifyQueryUsing(fn(Builder $query) => $query->whereNull('published_at')->where('status', 'processed')->where(fn($q) => $q->whereNotNull('scheduled_at')->orWhereNotNull('queue_order')))
            ->badge(fn () => Video::whereNull('published_at')->where('status', 'processed')->where(fn($q) => $q->whereNotNull('scheduled_at')->orWhereNotNull('queue_order'))->count())
            ->badgeColor('info'),

            'processing' => Tab::make('Processing')
            ->icon('phosphor-arrows-clockwise')
            ->modifyQueryUsing(fn(Builder $query) => $query->whereIn('status', ['pending', 'processing']))
            ->badge(fn () => Video::whereIn('status', ['pending', 'processing'])->count())
            ->badgeColor('info'),

            'failed' => Tab::make('Failed')
            ->icon('phosphor-x-circle')
            ->modifyQueryUsing(fn(Builder $query) => $query->where('status', 'failed'))
            ->badge(fn () => Video::where('status', 'failed')->count() ?: null)
            ->badgeColor('danger'),

            'featured' => Tab::make('Featured')
            ->icon('phosphor-star')
            ->modifyQueryUsing(fn(Builder $query) => $query->where('is_featured', true)),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            VideoStatsOverview::class ,
        ];
    }
}

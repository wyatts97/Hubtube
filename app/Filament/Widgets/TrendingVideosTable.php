<?php

namespace App\Filament\Widgets;

use App\Models\Video;
use Filament\Widgets\Widget;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;

/**
 * Custom trending-videos widget — a visual card list with thumbnails,
 * rank numbers, and stat pills. Keeps the class name stable so Dashboard
 * layout persistence continues to work.
 */
class TrendingVideosTable extends Widget
{
    protected static bool $isDiscovered = false;

    protected static string $view = 'filament.widgets.trending-videos-table';

    protected int|string|array $columnSpan = 1;

    protected static ?int $sort = 2;

    public string $trendingPeriod = 'week';

    public function setPeriod(string $period): void
    {
        $this->trendingPeriod = in_array($period, ['today', 'week', 'month', 'year', 'all'], true)
            ? $period
            : 'week';
    }

    public function getPeriodLabel(): string
    {
        return [
            'today' => 'Today',
            'week'  => 'This Week',
            'month' => 'This Month',
            'year'  => 'This Year',
            'all'   => 'All Time',
        ][$this->trendingPeriod] ?? 'This Week';
    }

    public function getHeading(): string
    {
        return [
            'today' => 'Trending Today',
            'week'  => 'Trending This Week',
            'month' => 'Trending This Month',
            'year'  => 'Trending This Year',
            'all'   => 'Trending All Time',
        ][$this->trendingPeriod] ?? 'Trending Videos';
    }

    public function getVideos(): Collection
    {
        $query = Video::query()
            ->with('user:id,username,avatar')
            ->public()
            ->approved()
            ->processed()
            ->orderByDesc('views_count')
            ->limit(8);

        match ($this->trendingPeriod) {
            'today' => $query->where('published_at', '>=', now()->startOfDay()),
            'week'  => $query->where('published_at', '>=', now()->subWeek()),
            'month' => $query->where('published_at', '>=', now()->subMonth()),
            'year'  => $query->where('published_at', '>=', now()->subYear()),
            default => null,
        };

        return $query->get();
    }

    public function render(): View
    {
        return view(static::$view, [
            'videos'      => $this->getVideos(),
            'heading'     => $this->getHeading(),
            'periodLabel' => $this->getPeriodLabel(),
        ]);
    }
}

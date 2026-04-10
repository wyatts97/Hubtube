<?php

namespace App\Filament\Widgets;

use App\Models\Video;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;

class TrendingVideosTable extends BaseWidget
{
    protected static bool $isDiscovered = false;

    protected int|string|array $columnSpan = 1;

    protected static ?int $sort = 2;

    public string $trendingPeriod = 'week';

    protected function getTableHeading(): string
    {
        $labels = [
            'today' => 'Trending Today',
            'week'  => 'Trending This Week',
            'month' => 'Trending This Month',
            'year'  => 'Trending This Year',
            'all'   => 'Trending All Time',
        ];

        return $labels[$this->trendingPeriod] ?? 'Trending Videos';
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Video::query()
                    ->with('user')
                    ->public()
                    ->approved()
                    ->processed()
                    ->orderByDesc('views_count')
                    ->limit(10)
            )
            ->headerActions([
                Tables\Actions\Action::make('period')
                    ->label(match ($this->trendingPeriod) {
                        'today' => 'Today',
                        'week'  => 'This Week',
                        'month' => 'This Month',
                        'year'  => 'This Year',
                        'all'   => 'All Time',
                        default => 'This Week',
                    })
                    ->icon('heroicon-o-funnel')
                    ->color('gray')
                    ->size('sm')
                    ->action(function () {
                        $periods = ['today', 'week', 'month', 'year', 'all'];
                        $current = array_search($this->trendingPeriod, $periods);
                        $this->trendingPeriod = $periods[($current + 1) % count($periods)];
                    }),
            ])
            ->modifyQueryUsing(function (Builder $query) {
                return match ($this->trendingPeriod) {
                    'today' => $query->where('published_at', '>=', now()->startOfDay()),
                    'week'  => $query->where('published_at', '>=', now()->subWeek()),
                    'month' => $query->where('published_at', '>=', now()->subMonth()),
                    'year'  => $query->where('published_at', '>=', now()->subYear()),
                    default => $query,
                };
            })
            ->columns([
                Tables\Columns\ImageColumn::make('thumbnail_preview')
                    ->label('')
                    ->getStateUsing(fn (Video $record): ?string => $record->thumbnail_url)
                    ->height(32)
                    ->width(56)
                    ->extraImgAttributes(['class' => 'rounded object-cover']),

                Tables\Columns\TextColumn::make('title')
                    ->wrap()
                    ->weight('bold')
                    ->url(fn (Video $record) => route('filament.admin.resources.videos.edit', $record)),

                Tables\Columns\TextColumn::make('user.username')
                    ->label('By')
                    ->color('gray')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('views_count')
                    ->label('Views')
                    ->numeric()
                    ->sortable(),

                Tables\Columns\TextColumn::make('likes_count')
                    ->label('Likes')
                    ->numeric()
                    ->toggleable(),
            ])
            ->paginated(false)
            ->defaultSort('views_count', 'desc');
    }
}

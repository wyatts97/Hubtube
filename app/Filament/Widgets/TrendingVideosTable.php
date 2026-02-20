<?php

namespace App\Filament\Widgets;

use App\Models\Video;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class TrendingVideosTable extends BaseWidget
{
    protected static bool $isDiscovered = false;

    protected static ?string $heading = 'Trending Videos';

    protected int|string|array $columnSpan = 1;

    protected static ?int $sort = 2;

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
            ->columns([
                Tables\Columns\ImageColumn::make('thumbnail_preview')
                    ->label('')
                    ->getStateUsing(fn (Video $record): ?string => $record->thumbnail_url)
                    ->height(32)
                    ->width(56)
                    ->extraImgAttributes(['class' => 'rounded object-cover']),

                Tables\Columns\TextColumn::make('title')
                    ->limit(30)
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

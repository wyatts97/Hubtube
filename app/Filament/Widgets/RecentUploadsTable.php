<?php

namespace App\Filament\Widgets;

use App\Models\Video;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class RecentUploadsTable extends BaseWidget
{
    protected static bool $isDiscovered = false;

    protected static ?string $heading = 'Recent Uploads';

    protected int|string|array $columnSpan = 1;

    protected static ?int $sort = 3;

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Video::query()
                    ->with('user')
                    ->latest()
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

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (string $state, Video $record): string => match (true) {
                        $state === 'processed' && $record->is_approved => 'Published',
                        $state === 'processed' && !$record->is_approved => 'Needs Moderation',
                        default => ucfirst($state),
                    })
                    ->color(fn (string $state, Video $record): string => match (true) {
                        $state === 'processed' && $record->is_approved => 'success',
                        $state === 'processed' && !$record->is_approved => 'warning',
                        $state === 'pending' => 'gray',
                        $state === 'processing' => 'info',
                        $state === 'failed' => 'danger',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('views_count')
                    ->label('Views')
                    ->numeric()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Uploaded')
                    ->since()
                    ->sortable(),
            ])
            ->paginated(false)
            ->defaultSort('created_at', 'desc');
    }
}

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
                Tables\Columns\TextColumn::make('title')
                    ->limit(40)
                    ->weight('bold')
                    ->url(fn (Video $record) => route('filament.admin.resources.videos.edit', $record)),

                Tables\Columns\TextColumn::make('user.username')
                    ->label('By')
                    ->color('gray')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'processed' => 'success',
                        'processing' => 'info',
                        'pending' => 'warning',
                        'failed' => 'danger',
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

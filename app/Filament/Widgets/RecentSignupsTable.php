<?php

namespace App\Filament\Widgets;

use App\Models\User;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class RecentSignupsTable extends BaseWidget
{
    protected static bool $isDiscovered = false;

    protected static ?string $heading = 'Recent Signups';

    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = 4;

    public function table(Table $table): Table
    {
        return $table
            ->query(
                User::query()
                    ->latest()
                    ->limit(10)
            )
            ->columns([
                Tables\Columns\ImageColumn::make('avatar')
                    ->circular()
                    ->defaultImageUrl(fn (User $record) => 'https://ui-avatars.com/api/?name=' . urlencode($record->username) . '&background=random')
                    ->size(32),

                Tables\Columns\TextColumn::make('username')
                    ->weight('bold')
                    ->url(fn (User $record) => route('filament.admin.resources.users.edit', $record))
                    ->searchable(),

                Tables\Columns\TextColumn::make('email')
                    ->color('gray')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Joined')
                    ->since()
                    ->sortable(),
            ])
            ->paginated(false)
            ->defaultSort('created_at', 'desc');
    }
}

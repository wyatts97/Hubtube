<?php

namespace App\Filament\Widgets;

use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use App\Models\User;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use SecretNinjas\FilamentMasonry\Concerns\HasMasonryLayout;
use SecretNinjas\FilamentMasonry\Enums\WidgetSize;

class RecentSignupsTable extends BaseWidget
{
    use HasMasonryLayout;

    protected static bool $isDiscovered = false;

    protected static ?string $heading = 'Recent Signups';

    protected static WidgetSize $size = WidgetSize::FullWidth;
    protected static int $order = 40;

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
                ImageColumn::make('avatar')
                    ->circular()
                    ->defaultImageUrl(fn (User $record) => 'https://ui-avatars.com/api/?name=' . urlencode($record->username) . '&background=random')
                    ->size(32),

                TextColumn::make('username')
                    ->weight('bold')
                    ->url(fn (User $record) => route('filament.admin.resources.users.edit', $record))
                    ->searchable(),

                TextColumn::make('email')
                    ->color('gray')
                    ->toggleable(),

                TextColumn::make('created_at')
                    ->label('Joined')
                    ->since()
                    ->sortable(),
            ])
            ->paginated(false)
            ->defaultSort('created_at', 'desc');
    }
}

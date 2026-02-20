<?php

namespace App\Filament\Resources;

use App\Filament\Resources\GalleryResource\Pages;
use App\Models\Gallery;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;

use App\Filament\Concerns\HasCustomizableNavigation;

class GalleryResource extends Resource
{
    use HasCustomizableNavigation;
    protected static ?string $model = Gallery::class;
    protected static ?string $navigationIcon = 'heroicon-o-rectangle-group';
    protected static ?string $navigationGroup = 'Content';
    protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Gallery Details')
                    ->schema([
                        Forms\Components\TextInput::make('title')
                            ->required()
                            ->maxLength(200)
                            ->columnSpanFull(),
                        Forms\Components\TextInput::make('slug')
                            ->disabled()
                            ->hiddenOn('create'),
                        Forms\Components\Textarea::make('description')
                            ->rows(3)
                            ->columnSpanFull(),
                        Forms\Components\Select::make('user_id')
                            ->label('Owner')
                            ->relationship('user', 'username')
                            ->required()
                            ->searchable()
                            ->preload(),
                        Forms\Components\Select::make('privacy')
                            ->options([
                                'public' => 'Public',
                                'private' => 'Private',
                                'unlisted' => 'Unlisted',
                            ])
                            ->default('public')
                            ->required(),
                        Forms\Components\Select::make('sort_order')
                            ->options([
                                'manual' => 'Manual',
                                'newest' => 'Newest First',
                                'oldest' => 'Oldest First',
                            ])
                            ->default('newest'),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\ImageColumn::make('cover_display')
                    ->label('Cover')
                    ->getStateUsing(fn (Gallery $record): ?string => $record->cover_url)
                    ->height(50)
                    ->width(50)
                    ->extraImgAttributes(['class' => 'rounded object-cover']),

                Tables\Columns\TextColumn::make('title')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->limit(50),

                Tables\Columns\TextColumn::make('user.username')
                    ->label('Owner')
                    ->searchable()
                    ->sortable()
                    ->icon('heroicon-m-user')
                    ->size('sm'),

                Tables\Columns\TextColumn::make('images_count')
                    ->label('Images')
                    ->numeric()
                    ->sortable()
                    ->icon('heroicon-m-photo')
                    ->iconColor('gray'),

                Tables\Columns\TextColumn::make('views_count')
                    ->label('Views')
                    ->numeric()
                    ->sortable()
                    ->icon('heroicon-m-eye')
                    ->iconColor('gray'),

                Tables\Columns\TextColumn::make('privacy')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'public' => 'success',
                        'unlisted' => 'warning',
                        'private' => 'danger',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->since()
                    ->sortable()
                    ->size('sm')
                    ->color('gray'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('privacy')
                    ->options([
                        'public' => 'Public',
                        'private' => 'Private',
                        'unlisted' => 'Unlisted',
                    ]),

                Tables\Filters\SelectFilter::make('user_id')
                    ->label('Owner')
                    ->relationship('user', 'username')
                    ->searchable()
                    ->preload(),
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\EditAction::make(),

                    Tables\Actions\Action::make('view_frontend')
                        ->icon('heroicon-o-eye')
                        ->color('gray')
                        ->url(fn (Gallery $record): string => "/gallery/{$record->slug}")
                        ->openUrlInNewTab(),

                    Tables\Actions\DeleteAction::make(),
                ]),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->striped()
            ->paginated([10, 25, 50]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListGalleries::route('/'),
            'edit' => Pages\EditGallery::route('/{record}/edit'),
        ];
    }
}

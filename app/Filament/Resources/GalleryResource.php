<?php

namespace App\Filament\Resources;

use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Select;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use App\Filament\Resources\GalleryResource\Pages\ListGalleries;
use App\Filament\Resources\GalleryResource\Pages\EditGallery;
use App\Filament\Resources\GalleryResource\Pages;
use App\Models\Gallery;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;


class GalleryResource extends Resource
{
    protected static ?string $model = Gallery::class;
    protected static string | \BackedEnum | null $navigationIcon = 'phosphor-squares-four';
    protected static string | \UnitEnum | null $navigationGroup = 'Content';
    protected static ?int $navigationSort = 3;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Gallery Details')
                    ->schema([
                        TextInput::make('title')
                            ->required()
                            ->maxLength(200)
                            ->columnSpanFull(),
                        TextInput::make('slug')
                            ->disabled()
                            ->hiddenOn('create'),
                        Textarea::make('description')
                            ->rows(3)
                            ->columnSpanFull(),
                        Select::make('user_id')
                            ->label('Owner')
                            ->relationship('user', 'username')
                            ->required()
                            ->searchable()
                            ->preload(),
                        Select::make('privacy')
                            ->options([
                                'public' => 'Public',
                                'private' => 'Private',
                                'unlisted' => 'Unlisted',
                            ])
                            ->default('public')
                            ->required(),
                        Select::make('sort_order')
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
                ImageColumn::make('cover_display')
                    ->label('Cover')
                    ->getStateUsing(fn (Gallery $record): ?string => $record->cover_url)
                    ->height(50)
                    ->width(50)
                    ->extraImgAttributes(['class' => 'rounded object-cover']),

                TextColumn::make('title')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->limit(50),

                TextColumn::make('user.username')
                    ->label('Owner')
                    ->searchable()
                    ->sortable()
                    ->icon('phosphor-user')
                    ->size('sm'),

                TextColumn::make('images_count')
                    ->label('Images')
                    ->numeric()
                    ->sortable()
                    ->icon('phosphor-image')
                    ->iconColor('gray'),

                TextColumn::make('views_count')
                    ->label('Views')
                    ->numeric()
                    ->sortable()
                    ->icon('phosphor-eye')
                    ->iconColor('gray'),

                TextColumn::make('privacy')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'public' => 'success',
                        'unlisted' => 'warning',
                        'private' => 'danger',
                        default => 'gray',
                    }),

                TextColumn::make('created_at')
                    ->label('Created')
                    ->since()
                    ->sortable()
                    ->size('sm')
                    ->color('gray'),
            ])
            ->filters([
                SelectFilter::make('privacy')
                    ->options([
                        'public' => 'Public',
                        'private' => 'Private',
                        'unlisted' => 'Unlisted',
                    ]),

                SelectFilter::make('user_id')
                    ->label('Owner')
                    ->relationship('user', 'username')
                    ->searchable()
                    ->preload(),
            ])
            ->recordActions([
                ActionGroup::make([
                    EditAction::make(),

                    Action::make('view_frontend')
                        ->icon('phosphor-eye')
                        ->color('gray')
                        ->url(fn (Gallery $record): string => "/gallery/{$record->slug}")
                        ->openUrlInNewTab(),

                    DeleteAction::make(),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
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
            'index' => ListGalleries::route('/'),
            'edit' => EditGallery::route('/{record}/edit'),
        ];
    }
}

<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ImageResource\Pages;
use App\Models\Image;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

use App\Filament\Concerns\HasCustomizableNavigation;

class ImageResource extends Resource
{
    use HasCustomizableNavigation;
    protected static ?string $model = Image::class;
    protected static ?string $navigationIcon = 'heroicon-o-photo';
    protected static ?string $navigationGroup = 'Content';
    protected static ?int $navigationSort = 2;

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::where('is_approved', false)->count() ?: null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Image Details')
                    ->schema([
                        Forms\Components\TextInput::make('title')
                            ->maxLength(200)
                            ->columnSpanFull(),
                        Forms\Components\Textarea::make('description')
                            ->rows(3)
                            ->columnSpanFull(),
                        Forms\Components\Select::make('user_id')
                            ->label('Uploader')
                            ->relationship('user', 'username')
                            ->required()
                            ->searchable()
                            ->preload(),
                        Forms\Components\Select::make('category_id')
                            ->relationship('category', 'name')
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
                        Forms\Components\TagsInput::make('tags')
                            ->separator(',')
                            ->columnSpanFull(),
                    ])->columns(2),

                Forms\Components\Section::make('Moderation')
                    ->schema([
                        Forms\Components\Toggle::make('is_approved')
                            ->label('Approved')
                            ->helperText('Image is visible to the public'),
                    ]),

                Forms\Components\Section::make('Technical Info')
                    ->schema([
                        Forms\Components\TextInput::make('file_path')
                            ->disabled()
                            ->columnSpanFull(),
                        Forms\Components\TextInput::make('storage_disk')
                            ->disabled(),
                        Forms\Components\TextInput::make('mime_type')
                            ->disabled(),
                        Forms\Components\TextInput::make('width')
                            ->disabled()
                            ->suffix('px'),
                        Forms\Components\TextInput::make('height')
                            ->disabled()
                            ->suffix('px'),
                        Forms\Components\TextInput::make('file_size')
                            ->disabled()
                            ->formatStateUsing(fn ($state) => $state ? number_format($state / 1048576, 2) . ' MB' : 'â€”'),
                        Forms\Components\Toggle::make('is_animated')
                            ->disabled(),
                    ])->columns(3)
                    ->collapsed(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\ImageColumn::make('thumbnail_display')
                    ->label('Preview')
                    ->getStateUsing(fn (Image $record): ?string => $record->thumbnail_url)
                    ->height(50)
                    ->width(50)
                    ->extraImgAttributes(['class' => 'rounded object-cover']),

                Tables\Columns\TextColumn::make('title')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->limit(50)
                    ->placeholder('Untitled'),

                Tables\Columns\TextColumn::make('user.username')
                    ->label('Uploader')
                    ->searchable()
                    ->sortable()
                    ->icon('heroicon-m-user')
                    ->size('sm'),

                Tables\Columns\TextColumn::make('mime_type')
                    ->label('Type')
                    ->size('sm')
                    ->badge()
                    ->color('gray'),

                Tables\Columns\TextColumn::make('dimensions')
                    ->label('Size')
                    ->getStateUsing(fn (Image $record): string => "{$record->width}Ã—{$record->height}")
                    ->size('sm')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\IconColumn::make('is_approved')
                    ->boolean()
                    ->label('Approved')
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('danger'),

                Tables\Columns\IconColumn::make('is_animated')
                    ->boolean()
                    ->label('Animated')
                    ->trueIcon('heroicon-o-gif')
                    ->falseIcon('heroicon-o-minus')
                    ->trueColor('info')
                    ->falseColor('gray')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('views_count')
                    ->label('Views')
                    ->numeric()
                    ->sortable()
                    ->icon('heroicon-m-eye')
                    ->iconColor('gray'),

                Tables\Columns\TextColumn::make('file_size')
                    ->label('File Size')
                    ->formatStateUsing(fn ($state) => $state ? number_format($state / 1048576, 1) . ' MB' : 'â€”')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('privacy')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'public' => 'success',
                        'unlisted' => 'warning',
                        'private' => 'danger',
                        default => 'gray',
                    })
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Uploaded')
                    ->since()
                    ->sortable()
                    ->size('sm')
                    ->color('gray'),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_approved')
                    ->label('Approved'),

                Tables\Filters\SelectFilter::make('privacy')
                    ->options([
                        'public' => 'Public',
                        'private' => 'Private',
                        'unlisted' => 'Unlisted',
                    ]),

                Tables\Filters\SelectFilter::make('user_id')
                    ->label('Uploader')
                    ->relationship('user', 'username')
                    ->searchable()
                    ->preload(),

                Tables\Filters\TernaryFilter::make('is_animated')
                    ->label('Animated'),
            ], layout: Tables\Enums\FiltersLayout::AboveContentCollapsible)
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\EditAction::make(),

                    Tables\Actions\Action::make('approve')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->requiresConfirmation()
                        ->action(fn (Image $record) => $record->update(['is_approved' => true]))
                        ->visible(fn (Image $record) => !$record->is_approved),

                    Tables\Actions\Action::make('unapprove')
                        ->icon('heroicon-o-x-circle')
                        ->color('warning')
                        ->requiresConfirmation()
                        ->action(fn (Image $record) => $record->update(['is_approved' => false]))
                        ->visible(fn (Image $record) => $record->is_approved),

                    Tables\Actions\Action::make('view_frontend')
                        ->icon('heroicon-o-eye')
                        ->color('gray')
                        ->url(fn (Image $record): string => "/image/{$record->uuid}")
                        ->openUrlInNewTab()
                        ->visible(fn (Image $record) => $record->is_approved),

                    Tables\Actions\DeleteAction::make(),
                ]),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('approve')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->requiresConfirmation()
                        ->action(fn (Collection $records) => $records->each(fn (Image $i) => $i->update(['is_approved' => true])))
                        ->deselectRecordsAfterCompletion(),

                    Tables\Actions\BulkAction::make('unapprove')
                        ->icon('heroicon-o-x-circle')
                        ->color('warning')
                        ->requiresConfirmation()
                        ->action(fn (Collection $records) => $records->each(fn (Image $i) => $i->update(['is_approved' => false])))
                        ->deselectRecordsAfterCompletion(),

                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->striped()
            ->paginated([10, 25, 50, 100]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListImages::route('/'),
            'edit' => Pages\EditImage::route('/{record}/edit'),
        ];
    }
}

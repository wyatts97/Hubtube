<?php

namespace App\Filament\Resources;

use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Toggle;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;
use PtPlugins\FilamentCollapsibleColumnGroup\CollapsibleColumnGroup;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\BulkAction;
use Filament\Actions\DeleteBulkAction;
use App\Filament\Resources\ImageResource\Pages\ListImages;
use App\Filament\Resources\ImageResource\Pages\EditImage;
use App\Filament\Resources\ImageResource\Pages;
use App\Models\Image;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;


class ImageResource extends Resource
{
    protected static ?string $model = Image::class;
    protected static string | \BackedEnum | null $navigationIcon = 'phosphor-image';
    protected static string | \UnitEnum | null $navigationGroup = 'Content';
    protected static ?int $navigationSort = 3;

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::where('is_approved', false)->count() ?: null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Image Details')
                    ->schema([
                        TextInput::make('title')
                            ->maxLength(200)
                            ->columnSpanFull(),
                        Textarea::make('description')
                            ->rows(3)
                            ->columnSpanFull(),
                        Select::make('user_id')
                            ->label('Uploader')
                            ->relationship('user', 'username')
                            ->required()
                            ->searchable()
                            ->preload(),
                        Select::make('category_id')
                            ->relationship('category', 'name')
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
                        TagsInput::make('tags')
                            ->separator(',')
                            ->columnSpanFull(),
                    ])->columns(2),

                Section::make('Moderation')
                    ->schema([
                        Toggle::make('is_approved')
                            ->label('Approved')
                            ->helperText('Image is visible to the public'),
                    ]),

                Section::make('Technical Info')
                    ->schema([
                        TextInput::make('file_path')
                            ->disabled()
                            ->columnSpanFull(),
                        TextInput::make('storage_disk')
                            ->disabled(),
                        TextInput::make('mime_type')
                            ->disabled(),
                        TextInput::make('width')
                            ->disabled()
                            ->suffix('px'),
                        TextInput::make('height')
                            ->disabled()
                            ->suffix('px'),
                        TextInput::make('file_size')
                            ->disabled()
                            ->formatStateUsing(fn ($state) => $state ? number_format($state / 1048576, 2) . ' MB' : '—'),
                        Toggle::make('is_animated')
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
                CollapsibleColumnGroup::make('Image Info')
                    ->collapsible()
                    ->columns([
                        ImageColumn::make('thumbnail_display')
                            ->label('Preview')
                            ->getStateUsing(fn (Image $record): ?string => $record->thumbnail_url)
                            ->height(50)
                            ->width(50)
                            ->extraImgAttributes(['class' => 'rounded object-cover']),
                        TextColumn::make('title')
                            ->searchable()
                            ->sortable()
                            ->weight('bold')
                            ->limit(50)
                            ->placeholder('Untitled'),
                        TextColumn::make('user.username')
                            ->label('Uploader')
                            ->searchable()
                            ->sortable()
                            ->icon('phosphor-user')
                            ->size('sm'),
                    ]),

                CollapsibleColumnGroup::make('Technical')
                    ->collapsible()
                    ->columns([
                        TextColumn::make('mime_type')
                            ->label('Type')
                            ->size('sm')
                            ->badge()
                            ->color('gray'),
                    ]),

                TextColumn::make('dimensions')
                    ->label('Size')
                    ->getStateUsing(fn (Image $record): string => "{$record->width}×{$record->height}")
                    ->size('sm')
                    ->toggleable(isToggledHiddenByDefault: true),

                CollapsibleColumnGroup::make('Status')
                    ->collapsible()
                    ->columns([
                        IconColumn::make('is_approved')
                            ->boolean()
                            ->label('Approved')
                            ->trueIcon('phosphor-check-circle')
                            ->falseIcon('phosphor-x-circle')
                            ->trueColor('success')
                            ->falseColor('danger'),
                    ]),

                IconColumn::make('is_animated')
                    ->boolean()
                    ->label('Animated')
                    ->trueIcon('phosphor-gif')
                    ->falseIcon('phosphor-minus')
                    ->trueColor('info')
                    ->falseColor('gray')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('privacy')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'public' => 'success',
                        'unlisted' => 'warning',
                        'private' => 'danger',
                        default => 'gray',
                    })
                    ->toggleable(isToggledHiddenByDefault: true),

                CollapsibleColumnGroup::make('Metrics')
                    ->collapsible()
                    ->columns([
                        TextColumn::make('views_count')
                            ->label('Views')
                            ->numeric()
                            ->sortable()
                            ->icon('phosphor-eye')
                            ->iconColor('gray'),
                    ]),

                TextColumn::make('file_size')
                    ->label('File Size')
                    ->formatStateUsing(fn ($state) => $state ? number_format($state / 1048576, 1) . ' MB' : '—')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                CollapsibleColumnGroup::make('Dates')
                    ->collapsible()
                    ->columns([
                        TextColumn::make('created_at')
                            ->label('Uploaded')
                            ->since()
                            ->sortable()
                            ->size('sm')
                            ->color('gray'),
                    ]),
            ])
            ->filters([
                TernaryFilter::make('is_approved')
                    ->label('Approved'),

                SelectFilter::make('privacy')
                    ->options([
                        'public' => 'Public',
                        'private' => 'Private',
                        'unlisted' => 'Unlisted',
                    ]),

                SelectFilter::make('user_id')
                    ->label('Uploader')
                    ->relationship('user', 'username')
                    ->searchable()
                    ->preload(),

                TernaryFilter::make('is_animated')
                    ->label('Animated'),
            ], layout: FiltersLayout::AboveContentCollapsible)
            ->recordActions([
                ActionGroup::make([
                    EditAction::make(),

                    Action::make('approve')
                        ->icon('phosphor-check-circle')
                        ->color('success')
                        ->requiresConfirmation()
                        ->action(fn (Image $record) => $record->update(['is_approved' => true]))
                        ->visible(fn (Image $record) => !$record->is_approved),

                    Action::make('unapprove')
                        ->icon('phosphor-x-circle')
                        ->color('warning')
                        ->requiresConfirmation()
                        ->action(fn (Image $record) => $record->update(['is_approved' => false]))
                        ->visible(fn (Image $record) => $record->is_approved),

                    Action::make('view_frontend')
                        ->icon('phosphor-eye')
                        ->color('gray')
                        ->url(fn (Image $record): string => "/image/{$record->uuid}")
                        ->openUrlInNewTab()
                        ->visible(fn (Image $record) => $record->is_approved),

                    DeleteAction::make(),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('approve')
                        ->icon('phosphor-check-circle')
                        ->color('success')
                        ->requiresConfirmation()
                        ->action(fn (Collection $records) => $records->each(fn (Image $i) => $i->update(['is_approved' => true])))
                        ->deselectRecordsAfterCompletion(),

                    BulkAction::make('unapprove')
                        ->icon('phosphor-x-circle')
                        ->color('warning')
                        ->requiresConfirmation()
                        ->action(fn (Collection $records) => $records->each(fn (Image $i) => $i->update(['is_approved' => false])))
                        ->deselectRecordsAfterCompletion(),

                    DeleteBulkAction::make(),
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
            'index' => ListImages::route('/'),
            'edit' => EditImage::route('/{record}/edit'),
        ];
    }
}

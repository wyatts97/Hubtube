<?php

namespace App\Filament\Resources;

use App\Filament\Resources\MenuItemResource\Pages;
use App\Models\MenuItem;
use App\Models\Category;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

use App\Filament\Concerns\HasCustomizableNavigation;

class MenuItemResource extends Resource
{
    use HasCustomizableNavigation;
    protected static ?string $model = MenuItem::class;
    protected static ?string $navigationIcon = 'heroicon-o-bars-3';
    protected static ?string $navigationLabel = 'Menu Builder';
    protected static ?string $navigationGroup = 'Appearance';
    protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Menu Item')
                    ->schema([
                        Forms\Components\TextInput::make('label')
                            ->required()
                            ->maxLength(100)
                            ->helperText('Display text for this menu item'),

                        Forms\Components\Select::make('type')
                            ->options([
                                'link' => 'Custom Link',
                                'category' => 'Category Page',
                                'tag' => 'Tag Page',
                                'dropdown' => 'Dropdown (parent only)',
                                'divider' => 'Divider',
                            ])
                            ->default('link')
                            ->required()
                            ->reactive()
                            ->helperText('Category and Tag types auto-generate their URL'),

                        Forms\Components\TextInput::make('url')
                            ->label(fn ($get) => match ($get('type')) {
                                'category' => 'Category Slug',
                                'tag' => 'Tag Name',
                                default => 'URL',
                            })
                            ->placeholder(fn ($get) => match ($get('type')) {
                                'category' => 'e.g. amateur (just the slug)',
                                'tag' => 'e.g. amateur (just the tag name)',
                                default => '/categories or https://example.com',
                            })
                            ->helperText(fn ($get) => match ($get('type')) {
                                'category' => 'Enter the category slug. URL will become /category/slug',
                                'tag' => 'Enter the tag name. URL will become /tag/name',
                                default => 'Relative or absolute URL',
                            })
                            ->visible(fn ($get) => !in_array($get('type'), ['dropdown', 'divider']))
                            ->dehydrateStateUsing(function ($state, $get) {
                                if (!$state) return null;
                                return match ($get('type')) {
                                    'category' => "/category/{$state}",
                                    'tag' => "/tag/{$state}",
                                    default => $state,
                                };
                            })
                            ->formatStateUsing(function ($state, $record) {
                                if (!$record || !$state) return $state;
                                if ($record->type === 'category' && str_starts_with($state, '/category/')) {
                                    return str_replace('/category/', '', $state);
                                }
                                if ($record->type === 'tag' && str_starts_with($state, '/tag/')) {
                                    return str_replace('/tag/', '', $state);
                                }
                                return $state;
                            }),
                    ])->columns(2),

                Forms\Components\Section::make('Appearance & Behavior')
                    ->schema([
                        Forms\Components\Select::make('target')
                            ->options([
                                '_self' => 'Same Window',
                                '_blank' => 'New Tab',
                            ])
                            ->default('_self'),

                        Forms\Components\TextInput::make('icon')
                            ->placeholder('e.g. tag, folder, star')
                            ->helperText('Lucide icon name (optional)'),

                        Forms\Components\Select::make('parent_id')
                            ->label('Parent Menu Item')
                            ->relationship('parent', 'label', fn ($query) => $query->topLevel()->orderBy('sort_order'))
                            ->searchable()
                            ->nullable()
                            ->helperText('Leave empty for top-level item'),

                        Forms\Components\Select::make('location')
                            ->options([
                                'header' => 'Desktop Header Only',
                                'mobile' => 'Mobile Sidebar Only',
                                'both' => 'Both Desktop & Mobile',
                            ])
                            ->default('both')
                            ->required(),

                        Forms\Components\Toggle::make('is_mega')
                            ->label('Mega Menu')
                            ->helperText('Display children in a multi-column dropdown (top-level only)')
                            ->visible(fn ($get) => !$get('parent_id')),

                        Forms\Components\TextInput::make('mega_columns')
                            ->label('Mega Menu Columns')
                            ->numeric()
                            ->default(4)
                            ->minValue(2)
                            ->maxValue(6)
                            ->visible(fn ($get) => $get('is_mega') && !$get('parent_id')),

                        Forms\Components\TextInput::make('sort_order')
                            ->numeric()
                            ->default(0),

                        Forms\Components\Toggle::make('is_active')
                            ->default(true),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('parent'))
            ->columns([
                Tables\Columns\TextColumn::make('label')
                    ->searchable()
                    ->sortable()
                    ->description(fn (MenuItem $record) => $record->parent ? "↳ Child of: {$record->parent->label}" : null),
                Tables\Columns\TextColumn::make('type')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'link' => 'primary',
                        'category' => 'success',
                        'tag' => 'warning',
                        'dropdown' => 'info',
                        'divider' => 'gray',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('url')
                    ->limit(40),
                Tables\Columns\TextColumn::make('location')
                    ->badge(),
                Tables\Columns\IconColumn::make('is_mega')
                    ->label('Mega')
                    ->boolean(),
                Tables\Columns\IconColumn::make('is_active')
                    ->boolean(),
                Tables\Columns\TextColumn::make('sort_order')
                    ->sortable(),
            ])
            ->defaultSort('sort_order')
            ->filters([
                Tables\Filters\SelectFilter::make('location')
                    ->options([
                        'header' => 'Header',
                        'mobile' => 'Mobile',
                        'both' => 'Both',
                    ]),
                Tables\Filters\TernaryFilter::make('is_active'),
                Tables\Filters\Filter::make('top_level')
                    ->label('Top Level Only')
                    ->query(fn ($query) => $query->whereNull('parent_id')),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->reorderable('sort_order');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMenuItems::route('/'),
            'create' => Pages\CreateMenuItem::route('/create'),
            'edit' => Pages\EditMenuItem::route('/{record}/edit'),
        ];
    }
}

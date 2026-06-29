<?php

namespace App\Filament\Resources;

use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;
use PtPlugins\FilamentCollapsibleColumnGroup\CollapsibleColumnGroup;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\Filter;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use App\Filament\Resources\MenuItemResource\Pages\ListMenuItems;
use App\Filament\Resources\MenuItemResource\Pages\CreateMenuItem;
use App\Filament\Resources\MenuItemResource\Pages\EditMenuItem;
use App\Filament\Resources\MenuItemResource\Pages;
use App\Models\MenuItem;
use App\Models\Category;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;


class MenuItemResource extends Resource
{
    protected static ?string $model = MenuItem::class;
    protected static string | \BackedEnum | null $navigationIcon = 'phosphor-list';
    protected static ?string $navigationLabel = 'Menu Builder';
    protected static string | \UnitEnum | null $navigationGroup = 'Appearance';
    protected static ?int $navigationSort = 3;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Menu Item')
                    ->schema([
                        TextInput::make('label')
                            ->required()
                            ->maxLength(100)
                            ->helperText('Display text for this menu item'),

                        Select::make('type')
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

                        TextInput::make('url')
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

                Section::make('Appearance & Behavior')
                    ->schema([
                        Select::make('target')
                            ->options([
                                '_self' => 'Same Window',
                                '_blank' => 'New Tab',
                            ])
                            ->default('_self'),

                        TextInput::make('icon')
                            ->placeholder('e.g. tag, folder, star')
                            ->helperText('Lucide icon name (optional)'),

                        Select::make('parent_id')
                            ->label('Parent Menu Item')
                            ->relationship('parent', 'label', fn ($query) => $query->topLevel()->orderBy('sort_order'))
                            ->searchable()
                            ->nullable()
                            ->helperText('Leave empty for top-level item'),

                        Select::make('location')
                            ->options([
                                'header' => 'Desktop Header Only',
                                'mobile' => 'Mobile Sidebar Only',
                                'both' => 'Both Desktop & Mobile',
                            ])
                            ->default('both')
                            ->required(),

                        Toggle::make('is_mega')
                            ->label('Mega Menu')
                            ->helperText('Display children in a multi-column dropdown (top-level only)')
                            ->visible(fn ($get) => !$get('parent_id')),

                        TextInput::make('mega_columns')
                            ->label('Mega Menu Columns')
                            ->numeric()
                            ->default(4)
                            ->minValue(2)
                            ->maxValue(6)
                            ->visible(fn ($get) => $get('is_mega') && !$get('parent_id')),

                        TextInput::make('sort_order')
                            ->numeric()
                            ->default(0),

                        Toggle::make('is_active')
                            ->default(true),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('parent'))
            ->columns([
                CollapsibleColumnGroup::make('Menu Info')
                    ->collapsible()
                    ->columns([
                        TextColumn::make('label')
                            ->searchable()
                            ->sortable()
                            ->description(fn (MenuItem $record) => $record->parent ? "↳ Child of: {$record->parent->label}" : null),
                        TextColumn::make('type')
                            ->badge()
                            ->color(fn (string $state): string => match ($state) {
                                'link' => 'primary',
                                'category' => 'success',
                                'tag' => 'warning',
                                'dropdown' => 'info',
                                'divider' => 'gray',
                                default => 'gray',
                            }),
                        TextColumn::make('url')
                            ->limit(40),
                    ]),

                CollapsibleColumnGroup::make('Display')
                    ->collapsible()
                    ->columns([
                        TextColumn::make('location')
                            ->badge(),
                        IconColumn::make('is_mega')
                            ->label('Mega')
                            ->boolean(),
                        IconColumn::make('is_active')
                            ->boolean(),
                    ]),

                CollapsibleColumnGroup::make('Order')
                    ->collapsible()
                    ->columns([
                        TextColumn::make('sort_order')
                            ->sortable(),
                    ]),
            ])
            ->defaultSort('sort_order')
            ->filters([
                SelectFilter::make('location')
                    ->options([
                        'header' => 'Header',
                        'mobile' => 'Mobile',
                        'both' => 'Both',
                    ]),
                TernaryFilter::make('is_active'),
                Filter::make('top_level')
                    ->label('Top Level Only')
                    ->query(fn ($query) => $query->whereNull('parent_id')),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->reorderable('sort_order')
            ->striped();
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMenuItems::route('/'),
            'create' => CreateMenuItem::route('/create'),
            'edit' => EditMenuItem::route('/{record}/edit'),
        ];
    }
}

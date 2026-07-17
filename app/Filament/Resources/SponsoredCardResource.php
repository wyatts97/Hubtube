<?php

namespace App\Filament\Resources;

use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\FileUpload;
use Filament\Schemas\Components\Grid;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Toggle;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Leek\FilamentRightClick\Menu\ContextMenuItem;
use Leek\FilamentRightClick\Menu\ContextMenuSeparator;
use App\Filament\Resources\SponsoredCardResource\Pages\ListSponsoredCards;
use App\Filament\Resources\SponsoredCardResource\Pages\CreateSponsoredCard;
use App\Filament\Resources\SponsoredCardResource\Pages\EditSponsoredCard;
use App\Filament\Resources\SponsoredCardResource\Pages;
use App\Models\Category;
use App\Models\SponsoredCard;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class SponsoredCardResource extends Resource
{
    protected static ?string $model = SponsoredCard::class;
    protected static string | \BackedEnum | null $navigationIcon = 'phosphor-megaphone';
    protected static ?string $navigationLabel = 'Sponsored Cards';
    protected static string | \UnitEnum | null $navigationGroup = 'Monetization';
    protected static ?int $navigationSort = 5;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Creative')
                    ->schema([
                        TextInput::make('external_id')
                            ->label('External ID')
                            ->maxLength(100)
                            ->placeholder('Optional external reference ID'),
                        TextInput::make('title')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('e.g. Check out our new product!'),
                        TextInput::make('click_url')
                            ->label('Click-Through URL')
                            ->required()
                            ->url()
                            ->maxLength(2048)
                            ->placeholder('https://example.com/landing-page'),
                        FileUpload::make('thumbnail_url')
                            ->label('Thumbnail Image')
                            ->image()
                            ->disk('public')
                            ->directory('sponsored')
                            ->visibility('public')
                            ->helperText('Recommended: 640×360 (16:9). Can also use external URL.'),
                        TextInput::make('description')
                            ->maxLength(255)
                            ->placeholder('Optional short description shown below the title'),
                        TextInput::make('studio')
                            ->maxLength(255)
                            ->placeholder('Studio or brand name'),
                    ])->columns(2),

                Section::make('Pricing & Ribbon')
                    ->schema([
                        Grid::make(3)->schema([
                            TextInput::make('price')
                                ->label('Price')
                                ->numeric()
                                ->prefix('$')
                                ->step(0.01)
                                ->placeholder('9.99'),
                            TextInput::make('sale_price')
                                ->label('Sale Price')
                                ->numeric()
                                ->prefix('$')
                                ->step(0.01)
                                ->placeholder('7.99')
                                ->helperText('Leave empty if not on sale'),
                            TextInput::make('duration')
                                ->label('Duration (seconds)')
                                ->numeric()
                                ->placeholder('300'),
                        ]),
                        TextInput::make('ribbon_text')
                            ->label('Ribbon Text')
                            ->maxLength(50)
                            ->placeholder('e.g. "Product", "Video", "Clip"')
                            ->helperText('Text shown after "Featured" on the ribbon (e.g. "Featured Product")'),
                    ]),

                Section::make('Preview Images')
                    ->description('Multiple images that cycle on hover (like video card previews)')
                    ->schema([
                        Repeater::make('preview_images')
                            ->label('Preview Images')
                            ->simple(
                                TextInput::make('url')
                                    ->label('Image URL')
                                    ->url()
                                    ->placeholder('https://example.com/preview1.jpg')
                            )
                            ->addActionLabel('Add Preview Image')
                            ->reorderable()
                            ->collapsible()
                            ->defaultItems(0),
                    ])->collapsed(),

                Section::make('Targeting & Display')
                    ->schema([
                        CheckboxList::make('target_pages')
                            ->label('Show on Pages')
                            ->options([
                                'home' => 'Home',
                                'trending' => 'Trending',
                                'search' => 'Search Results',
                                'category' => 'Category Pages',
                                'browse' => 'Browse Videos',
                            ])
                            ->helperText('Leave empty to show on all pages')
                            ->columns(3),

                        Grid::make(3)->schema([
                            TextInput::make('frequency')
                                ->label('Frequency (1 per N videos)')
                                ->numeric()
                                ->default(8)
                                ->minValue(2)
                                ->maxValue(50)
                                ->helperText('Insert 1 sponsored card every N videos'),
                            TextInput::make('weight')
                                ->label('Weight / Priority')
                                ->numeric()
                                ->default(1)
                                ->minValue(1)
                                ->maxValue(100)
                                ->helperText('Higher = more likely when multiple cards compete'),
                            Toggle::make('is_active')
                                ->label('Active')
                                ->default(true),
                        ]),

                        Grid::make(2)->schema([
                            CheckboxList::make('category_ids')
                                ->label('Target Categories')
                                ->options(fn () => Category::active()->orderBy('name')->pluck('name', 'id')->toArray())
                                ->helperText('Leave empty for all categories')
                                ->columns(2),
                            CheckboxList::make('target_roles')
                                ->label('Target User Roles')
                                ->options([
                                    'guest' => 'Guests (not logged in)',
                                    'default' => 'Default Users (free)',
                                    'pro' => 'Pro Users',
                                    'admin' => 'Admins',
                                ])
                                ->helperText('Leave empty for all users'),
                        ]),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('thumb_display')
                    ->label('Thumb')
                    ->getStateUsing(function ($record): ?string {
                        $thumb = $record->thumbnail_url;
                        if (!$thumb) return null;
                        // External URL — Filament uses it directly (bypasses disk resolution)
                        if (str_starts_with($thumb, 'http://') || str_starts_with($thumb, 'https://')) {
                            return $thumb;
                        }
                        // Strip any accidental /storage/ prefix so Filament resolves via disk correctly
                        if (str_starts_with($thumb, '/storage/')) {
                            return substr($thumb, 9);
                        }
                        return $thumb;
                    })
                    ->disk('public')
                    ->square()
                    ->size(60)
                    ->defaultImageUrl(url('/images/placeholder.jpg')),
                TextColumn::make('title')
                    ->searchable()
                    ->weight('bold')
                    ->limit(40),
                TextColumn::make('click_url')
                    ->label('URL')
                    ->limit(30)
                    ->color('gray')
                    ->copyable(),

                TextColumn::make('target_pages')
                    ->label('Pages')
                    ->formatStateUsing(function ($state): string {
                        if (empty($state) || !is_array($state)) return 'All';
                        return implode(', ', array_map('ucfirst', $state));
                    })
                    ->badge()
                    ->color('gray'),
                TextColumn::make('frequency')
                    ->label('Every N')
                    ->alignCenter(),
                TextColumn::make('weight')
                    ->alignCenter(),

                TextColumn::make('clicks_count')
                    ->label('Clicks')
                    ->numeric()
                    ->sortable()
                    ->alignCenter(),

                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),
                TextColumn::make('price')
                    ->money('USD')
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('ribbon_text')
                    ->label('Ribbon')
                    ->badge()
                    ->color('danger')
                    ->formatStateUsing(fn ($state) => $state ? "Featured {$state}" : null)
                    ->toggleable(),

                TextColumn::make('created_at')
                    ->dateTime('M j, Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->contextMenuActions([
                ContextMenuItem::for(EditAction::make('ctxEdit'))
                    ->label('Edit')
                    ->icon('phosphor-pencil-simple'),
                ContextMenuSeparator::make(),
                ContextMenuItem::for(DeleteAction::make('ctxDelete'))
                    ->label('Delete')
                    ->icon('phosphor-trash')
                    ->color('danger'),
            ])
            ->toolbarActions([
                DeleteBulkAction::make(),
            ])
            ->emptyStateHeading('No sponsored cards')
            ->emptyStateDescription('Create native in-feed ads that look like video cards with a "Sponsored" badge.')
            ->emptyStateIcon('phosphor-megaphone')
            ->striped();
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSponsoredCards::route('/'),
            'create' => CreateSponsoredCard::route('/create'),
            'edit' => EditSponsoredCard::route('/{record}/edit'),
        ];
    }
}
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
use Illuminate\Database\Eloquent\Builder;

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

                Section::make('Pricing')
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
                ImageColumn::make('thumbnail_url')
                    ->label('Thumb')
                    ->disk('public')
                    ->square()
                    ->size(60)
                    ->defaultImageUrl(url('/assets/placeholder.svg')),
                TextColumn::make('title')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->limit(40)
                    ->toggleable(),
                TextColumn::make('click_url')
                    ->label('URL')
                    ->limit(30)
                    ->color('gray')
                    ->copyable()
                    ->toggleable(),

                TextColumn::make('target_pages')
                    ->label('Pages')
                    ->formatStateUsing(function ($state): string {
                        if (empty($state) || !is_array($state)) return 'All';
                        return implode(', ', array_map('ucfirst', $state));
                    })
                    ->badge()
                    ->color('gray')
                    ->toggleable(),
                TextColumn::make('frequency')
                    ->label('Every N')
                    ->alignCenter()
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('weight')
                    ->alignCenter()
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('clicks_count')
                    ->label('Clicks')
                    ->numeric()
                    ->sortable()
                    ->alignCenter()
                    ->toggleable(),

                TextColumn::make('impressions_count')
                    ->label('Impr.')
                    ->numeric()
                    ->sortable()
                    ->alignCenter()
                    ->toggleable(),

                TextColumn::make('ctr')
                    ->label('CTR')
                    ->state(fn ($record) => $record->impressions_count > 0
                        ? round(($record->clicks_count / $record->impressions_count) * 100, 1) . '%'
                        : '—')
                    ->alignCenter()
                    ->toggleable(),

                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean()
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('price')
                    ->money('USD')
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('created_at')
                    ->dateTime('M j, Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort(fn (Builder $query, string $direction) => $query->orderBy('created_at', $direction)->orderBy('id', $direction), 'desc')
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                DeleteBulkAction::make(),
            ])
            ->emptyStateHeading('No sponsored cards')
            ->emptyStateDescription('Create native in-feed ads that look like video cards with a "Sponsored" badge.')
            ->emptyStateIcon('phosphor-megaphone')
            ->striped()
            ->paginated([10, 25, 50, 100]);
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
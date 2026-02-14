<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SponsoredCardResource\Pages;
use App\Models\Category;
use App\Models\SponsoredCard;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class SponsoredCardResource extends Resource
{
    protected static ?string $model = SponsoredCard::class;
    protected static ?string $navigationIcon = 'heroicon-o-megaphone';
    protected static ?string $navigationLabel = 'Sponsored Cards';
    protected static ?string $navigationGroup = 'Appearance';
    protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Creative')
                    ->schema([
                        Forms\Components\TextInput::make('title')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('e.g. Check out our new product!'),
                        Forms\Components\TextInput::make('click_url')
                            ->label('Click-Through URL')
                            ->required()
                            ->url()
                            ->maxLength(2048)
                            ->placeholder('https://example.com/landing-page'),
                        Forms\Components\FileUpload::make('thumbnail_url')
                            ->label('Thumbnail Image')
                            ->image()
                            ->required()
                            ->disk('public')
                            ->directory('sponsored')
                            ->visibility('public')
                            ->helperText('Recommended: 640×360 (16:9 aspect ratio) to match video cards'),
                        Forms\Components\TextInput::make('description')
                            ->maxLength(255)
                            ->placeholder('Optional short description shown below the title'),
                    ])->columns(2),

                Forms\Components\Section::make('Targeting & Display')
                    ->schema([
                        Forms\Components\CheckboxList::make('target_pages')
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

                        Forms\Components\Grid::make(3)->schema([
                            Forms\Components\TextInput::make('frequency')
                                ->label('Frequency (1 per N videos)')
                                ->numeric()
                                ->default(8)
                                ->minValue(2)
                                ->maxValue(50)
                                ->helperText('Insert 1 sponsored card every N videos'),
                            Forms\Components\TextInput::make('weight')
                                ->label('Weight / Priority')
                                ->numeric()
                                ->default(1)
                                ->minValue(1)
                                ->maxValue(100)
                                ->helperText('Higher = more likely when multiple cards compete'),
                            Forms\Components\Toggle::make('is_active')
                                ->label('Active')
                                ->default(true),
                        ]),

                        Forms\Components\Grid::make(2)->schema([
                            Forms\Components\CheckboxList::make('category_ids')
                                ->label('Target Categories')
                                ->options(fn () => Category::active()->orderBy('name')->pluck('name', 'id')->toArray())
                                ->helperText('Leave empty for all categories')
                                ->columns(2),
                            Forms\Components\CheckboxList::make('target_roles')
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
                Tables\Columns\ImageColumn::make('thumbnail_url')
                    ->label('Thumb')
                    ->getStateUsing(function ($record) {
                        $thumb = $record->thumbnail_url;
                        if (!$thumb) return null;
                        if (str_starts_with($thumb, 'http://') || str_starts_with($thumb, 'https://') || str_starts_with($thumb, '/')) {
                            return $thumb;
                        }
                        return '/storage/' . $thumb;
                    })
                    ->square()
                    ->size(60),
                Tables\Columns\TextColumn::make('title')
                    ->searchable()
                    ->weight('bold')
                    ->limit(40),
                Tables\Columns\TextColumn::make('click_url')
                    ->label('URL')
                    ->limit(30)
                    ->color('gray'),
                Tables\Columns\TextColumn::make('target_pages')
                    ->label('Pages')
                    ->formatStateUsing(function ($state) {
                        if (!$state || (is_array($state) && empty($state))) return 'All';
                        if (is_array($state)) return implode(', ', array_map('ucfirst', $state));
                        return 'All';
                    }),
                Tables\Columns\TextColumn::make('frequency')
                    ->label('Every N')
                    ->alignCenter(),
                Tables\Columns\TextColumn::make('weight')
                    ->alignCenter(),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime('M j, Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ])
            ->emptyStateHeading('No sponsored cards')
            ->emptyStateDescription('Create native in-feed ads that look like video cards with a "Sponsored" badge.')
            ->emptyStateIcon('heroicon-o-megaphone')
            ->striped();
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSponsoredCards::route('/'),
            'create' => Pages\CreateSponsoredCard::route('/create'),
            'edit' => Pages\EditSponsoredCard::route('/{record}/edit'),
        ];
    }
}

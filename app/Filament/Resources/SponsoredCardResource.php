<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\AuthorizesWithPermissions;
use App\Filament\Resources\SponsoredCardResource\Pages\CreateSponsoredCard;
use App\Filament\Resources\SponsoredCardResource\Pages\EditSponsoredCard;
use App\Filament\Resources\SponsoredCardResource\Pages\ListSponsoredCards;
use App\Models\Category;
use App\Models\SponsoredCard;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ReplicateAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ToggleButtons;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

class SponsoredCardResource extends Resource
{
    use AuthorizesWithPermissions;

    protected static ?string $model = SponsoredCard::class;

    protected static string|\BackedEnum|null $navigationIcon = 'phosphor-megaphone';

    protected static ?string $navigationLabel = 'Sponsored Cards';

    protected static string|\UnitEnum|null $navigationGroup = 'Monetization';

    protected static ?int $navigationSort = 5;

    protected static ?string $recordTitleAttribute = 'title';

    public static function getGloballySearchableAttributes(): array
    {
        return ['title', 'external_id'];
    }

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        return [
            'Type' => SponsoredCard::TYPES[$record->type] ?? $record->type,
            'Active' => $record->is_active ? 'Yes' : 'No',
        ];
    }

    public static function form(Schema $schema): Schema
    {
        $is = fn (string ...$types) => fn ($get) => in_array($get('type'), $types, true);

        return $schema
            ->components([
                Section::make('Ad')
                    ->schema([
                        ToggleButtons::make('type')
                            ->options(SponsoredCard::TYPES)
                            ->icons([
                                'image' => 'phosphor-image',
                                'video' => 'phosphor-film-strip',
                                'html' => 'phosphor-code',
                            ])
                            ->default('image')
                            ->inline()
                            ->required()
                            ->live()
                            ->columnSpanFull(),

                        TextInput::make('title')
                            ->label(fn ($get) => $get('type') === 'html' ? 'Name' : 'Title')
                            ->helperText(fn ($get) => $get('type') === 'html' ? 'Only shown in the admin.' : null)
                            ->required()
                            ->maxLength(255),
                        TextInput::make('click_url')
                            ->label('Link')
                            ->url()
                            ->maxLength(2048)
                            ->placeholder('https://')
                            ->required($is('image', 'video'))
                            ->visible($is('image', 'video')),

                        // Image
                        FileUpload::make('thumbnail_url')
                            ->label(fn ($get) => $get('type') === 'video' ? 'Poster image' : 'Image')
                            ->helperText(fn ($get) => $get('type') === 'video' ? 'Optional. Shown until the video starts.' : '16:9, e.g. 640×360.')
                            ->image()
                            ->disk('public')
                            ->directory('sponsored')
                            ->visibility('public')
                            ->required($is('image'))
                            ->visible($is('image', 'video')),

                        // Video
                        FileUpload::make('video_path')
                            ->label('Video file')
                            ->disk('public')
                            ->directory('sponsored/video')
                            ->visibility('public')
                            ->acceptedFileTypes(['video/mp4', 'video/webm'])
                            ->maxSize(51200)
                            ->helperText('MP4 or WebM, up to 50 MB. Plays muted.')
                            ->requiredWithout('video_url')
                            ->visible($is('video')),
                        TextInput::make('video_url')
                            ->label('Or video URL')
                            ->url()
                            ->maxLength(2048)
                            ->placeholder('https://example.com/ad.mp4')
                            ->requiredWithout('video_path')
                            ->visible($is('video')),

                        // HTML
                        Textarea::make('html_code')
                            ->label('Ad code')
                            ->helperText('Network code or an <iframe>. Scaled to fit the card.')
                            ->rows(6)
                            ->extraInputAttributes(['class' => 'font-mono text-xs'])
                            ->required($is('html'))
                            ->visible($is('html'))
                            ->columnSpanFull(),
                        Textarea::make('mobile_html_code')
                            ->label('Mobile ad code')
                            ->helperText('Optional. Used on phones.')
                            ->rows(4)
                            ->extraInputAttributes(['class' => 'font-mono text-xs'])
                            ->visible($is('html'))
                            ->columnSpanFull(),
                    ])->columns(2),

                Section::make('Details')
                    ->description('Optional text under the title.')
                    ->visible($is('image', 'video'))
                    ->collapsible()
                    ->collapsed()
                    ->schema([
                        TextInput::make('studio')
                            ->label('Studio / brand')
                            ->maxLength(255),
                        TextInput::make('description')
                            ->maxLength(255),
                        Grid::make(3)->schema([
                            TextInput::make('price')
                                ->numeric()
                                ->prefix('$')
                                ->step(0.01),
                            TextInput::make('sale_price')
                                ->numeric()
                                ->prefix('$')
                                ->step(0.01),
                            TextInput::make('duration')
                                ->label('Duration (seconds)')
                                ->numeric(),
                        ])->columnSpanFull(),
                        Repeater::make('preview_images')
                            ->label('Hover preview images')
                            ->simple(
                                TextInput::make('url')
                                    ->url()
                                    ->placeholder('https://example.com/preview.jpg')
                            )
                            ->addActionLabel('Add image')
                            ->reorderable()
                            ->defaultItems(0)
                            ->visible($is('image'))
                            ->columnSpanFull(),
                        TextInput::make('external_id')
                            ->label('External ID')
                            ->maxLength(100),
                    ])->columns(2),

                Section::make('Where it shows')
                    ->description('Leave a list empty to show everywhere.')
                    ->schema([
                        CheckboxList::make('target_pages')
                            ->label('Pages')
                            ->options(SponsoredCard::PAGES)
                            ->columns(3)
                            ->columnSpanFull(),
                        CheckboxList::make('category_ids')
                            ->label('Categories')
                            ->options(fn () => Category::active()->orderBy('name')->pluck('name', 'id')->toArray())
                            ->searchable()
                            ->columns(2),
                        CheckboxList::make('target_roles')
                            ->label('Viewers')
                            ->options([
                                'guest' => 'Guests',
                                'default' => 'Free users',
                                'pro' => 'Pro users',
                                'admin' => 'Admins',
                            ]),
                        TextInput::make('weight')
                            ->numeric()
                            ->default(1)
                            ->minValue(1)
                            ->maxValue(100)
                            ->helperText('Higher shows more often.'),
                        Toggle::make('is_active')
                            ->label('Active')
                            ->default(true)
                            ->inline(false),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('thumbnail_url')
                    ->label('')
                    ->disk('public')
                    ->imageWidth(80)
                    ->imageHeight(45)
                    ->defaultImageUrl(url('/assets/placeholder.svg'))
                    ->toggleable(),
                TextColumn::make('title')
                    ->searchable(['title', 'external_id'])
                    ->sortable()
                    ->weight('bold')
                    ->limit(40)
                    ->description(fn (SponsoredCard $record) => $record->type === 'html' ? null : $record->click_url),
                TextColumn::make('type')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => SponsoredCard::TYPES[$state] ?? $state)
                    ->color(fn (string $state) => match ($state) {
                        'video' => 'info',
                        'html' => 'warning',
                        default => 'gray',
                    })
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('target_pages')
                    ->label('Pages')
                    ->formatStateUsing(fn ($state) => collect((array) $state)
                        ->map(fn ($page) => SponsoredCard::PAGES[$page] ?? $page)
                        ->implode(', '))
                    ->placeholder('All')
                    ->limit(40)
                    ->color('gray')
                    ->toggleable(),
                TextColumn::make('weight')
                    ->alignCenter()
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('impressions_count')
                    ->label('Impr.')
                    ->numeric()
                    ->sortable()
                    ->alignCenter()
                    ->toggleable(),
                TextColumn::make('clicks_count')
                    ->label('Clicks')
                    ->numeric()
                    ->sortable()
                    ->alignCenter()
                    ->toggleable(),
                TextColumn::make('ctr')
                    ->label('CTR')
                    ->state(fn ($record) => $record->impressions_count > 0
                        ? round(($record->clicks_count / $record->impressions_count) * 100, 1).'%'
                        : '—')
                    ->alignCenter()
                    ->toggleable(),
                ToggleColumn::make('is_active')
                    ->label('Active')
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('created_at')
                    ->dateTime('M j, Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort(fn (Builder $query, string $direction) => $query->orderBy('created_at', $direction)->orderBy('id', $direction), 'desc')
            ->filters([
                SelectFilter::make('type')->options(SponsoredCard::TYPES),
                TernaryFilter::make('is_active')->label('Active'),
            ])
            ->recordActions([
                EditAction::make(),
                ReplicateAction::make()
                    ->label('Duplicate')
                    ->excludeAttributes(['impressions_count', 'clicks_count'])
                    ->beforeReplicaSaved(function (SponsoredCard $replica) {
                        $replica->title = $replica->title.' (copy)';
                        $replica->is_active = false;
                    }),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('activate')
                        ->label('Activate')
                        ->icon('phosphor-play')
                        ->color('success')
                        ->action(fn (Collection $records) => $records->each->update(['is_active' => true]))
                        ->deselectRecordsAfterCompletion(),
                    BulkAction::make('deactivate')
                        ->label('Deactivate')
                        ->icon('phosphor-pause')
                        ->color('gray')
                        ->action(fn (Collection $records) => $records->each->update(['is_active' => false]))
                        ->deselectRecordsAfterCompletion(),
                    DeleteBulkAction::make(),
                ]),
            ])
            ->striped()
            ->paginated([10, 25, 50, 100])
            ->emptyStateIcon('phosphor-megaphone')
            ->emptyStateHeading('No sponsored cards')
            ->emptyStateDescription('Ads shown between videos in the grid: an image, a video or ad code.')
            ->emptyStateActions([
                Action::make('create')
                    ->color('success')
                    ->label('New Sponsored Card')
                    ->icon('phosphor-plus')
                    ->url(static::getUrl('create'))
                    ->button(),
            ]);
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

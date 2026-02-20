<?php

namespace App\Filament\Resources;

use App\Filament\Resources\VideoAdResource\Pages;
use App\Models\Category;
use App\Models\VideoAd;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;

use App\Filament\Concerns\HasCustomizableNavigation;

class VideoAdResource extends Resource
{
    use HasCustomizableNavigation;
    protected static ?string $model = VideoAd::class;
    protected static ?string $navigationIcon = 'heroicon-o-film';
    protected static ?string $navigationLabel = 'Ad Creatives';
    protected static ?string $navigationGroup = 'Appearance';
    protected static ?int $navigationSort = 3;
    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Section::make('Creative Details')->schema([
                Grid::make(2)->schema([
                    TextInput::make('name')
                        ->required()
                        ->maxLength(255)
                        ->placeholder('e.g. Summer Sale Pre-Roll'),

                    Select::make('placement')
                        ->required()
                        ->options([
                            'pre_roll'  => 'Pre-Roll (before video)',
                            'mid_roll'  => 'Mid-Roll (during video)',
                            'post_roll' => 'Post-Roll (after video)',
                        ])
                        ->default('pre_roll'),
                ]),

                Grid::make(2)->schema([
                    Select::make('type')
                        ->required()
                        ->options([
                            'mp4'   => 'MP4 Video (local file or URL)',
                            'vast'  => 'VAST Tag URL',
                            'vpaid' => 'VPAID Tag URL',
                            'html'  => 'HTML Ad Script',
                        ])
                        ->default('mp4')
                        ->live()
                        ->helperText(fn ($get) => match ($get('type')) {
                            'vast', 'vpaid' => 'Served via Google IMA SDK. The ad network controls skip behavior.',
                            'mp4'           => 'Upload a file OR paste an external URL below.',
                            default         => null,
                        }),

                    TextInput::make('weight')
                        ->numeric()
                        ->default(1)
                        ->minValue(1)
                        ->maxValue(100)
                        ->helperText('Higher weight = shown more often when shuffle is on'),
                ]),
            ]),

            // â”€â”€ MP4: file upload + URL (either/or) â”€â”€
            Section::make('MP4 Video Source')
                ->description('Upload a video file OR paste an external URL. Uploaded file takes priority.')
                ->visible(fn ($get) => $get('type') === 'mp4')
                ->schema([
                    FileUpload::make('file_path')
                        ->label('Upload MP4 File')
                        ->disk('public')
                        ->directory('media/ads')
                        ->acceptedFileTypes(['video/mp4', 'video/webm', 'video/quicktime'])
                        ->maxSize(204800)
                        ->helperText('Max 200 MB. Stored in storage/app/public/media/ads/')
                        ->columnSpanFull()
                        ->afterStateUpdated(function ($state, callable $set) {
                            if ($state) {
                                $set('content', '');
                            }
                        }),

                    TextInput::make('content')
                        ->label('External MP4 URL (optional if file uploaded)')
                        ->placeholder('https://example.com/ads/my-ad.mp4')
                        ->url()
                        ->columnSpanFull()
                        ->helperText('Leave empty if you uploaded a file above.'),
                ]),

            // â”€â”€ VAST / VPAID: tag URL only â”€â”€
            Section::make('VAST / VPAID Tag')
                ->description('Paste your ad tag URL. Served via Google IMA SDK â€” the ad network controls skip, impression tracking, and click-through.')
                ->visible(fn ($get) => in_array($get('type'), ['vast', 'vpaid']))
                ->schema([
                    TextInput::make('content')
                        ->label(fn ($get) => $get('type') === 'vpaid' ? 'VPAID Tag URL' : 'VAST Tag URL')
                        ->required()
                        ->url()
                        ->placeholder('https://example.com/vast-tag.xml')
                        ->columnSpanFull(),

                    Placeholder::make('vast_note')
                        ->label('')
                        ->content(new HtmlString(
                            '<div style="background:#1e3a5f;border:1px solid #2563eb;border-radius:8px;padding:12px 16px;font-size:13px;color:#93c5fd;">'
                            . '<strong style="color:#60a5fa;">â„¹ VAST/VPAID Notes</strong><br>'
                            . 'â€¢ Skip delay settings in "Ad Settings â†’ Video Roll Ads" do <strong>not</strong> apply â€” the ad network controls skip.<br>'
                            . 'â€¢ Click-through URL is handled by the VAST tag itself.<br>'
                            . 'â€¢ Weight and category/role targeting still apply for ad selection.'
                            . '</div>'
                        ))
                        ->columnSpanFull(),
                ]),

            // â”€â”€ HTML: raw script â”€â”€
            Section::make('HTML Ad Script')
                ->visible(fn ($get) => $get('type') === 'html')
                ->schema([
                    Textarea::make('content')
                        ->label('HTML / JavaScript Ad Code')
                        ->required()
                        ->rows(6)
                        ->placeholder('<script>...</script>')
                        ->columnSpanFull(),
                ]),

            // â”€â”€ Click-through (MP4 / HTML only) â”€â”€
            Section::make('Click-Through')->schema([
                TextInput::make('click_url')
                    ->label('Click-Through URL')
                    ->url()
                    ->maxLength(2048)
                    ->placeholder('https://example.com/landing-page')
                    ->helperText('Optional. Clicking the ad opens this URL. Not used for VAST/VPAID (handled by ad network).')
                    ->columnSpanFull(),
            ])->visible(fn ($get) => in_array($get('type'), ['mp4', 'html'])),

            // â”€â”€ Targeting â”€â”€
            Section::make('Targeting')->schema([
                Grid::make(2)->schema([
                    CheckboxList::make('category_ids')
                        ->label('Target Categories')
                        ->options(fn () => Category::active()->orderBy('name')->pluck('name', 'id')->toArray())
                        ->helperText('Leave empty to show on all categories')
                        ->columns(2),

                    CheckboxList::make('target_roles')
                        ->label('Target User Roles')
                        ->options([
                            'guest'   => 'Guests (not logged in)',
                            'default' => 'Default Users (free)',
                            'pro'     => 'Pro Users',
                            'admin'   => 'Admins',
                        ])
                        ->helperText('Leave empty to show to all users'),
                ]),

                Toggle::make('is_active')
                    ->label('Active')
                    ->default(true),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->weight('bold')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('type')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => strtoupper($state))
                    ->color(fn (string $state): string => match ($state) {
                        'mp4'   => 'info',
                        'vast'  => 'purple',
                        'vpaid' => 'indigo',
                        'html'  => 'warning',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('placement')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => ucwords(str_replace('_', '-', $state)))
                    ->color(fn (string $state): string => match ($state) {
                        'pre_roll'  => 'success',
                        'mid_roll'  => 'warning',
                        'post_roll' => 'danger',
                        default     => 'gray',
                    }),

                Tables\Columns\TextColumn::make('content')
                    ->label('Source')
                    ->formatStateUsing(function (string $state, VideoAd $record): string {
                        if ($record->type === 'mp4' && $record->file_path) {
                            return 'ðŸ“ ' . basename($record->file_path);
                        }
                        if ($record->type === 'html') {
                            return 'ðŸ–¥ HTML script (' . strlen($state) . ' chars)';
                        }
                        return \Illuminate\Support\Str::limit($state, 50);
                    })
                    ->color('gray')
                    ->size('sm'),

                Tables\Columns\TextColumn::make('weight')
                    ->alignCenter()
                    ->sortable(),

                Tables\Columns\TextColumn::make('category_ids')
                    ->label('Targeting')
                    ->formatStateUsing(function ($state, VideoAd $record): string {
                        $cats  = ($record->category_ids && count($record->category_ids))
                            ? count($record->category_ids) . ' cats'
                            : 'All cats';
                        $roles = ($record->target_roles && count($record->target_roles))
                            ? implode(', ', $record->target_roles)
                            : 'All roles';
                        return "{$cats} Â· {$roles}";
                    })
                    ->color('gray')
                    ->size('sm'),

                Tables\Columns\ToggleColumn::make('is_active')
                    ->label('Active'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->options([
                        'mp4'   => 'MP4',
                        'vast'  => 'VAST',
                        'vpaid' => 'VPAID',
                        'html'  => 'HTML',
                    ]),
                Tables\Filters\SelectFilter::make('placement')
                    ->options([
                        'pre_roll'  => 'Pre-Roll',
                        'mid_roll'  => 'Mid-Roll',
                        'post_roll' => 'Post-Roll',
                    ]),
                Tables\Filters\TernaryFilter::make('is_active')->label('Active'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->after(function (VideoAd $record) {
                        if ($record->file_path && Storage::disk('public')->exists($record->file_path)) {
                            Storage::disk('public')->delete($record->file_path);
                        }
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->after(function ($records) {
                            foreach ($records as $record) {
                                if ($record->file_path && Storage::disk('public')->exists($record->file_path)) {
                                    Storage::disk('public')->delete($record->file_path);
                                }
                            }
                        }),
                ]),
            ])
            ->emptyStateHeading('No ad creatives yet')
            ->emptyStateDescription('Create your first video ad creative to start serving pre-roll, mid-roll, or post-roll ads.')
            ->emptyStateIcon('heroicon-o-film')
            ->striped();
    }

    public static function mutateFormDataBeforeSave(array $data): array
    {
        $data['category_ids'] = !empty($data['category_ids']) ? array_map('intval', $data['category_ids']) : null;
        $data['target_roles'] = !empty($data['target_roles']) ? $data['target_roles'] : null;
        return $data;
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListVideoAds::route('/'),
            'create' => Pages\CreateVideoAd::route('/create'),
            'edit'   => Pages\EditVideoAd::route('/{record}/edit'),
        ];
    }
}

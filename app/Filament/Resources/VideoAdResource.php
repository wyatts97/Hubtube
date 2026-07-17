<?php

namespace App\Filament\Resources;

use App\Filament\Resources\VideoAdResource\Pages\CreateVideoAd;
use App\Filament\Resources\VideoAdResource\Pages\EditVideoAd;
use App\Filament\Resources\VideoAdResource\Pages\ListVideoAds;
use App\Models\Category;
use App\Models\VideoAd;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

class VideoAdResource extends Resource
{
    protected static ?string $model = VideoAd::class;

    protected static string|\BackedEnum|null $navigationIcon = 'phosphor-film-strip';

    protected static ?string $navigationLabel = 'Ad Creatives';

    protected static string|\UnitEnum|null $navigationGroup = 'Appearance';

    protected static ?int $navigationSort = 3;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Creative Details')->schema([
                Grid::make(2)->schema([
                    TextInput::make('name')
                        ->required()
                        ->maxLength(255)
                        ->placeholder('e.g. Summer Sale Pre-Roll'),

                    Select::make('placement')
                        ->required()
                        ->options([
                            'pre_roll' => 'Pre-Roll (before video)',
                            'mid_roll' => 'Mid-Roll (during video)',
                            'post_roll' => 'Post-Roll (after video)',
                            'outstream' => 'Outstream (in video grid)',
                            'shorts' => 'Shorts (full-screen vertical)',
                        ])
                        ->default('pre_roll')
                        ->helperText(fn ($get) => $get('placement') === 'shorts'
                            ? 'Use 9:16 portrait creatives. VAST vertical video tags and HTML/banner ads are supported.'
                            : null),
                ]),

                Grid::make(2)->schema([
                    Select::make('type')
                        ->required()
                        ->options([
                            'mp4' => 'MP4 Video (local file or URL)',
                            'vast' => 'VAST Tag URL',
                            'vpaid' => 'VPAID Tag URL',
                            'html' => 'HTML Ad Script',
                        ])
                        ->default('mp4')
                        ->live()
                        ->helperText(fn ($get) => match ($get('type')) {
                            'vast', 'vpaid' => 'Served via Google IMA SDK. The ad network controls skip behavior.',
                            'mp4' => 'Upload a file OR paste an external URL below.',
                            default => null,
                        }),

                    TextInput::make('weight')
                        ->numeric()
                        ->default(1)
                        ->minValue(1)
                        ->maxValue(100)
                        ->helperText('Higher weight = shown more often when shuffle is on'),
                ]),
            ]),

            // — MP4: file upload + URL (either/or) —
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
                        ->columnSpanFull()
                        ->helperText('Leave empty if you uploaded a file above.'),
                ]),

            // — VAST / VPAID: tag URL only —
            Section::make('VAST / VPAID Tag')
                ->description('Paste your ad tag URL. Served via Google IMA SDK — the ad network controls skip, impression tracking, and click-through.')
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
                            .'<strong style="color:#60a5fa;">ℹ VAST/VPAID Notes</strong><br>'
                            .'• Skip delay settings in "Ad Settings → Video Roll Ads" do <strong>not</strong> apply — the ad network controls skip.<br>'
                            .'• Click-through URL is handled by the VAST tag itself.<br>'
                            .'• Weight and category/role targeting still apply for ad selection.'
                            .'</div>'
                        ))
                        ->columnSpanFull(),
                ]),

            // — HTML: raw script —
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

            // — Outstream thumbnail (optional preview image shown before video plays) —
            Section::make('Outstream Preview Image')
                ->description('Optional: thumbnail shown in the grid before the video autoplays. Recommended 640×360.')
                ->visible(fn ($get) => $get('placement') === 'outstream')
                ->schema([
                    FileUpload::make('outstream_thumbnail')
                        ->label('Preview Thumbnail')
                        ->image()
                        ->disk('public')
                        ->directory('media/ads')
                        ->columnSpanFull(),
                ]),

            // — Click-through (MP4 / HTML only) —
            Section::make('Click-Through')->schema([
                TextInput::make('click_url')
                    ->label('Click-Through URL')
                    ->url()
                    ->maxLength(2048)
                    ->placeholder('https://example.com/landing-page')
                    ->helperText('Optional. Clicking the ad opens this URL. Not used for VAST/VPAID (handled by ad network).')
                    ->columnSpanFull(),
            ])->visible(fn ($get) => in_array($get('type'), ['mp4', 'html'])),

            // — Targeting —
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
                            'guest' => 'Guests (not logged in)',
                            'default' => 'Default Users (free)',
                            'pro' => 'Pro Users',
                            'admin' => 'Admins',
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
                TextColumn::make('name')
                    ->weight('bold')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('type')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => strtoupper($state))
                    ->color(fn (string $state): string => match ($state) {
                        'mp4' => 'info',
                        'vast' => 'purple',
                        'vpaid' => 'indigo',
                        'html' => 'warning',
                        default => 'gray',
                    }),
                TextColumn::make('placement')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => ucwords(str_replace('_', '-', $state)))
                    ->color(fn (string $state): string => match ($state) {
                        'pre_roll' => 'success',
                        'mid_roll' => 'warning',
                        'post_roll' => 'danger',
                        'shorts' => 'info',
                        default => 'gray',
                    }),

                TextColumn::make('content')
                    ->label('Source')
                    ->formatStateUsing(function (string $state, VideoAd $record): string {
                        if ($record->type === 'mp4' && $record->file_path) {
                            return '📁 '.basename($record->file_path);
                        }
                        if ($record->type === 'html') {
                            return '🖥 HTML script ('.strlen($state).' chars)';
                        }

                        return Str::limit($state, 50);
                    })
                    ->color('gray')
                    ->size('sm'),

                TextColumn::make('weight')
                    ->alignCenter()
                    ->sortable(),
                TextColumn::make('category_ids')
                    ->label('Targeting')
                    ->formatStateUsing(function ($state, VideoAd $record): string {
                        $cats = ($record->category_ids && count($record->category_ids))
                            ? count($record->category_ids).' cats'
                            : 'All cats';
                        $roles = ($record->target_roles && count($record->target_roles))
                            ? implode(', ', $record->target_roles)
                            : 'All roles';

                        return "{$cats} · {$roles}";
                    })
                    ->color('gray')
                    ->size('sm'),

                TextColumn::make('impressions_count')
                    ->label('Impressions')
                    ->numeric()
                    ->sortable()
                    ->alignCenter(),
                TextColumn::make('clicks_count')
                    ->label('Clicks')
                    ->numeric()
                    ->sortable()
                    ->alignCenter(),

                ToggleColumn::make('is_active')
                    ->label('Active'),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->options([
                        'mp4' => 'MP4',
                        'vast' => 'VAST',
                        'vpaid' => 'VPAID',
                        'html' => 'HTML',
                    ]),
                SelectFilter::make('placement')
                    ->options([
                        'pre_roll' => 'Pre-Roll',
                        'mid_roll' => 'Mid-Roll',
                        'post_roll' => 'Post-Roll',
                        'outstream' => 'Outstream',
                        'shorts' => 'Shorts',
                    ]),
                TernaryFilter::make('is_active')->label('Active'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()
                    ->after(function (VideoAd $record) {
                        if ($record->file_path && Storage::disk('public')->exists($record->file_path)) {
                            Storage::disk('public')->delete($record->file_path);
                        }
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
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
            ->emptyStateIcon('phosphor-film-strip')
            ->striped();
    }

    public static function mutateFormDataBeforeSave(array $data): array
    {
        $data['category_ids'] = ! empty($data['category_ids']) ? array_map('intval', $data['category_ids']) : null;
        $data['target_roles'] = ! empty($data['target_roles']) ? $data['target_roles'] : null;

        return $data;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListVideoAds::route('/'),
            'create' => CreateVideoAd::route('/create'),
            'edit' => EditVideoAd::route('/{record}/edit'),
        ];
    }
}

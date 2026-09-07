<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ImageResource\Pages\CreateImage;
use App\Filament\Resources\ImageResource\Pages\EditImage;
use App\Filament\Resources\ImageResource\Pages\ListImages;
use App\Models\Image;
use App\Models\PointsTransaction;
use App\Models\Setting;
use App\Services\PointsService;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

class ImageResource extends Resource
{
    protected static ?string $model = Image::class;

    protected static string|\BackedEnum|null $navigationIcon = 'phosphor-image';

    protected static string|\UnitEnum|null $navigationGroup = 'Content';

    protected static ?int $navigationSort = 3;

    protected static ?string $recordTitleAttribute = 'title';

    // Moderation count is surfaced as a topbar pill (see SystemStatusBar::getActionItems).

    public static function getGloballySearchableAttributes(): array
    {
        return ['title', 'description', 'user.username'];
    }

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        return [
            'Uploader' => $record->user?->username,
            'Approved' => $record->is_approved ? 'Yes' : 'No',
        ];
    }

    public static function getGlobalSearchEloquentQuery(): Builder
    {
        return parent::getGlobalSearchEloquentQuery()->with('user');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                // Create-only: upload new image file
                Section::make('Image File')
                    ->schema([
                        FileUpload::make('image_file')
                            ->label('Image File')
                            ->disk('public')
                            ->directory('images/admin-uploads')
                            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/gif', 'image/webp'])
                            ->maxSize(524288) // 500MB
                            ->visibility('public')
                            ->helperText('Upload JPG, PNG, GIF, or WebP. Max 500MB. Image will be processed after creation.')
                            ->columnSpanFull(),
                    ])
                    ->visibleOn('create'),

                Section::make('Image Preview')
                    ->hiddenOn('create')
                    ->schema([
                        View::make('filament.resources.image-resource.components.image-preview')
                            ->columnSpanFull(),
                    ]),

                Section::make('Image Details')
                    ->schema([
                        TextInput::make('title')
                            ->required()
                            ->maxLength(200)
                            ->columnSpanFull(),
                        Textarea::make('description')
                            ->rows(3)
                            ->columnSpanFull(),
                        TextInput::make('alt_text')
                            ->label('Alt Text')
                            ->maxLength(255)
                            ->helperText('Leave blank to generate from the SEO template. A value set here is never overwritten unless seo:backfill-alt-text is run with --force.')
                            ->columnSpanFull(),
                        Select::make('user_id')
                            ->label('Uploader')
                            ->relationship('user', 'username')
                            ->required()
                            ->searchable()
                            ->preload(),
                        Select::make('category_id')
                            ->relationship('category', 'name')
                            ->required(fn (string $operation): bool => $operation === 'create')
                            ->searchable()
                            ->preload()
                            ->nullable(),
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
                            ->formatStateUsing(fn ($state) => $state ? number_format($state / 1048576, 2).' MB' : '—'),
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
                ImageColumn::make('thumbnail_display')
                    ->label('Preview')
                    ->getStateUsing(fn (Image $record): ?string => $record->thumbnail_url)
                    ->height(50)
                    ->width(50)
                    ->extraImgAttributes(['class' => 'rounded object-cover'])
                    ->toggleable(),
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
                    ->size('sm')
                    ->toggleable(),

                TextColumn::make('mime_type')
                    ->label('Type')
                    ->size('sm')
                    ->badge()
                    ->color('gray')
                    ->toggleable(),

                TextColumn::make('dimensions')
                    ->label('Size')
                    ->getStateUsing(fn (Image $record): string => "{$record->width}×{$record->height}")
                    ->size('sm')
                    ->toggleable(isToggledHiddenByDefault: true),

                IconColumn::make('is_approved')
                    ->boolean()
                    ->label('Approved')
                    ->trueIcon('phosphor-check-circle')
                    ->falseIcon('phosphor-x-circle')
                    ->trueColor('success')
                    ->falseColor('danger')
                    ->toggleable(),

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

                TextColumn::make('views_count')
                    ->label('Views')
                    ->numeric()
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('file_size')
                    ->label('File Size')
                    ->formatStateUsing(fn ($state) => $state ? number_format($state / 1048576, 1).' MB' : '—')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('created_at')
                    ->label('Uploaded')
                    ->since()
                    ->sortable()
                    ->size('sm')
                    ->color('gray')
                    ->toggleable(),
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
            ])
            ->recordActions([
                ActionGroup::make([
                    EditAction::make(),

                    Action::make('approve')
                        ->icon('phosphor-check-circle')
                        ->color('success')
                        ->requiresConfirmation()
                        ->action(function (Image $record) {
                            $record->update(['is_approved' => true]);
                            static::awardUploadPoints($record);
                        })
                        ->visible(fn (Image $record) => ! $record->is_approved),

                    Action::make('unapprove')
                        ->icon('phosphor-x-circle')
                        ->color('warning')
                        ->requiresConfirmation()
                        ->action(fn (Image $record) => $record->update(['is_approved' => false]))
                        ->visible(fn (Image $record) => $record->is_approved),

                    Action::make('view_frontend')
                        ->icon('phosphor-eye')
                        ->color('gray')
                        ->url(fn (Image $record): string => "/image/{$record->slug}")
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
                        ->action(function (Collection $records) {
                            $records->each(function (Image $i) {
                                $i->update(['is_approved' => true]);
                                static::awardUploadPoints($i);
                            });
                        })
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
            ->paginated([10, 25, 50, 100])
            ->emptyStateIcon('phosphor-image')
            ->emptyStateHeading('No images yet')
            ->emptyStateDescription('Upload images individually, or use the bulk uploader to add a batch.')
            ->emptyStateActions([
                Action::make('create')
                    ->color('success')
                    ->label('New Image')
                    ->icon('phosphor-plus')
                    ->url(static::getUrl('create'))
                    ->button(),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    /**
     * Award reward points to the uploader when an image is approved via moderation.
     * Idempotent per image (guarded inside PointsService).
     */
    protected static function awardUploadPoints(Image $image): void
    {
        if (! Setting::get('points_enabled', true) || ! Setting::get('points_image_upload_enabled', true)) {
            return;
        }

        $points = (int) Setting::get('points_per_image_upload', 25);
        if ($points <= 0) {
            return;
        }

        $image->loadMissing('user');
        if (! $image->user) {
            return;
        }

        app(PointsService::class)->award(
            $image->user,
            PointsTransaction::TYPE_IMAGE_UPLOAD,
            $points,
            $image,
            "Image approved: {$image->title}"
        );
    }

    public static function getPages(): array
    {
        return [
            'index' => ListImages::route('/'),
            'create' => CreateImage::route('/create'),
            'edit' => EditImage::route('/{record}/edit'),
        ];
    }
}

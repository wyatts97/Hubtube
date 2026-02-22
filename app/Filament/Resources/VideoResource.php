<?php

namespace App\Filament\Resources;

use App\Filament\Resources\VideoResource\Pages;
use App\Models\Category;
use App\Models\Setting;
use App\Models\User;
use App\Models\Video;
use App\Services\EmailService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

use App\Filament\Concerns\HasCustomizableNavigation;

class VideoResource extends Resource
{
    use HasCustomizableNavigation;
    protected static ?string $model = Video::class;
    protected static ?string $navigationIcon = 'heroicon-o-video-camera';
    protected static ?string $navigationGroup = 'Content';
    protected static ?int $navigationSort = 1;

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::where('is_approved', false)
            ->where('status', 'processed')
            ->count() ?: null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Video File')
                    ->schema([
                        Forms\Components\FileUpload::make('video_file')
                            ->label('Video File')
                            ->disk('public')
                            ->directory('videos/admin-uploads')
                            ->acceptedFileTypes(['video/mp4', 'video/quicktime', 'video/x-msvideo', 'video/x-matroska', 'video/webm'])
                            ->maxSize(5242880) // 5GB
                            ->visibility('public')
                            ->helperText('Upload MP4, MOV, AVI, MKV, or WebM. Max 5GB. Video will be processed after creation.')
                            ->columnSpanFull(),
                    ])
                    ->visibleOn('create'),

                Forms\Components\Section::make('Video Details')
                    ->schema([
                        Forms\Components\TextInput::make('title')
                            ->required()
                            ->maxLength(200)
                            ->columnSpanFull(),
                        Forms\Components\Textarea::make('description')
                            ->rows(4)
                            ->columnSpanFull(),
                        Forms\Components\Select::make('user_id')
                            ->label('Uploader')
                            ->relationship('user', 'username')
                            ->required()
                            ->searchable()
                            ->preload(),
                        Forms\Components\Select::make('category_id')
                            ->relationship('category', 'name')
                            ->searchable()
                            ->preload(),
                        Forms\Components\Select::make('status')
                            ->options([
                                'pending_download' => 'Pending Download',
                                'downloading' => 'Downloading',
                                'download_failed' => 'Download Failed',
                                'pending' => 'Pending',
                                'processing' => 'Processing',
                                'processed' => 'Published',
                                'failed' => 'Failed',
                            ])
                            ->default('pending')
                            ->required()
                            ->hiddenOn('create'),
                        Forms\Components\TagsInput::make('tags')
                            ->separator(',')
                            ->columnSpanFull(),
                        Forms\Components\DateTimePicker::make('scheduled_at')
                            ->label('Schedule Publish')
                            ->helperText('Leave empty to publish immediately when approved. Set a future date/time to auto-publish.')
                            ->native(false)
                            ->minDate(now())
                            ->hiddenOn('create')
                            ->columnSpanFull(),
                    ])->columns(2),

                Forms\Components\Section::make('Moderation & Flags')
                    ->schema([
                        Forms\Components\Toggle::make('is_approved')
                            ->label('Approved')
                            ->helperText('Video is visible to the public'),
                        Forms\Components\Toggle::make('is_featured')
                            ->label('Featured')
                            ->helperText('Show in featured sections'),
                        Forms\Components\Toggle::make('age_restricted')
                            ->label('Age Restricted')
                            ->default(true),
                        Forms\Components\Toggle::make('monetization_enabled')
                            ->label('Monetization')
                            ->visible(fn () => (bool) Setting::get('monetization_enabled', true)),
                    ])->columns(3),

                Forms\Components\Section::make('Pricing')
                    ->schema([
                        Forms\Components\TextInput::make('price')
                            ->numeric()
                            ->prefix('$')
                            ->default(0),
                        Forms\Components\TextInput::make('rent_price')
                            ->numeric()
                            ->prefix('$')
                            ->default(0),
                    ])->columns(2)
                    ->collapsed()
                    ->visible(fn () => (bool) Setting::get('monetization_enabled', true)),

                Forms\Components\Section::make('Technical Info')
                    ->schema([
                        Forms\Components\TextInput::make('video_path')
                            ->disabled()
                            ->columnSpanFull(),
                        Forms\Components\TextInput::make('storage_disk')
                            ->disabled(),
                        Forms\Components\TextInput::make('duration')
                            ->disabled()
                            ->suffix('seconds'),
                        Forms\Components\TextInput::make('size')
                            ->disabled()
                            ->formatStateUsing(fn ($state) => $state ? number_format($state / 1048576, 1) . ' MB' : '—'),
                        Forms\Components\TextInput::make('failure_reason')
                            ->disabled()
                            ->visible(fn ($record) => $record?->status === 'failed')
                            ->columnSpanFull(),
                    ])->columns(3)
                    ->hiddenOn('create')
                    ->collapsed(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\ImageColumn::make('thumbnail_display')
                    ->label('Thumbnail')
                    ->getStateUsing(fn (Video $record): ?string => $record->thumbnail_url)
                    ->height(50)
                    ->width(89)
                    ->extraImgAttributes(['class' => 'rounded object-cover'])
                    ->defaultImageUrl(url('/icons/icon-192x192.png')),

                Tables\Columns\TextColumn::make('title')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->limit(50)
                    ->description(fn (Video $record): string => $record->formatted_duration ?: '—'),

                Tables\Columns\TextColumn::make('user.username')
                    ->label('Uploader')
                    ->searchable()
                    ->sortable()
                    ->icon('heroicon-m-user')
                    ->size('sm'),

                Tables\Columns\TextColumn::make('category.name')
                    ->label('Category')
                    ->sortable()
                    ->size('sm')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (string $state, Video $record): string => match (true) {
                        $state === 'processed' && $record->is_approved => 'Published',
                        $state === 'processed' && !$record->is_approved => 'Needs Moderation',
                        $state === 'pending_download' => 'Pending Download',
                        $state === 'downloading' => 'Downloading',
                        $state === 'download_failed' => 'Download Failed',
                        default => ucfirst($state),
                    })
                    ->color(fn (string $state, Video $record): string => match (true) {
                        $state === 'processed' && $record->is_approved => 'success',
                        $state === 'processed' && !$record->is_approved => 'warning',
                        $state === 'pending' => 'gray',
                        $state === 'pending_download' => 'gray',
                        $state === 'downloading' => 'info',
                        $state === 'download_failed' => 'danger',
                        $state === 'processing' => 'info',
                        $state === 'failed' => 'danger',
                        default => 'gray',
                    }),

                Tables\Columns\IconColumn::make('is_approved')
                    ->boolean()
                    ->label('Approved')
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('danger'),

                Tables\Columns\IconColumn::make('is_featured')
                    ->boolean()
                    ->label('Featured')
                    ->trueIcon('heroicon-s-star')
                    ->falseIcon('heroicon-o-star')
                    ->trueColor('warning')
                    ->falseColor('gray')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('views_count')
                    ->label('Views')
                    ->numeric()
                    ->sortable()
                    ->icon('heroicon-m-eye')
                    ->iconColor('gray'),

                Tables\Columns\TextColumn::make('likes_count')
                    ->label('Likes')
                    ->numeric()
                    ->sortable()
                    ->icon('heroicon-m-hand-thumb-up')
                    ->iconColor('gray')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('size')
                    ->label('Size')
                    ->formatStateUsing(fn ($state) => $state ? number_format($state / 1048576, 1) . ' MB' : '—')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Uploaded')
                    ->since()
                    ->sortable()
                    ->size('sm')
                    ->color('gray')
                    ->tooltip(fn (Video $record): string => $record->created_at?->format('M j, Y g:i A') ?? ''),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'pending_download' => 'Pending Download',
                        'downloading' => 'Downloading',
                        'download_failed' => 'Download Failed',
                        'pending' => 'Pending',
                        'processing' => 'Processing',
                        'processed' => 'Published',
                        'failed' => 'Failed',
                    ]),

                Tables\Filters\TernaryFilter::make('is_approved')
                    ->label('Approved'),

                Tables\Filters\TernaryFilter::make('is_featured')
                    ->label('Featured'),

                Tables\Filters\SelectFilter::make('category_id')
                    ->label('Category')
                    ->relationship('category', 'name')
                    ->searchable()
                    ->preload(),

                Tables\Filters\SelectFilter::make('user_id')
                    ->label('Uploader')
                    ->relationship('user', 'username')
                    ->searchable()
                    ->preload(),

                Tables\Filters\Filter::make('needs_moderation')
                    ->label('Needs Moderation')
                    ->query(fn (Builder $query): Builder => $query->where('is_approved', false)->where('status', 'processed'))
                    ->toggle(),

                Tables\Filters\Filter::make('created_at')
                    ->form([
                        Forms\Components\DatePicker::make('from')
                            ->label('From'),
                        Forms\Components\DatePicker::make('until')
                            ->label('Until'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['from'], fn (Builder $q, $date) => $q->whereDate('created_at', '>=', $date))
                            ->when($data['until'], fn (Builder $q, $date) => $q->whereDate('created_at', '<=', $date));
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];
                        if ($data['from'] ?? null) {
                            $indicators['from'] = 'From ' . \Carbon\Carbon::parse($data['from'])->toFormattedDateString();
                        }
                        if ($data['until'] ?? null) {
                            $indicators['until'] = 'Until ' . \Carbon\Carbon::parse($data['until'])->toFormattedDateString();
                        }
                        return $indicators;
                    }),
            ], layout: Tables\Enums\FiltersLayout::AboveContentCollapsible)
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\ViewAction::make(),
                    Tables\Actions\EditAction::make(),

                    Tables\Actions\Action::make('approve')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->requiresConfirmation()
                        ->action(function (Video $record) {
                            $record->update([
                                'is_approved' => true,
                                'published_at' => $record->published_at ?? now(),
                            ]);
                            $record->loadMissing('user');
                            if ($record->user) {
                                EmailService::sendToUser('video-approved', $record->user->email, [
                                    'username' => $record->user->username,
                                    'video_title' => $record->title,
                                    'video_url' => url("/{$record->slug}"),
                                ]);
                            }
                        })
                        ->visible(fn (Video $record) => !$record->is_approved && $record->status === 'processed'),

                    Tables\Actions\Action::make('unapprove')
                        ->icon('heroicon-o-x-circle')
                        ->color('warning')
                        ->requiresConfirmation()
                        ->form([
                            Forms\Components\Textarea::make('rejection_reason')
                                ->label('Reason (optional)')
                                ->rows(3),
                        ])
                        ->action(function (Video $record, array $data) {
                            $record->update(['is_approved' => false]);
                            $record->loadMissing('user');
                            if ($record->user) {
                                EmailService::sendToUser('video-rejected', $record->user->email, [
                                    'username' => $record->user->username,
                                    'video_title' => $record->title,
                                    'rejection_reason' => $data['rejection_reason'] ?? 'No reason provided.',
                                ]);
                            }
                        })
                        ->visible(fn (Video $record) => $record->is_approved),

                    Tables\Actions\Action::make('feature')
                        ->icon('heroicon-o-star')
                        ->color('warning')
                        ->action(fn (Video $record) => $record->update(['is_featured' => !$record->is_featured]))
                        ->label(fn (Video $record) => $record->is_featured ? 'Unfeature' : 'Feature'),

                    Tables\Actions\Action::make('reprocess')
                        ->icon('heroicon-o-arrow-path')
                        ->color('info')
                        ->requiresConfirmation()
                        ->modalDescription('This will re-dispatch the video processing job. Existing transcoded files will be skipped.')
                        ->action(function (Video $record) {
                            $record->update(['status' => 'pending']);
                            \App\Jobs\ProcessVideoJob::dispatch($record)->onQueue('video-processing');
                        })
                        ->visible(fn (Video $record) => in_array($record->status, ['failed', 'processing'])),

                    Tables\Actions\Action::make('view_frontend')
                        ->icon('heroicon-o-eye')
                        ->color('gray')
                        ->url(fn (Video $record): string => '/' . $record->slug)
                        ->openUrlInNewTab()
                        ->visible(fn (Video $record) => $record->status === 'processed' && $record->is_approved),

                    Tables\Actions\DeleteAction::make(),
                ]),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('approve')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->requiresConfirmation()
                        ->action(fn (Collection $records) => $records->each(fn (Video $v) => $v->update([
                            'is_approved' => true,
                            'published_at' => $v->published_at ?? now(),
                        ])))
                        ->deselectRecordsAfterCompletion(),

                    Tables\Actions\BulkAction::make('unapprove')
                        ->icon('heroicon-o-x-circle')
                        ->color('warning')
                        ->requiresConfirmation()
                        ->action(fn (Collection $records) => $records->each(fn (Video $v) => $v->update(['is_approved' => false])))
                        ->deselectRecordsAfterCompletion(),

                    Tables\Actions\BulkAction::make('feature')
                        ->icon('heroicon-o-star')
                        ->color('warning')
                        ->action(fn (Collection $records) => $records->each(fn (Video $v) => $v->update(['is_featured' => true])))
                        ->deselectRecordsAfterCompletion(),

                    Tables\Actions\BulkAction::make('unfeature')
                        ->icon('heroicon-o-star')
                        ->color('gray')
                        ->action(fn (Collection $records) => $records->each(fn (Video $v) => $v->update(['is_featured' => false])))
                        ->deselectRecordsAfterCompletion(),

                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->poll('30s')
            ->striped()
            ->paginated([10, 25, 50, 100]);
    }

    public static function getWidgets(): array
    {
        return [
            VideoResource\Widgets\VideoStatsOverview::class,
        ];
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListVideos::route('/'),
            'create' => Pages\CreateVideo::route('/create'),
            'edit' => Pages\EditVideo::route('/{record}/edit'),
        ];
    }
}

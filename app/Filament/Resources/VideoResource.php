<?php

namespace App\Filament\Resources;

use Illuminate\Database\Eloquent\Model;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Infolists\Components\ViewEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Forms\Components\FileUpload;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\View;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\Filter;
use Filament\Forms\Components\DatePicker;
use Carbon\Carbon;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\ViewAction;
use Filament\Actions\EditAction;
use App\Jobs\ProcessVideoJob;
use Filament\Actions\DeleteAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\BulkAction;
use Filament\Actions\DeleteBulkAction;
use App\Filament\Resources\VideoResource\Widgets\VideoStatsOverview;
use App\Filament\Resources\VideoResource\Pages\ListVideos;
use App\Filament\Resources\VideoResource\Pages\CreateVideo;
use App\Filament\Resources\VideoResource\Pages\ViewVideo;
use App\Filament\Resources\VideoResource\Pages\EditVideo;
use App\Filament\Resources\VideoResource\Pages;
use App\Models\Category;
use App\Models\Setting;
use App\Models\User;
use App\Models\Video;
use App\Events\VideoProcessed;
use App\Models\Notification as AppNotification;
use App\Services\EmailService;
use App\Services\VideoService;
use Filament\Forms;
use Filament\Infolists;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;


class VideoResource extends Resource
{
    protected static ?string $model = Video::class;
    protected static string | \BackedEnum | null $navigationIcon = 'phosphor-video-camera';
    protected static string | \UnitEnum | null $navigationGroup = 'Content';
    protected static ?int $navigationSort = 1;
    protected static ?string $recordTitleAttribute = 'title';

    public static function getGloballySearchableAttributes(): array
    {
        return ['title', 'slug', 'user.username'];
    }

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        return [
            'Uploader' => $record->user?->username ?? '—',
            'Status'   => ucfirst($record->status),
            'Views'    => number_format($record->views_count),
        ];
    }

    public static function getGlobalSearchEloquentQuery(): Builder
    {
        return parent::getGlobalSearchEloquentQuery()->with('user');
    }

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::where('is_approved', false)
            ->where('status', 'processed')
            ->whereNull('queue_order')
            ->count() ?: null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Video Preview')
                    ->schema([
                        ViewEntry::make('video_player')
                            ->view('filament.resources.video-resource.components.video-player-infolist')
                            ->columnSpanFull(),
                    ])
                    ->visible(fn (Video $record) => $record->video_path || $record->video_url)
                    ->collapsible(),

                Section::make('Details')
                    ->schema([
                        TextEntry::make('title'),
                        TextEntry::make('user.username')
                            ->label('Uploader'),
                        TextEntry::make('status')
                            ->badge()
                            ->color(fn (string $state): string => match ($state) {
                                'processed' => 'success',
                                'processing' => 'info',
                                'pending' => 'warning',
                                'failed' => 'danger',
                                default => 'gray',
                            }),
                        TextEntry::make('category.name')
                            ->label('Category')
                            ->default('—'),
                        TextEntry::make('views_count')
                            ->label('Views')
                            ->numeric(),
                        TextEntry::make('formatted_duration')
                            ->label('Duration')
                            ->default('—'),
                        TextEntry::make('description')
                            ->columnSpanFull()
                            ->default('No description'),
                    ])->columns(3),
            ]);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                // Create-only: upload new video file
                Section::make('Video File')
                    ->schema([
                        FileUpload::make('video_file')
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

                // Tabbed layout for compact edit view
                Tabs::make('VideoEdit')
                    ->columnSpanFull()
                    ->persistTabInQueryString('tab')
                    ->tabs([
                        Tab::make('Details')
                            ->icon('phosphor-file-text')
                            ->schema([
                                TextInput::make('title')
                                    ->required()
                                    ->maxLength(200)
                                    ->columnSpanFull(),
                                Textarea::make('description')
                                    ->rows(4)
                                    ->columnSpanFull(),
                                Select::make('user_id')
                                    ->label('Uploader')
                                    ->relationship('user', 'username')
                                    ->required()
                                    ->searchable()
                                    ->preload(),
                                Select::make('category_id')
                                    ->relationship('category', 'name')
                                    ->searchable()
                                    ->preload(),
                                Select::make('status')
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
                                DateTimePicker::make('scheduled_at')
                                    ->label('Schedule Publish')
                                    ->helperText('Leave empty to publish when approved. Set a future date/time to auto-publish.')
                                    ->native(false)
                                    ->minDate(now())
                                    ->hiddenOn('create'),
                                TagsInput::make('tags')
                                    ->separator(',')
                                    ->columnSpanFull(),
                            ])->columns(2),

                        Tab::make('Moderation')
                            ->icon('phosphor-shield-check')
                            ->schema([
                                Toggle::make('is_approved')
                                    ->label('Approved')
                                    ->helperText('Video is visible to the public'),
                                Toggle::make('is_featured')
                                    ->label('Featured')
                                    ->helperText('Show in featured sections'),
                                Toggle::make('age_restricted')
                                    ->label('Age Restricted')
                                    ->default(true),
                                Toggle::make('monetization_enabled')
                                    ->label('Monetization')
                                    ->visible(fn () => (bool) Setting::get('monetization_enabled', true)),
                            ])->columns(2),

                        Tab::make('Pricing')
                            ->icon('phosphor-currency-dollar')
                            ->visible(fn () => (bool) Setting::get('monetization_enabled', true))
                            ->schema([
                                TextInput::make('price')
                                    ->label('Purchase Price')
                                    ->numeric()
                                    ->prefix('$')
                                    ->default(0),
                                TextInput::make('rent_price')
                                    ->label('Rental Price')
                                    ->numeric()
                                    ->prefix('$')
                                    ->default(0),
                            ])->columns(2),

                        Tab::make('Technical')
                            ->icon('phosphor-cog-6-tooth')
                            ->hiddenOn('create')
                            ->schema([
                                TextInput::make('video_path')
                                    ->disabled()
                                    ->columnSpanFull(),
                                TextInput::make('storage_disk')
                                    ->disabled(),
                                TextInput::make('duration')
                                    ->disabled()
                                    ->suffix('seconds'),
                                TextInput::make('size')
                                    ->disabled()
                                    ->formatStateUsing(fn ($state) => $state ? number_format($state / 1048576, 1) . ' MB' : '—'),
                                TextInput::make('failure_reason')
                                    ->disabled()
                                    ->visible(fn ($record) => $record?->status === 'failed')
                                    ->columnSpanFull(),
                            ])->columns(2),

                        Tab::make('Activity')
                            ->icon('phosphor-clipboard-text')
                            ->hiddenOn('create')
                            ->schema([
                                View::make('filament.resources.video-resource.components.activity-log')
                                    ->columnSpanFull(),
                            ]),
                    ]),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['user', 'category']);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->poll('15s')
            ->columns([
                ImageColumn::make('thumbnail_display')
                    ->label('Thumbnail')
                    ->getStateUsing(fn (Video $record): ?string => $record->thumbnail_url)
                    ->height(50)
                    ->width(89)
                    ->extraImgAttributes([
                        'class' => 'ht-thumb-hover',
                    ])
                    ->extraAttributes(['class' => 'ht-thumb-col'])
                    ->defaultImageUrl(url('/icons/icon-192x192.png')),

                TextColumn::make('title')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->limit(50)
                    ->description(fn (Video $record): string => $record->formatted_duration ?: '—'),

                TextColumn::make('user.username')
                    ->label('Uploader')
                    ->searchable()
                    ->sortable()
                    ->icon('phosphor-user')
                    ->size('sm'),

                TextColumn::make('category.name')
                    ->label('Category')
                    ->sortable()
                    ->size('sm')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (string $state, Video $record): string => match (true) {
                        $state === 'processed' && $record->is_approved && $record->published_at => 'Published',
                        $state === 'processed' && !is_null($record->queue_order) => 'Scheduled',
                        $state === 'processed' && !$record->is_approved => 'Needs Moderation',
                        $state === 'pending_download' => 'Pending Download',
                        $state === 'downloading' => 'Downloading',
                        $state === 'download_failed' => 'Download Failed',
                        default => ucfirst($state),
                    })
                    ->color(fn (string $state, Video $record): string => match (true) {
                        $state === 'processed' && $record->is_approved && $record->published_at => 'success',
                        $state === 'processed' && !is_null($record->queue_order) => 'info',
                        $state === 'processed' && !$record->is_approved => 'warning',
                        $state === 'pending' => 'gray',
                        $state === 'pending_download' => 'gray',
                        $state === 'downloading' => 'info',
                        $state === 'download_failed' => 'danger',
                        $state === 'processing' => 'info',
                        $state === 'failed' => 'danger',
                        default => 'gray',
                    }),

                // Hidden by default — Status badge above already communicates approved/needs-moderation.
                // Still available via the column toggle for admins who want a standalone flag.
                IconColumn::make('is_approved')
                    ->boolean()
                    ->label('Approved')
                    ->alignCenter()
                    ->trueIcon('phosphor-check-circle')
                    ->falseIcon('phosphor-clock')
                    ->trueColor('success')
                    ->falseColor('warning')
                    ->toggleable(isToggledHiddenByDefault: true),

                IconColumn::make('is_featured')
                    ->boolean()
                    ->label('Featured')
                    ->alignCenter()
                    ->trueIcon('phosphor-star')
                    ->falseIcon('phosphor-star')
                    ->trueColor('warning')
                    ->falseColor('gray')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('views_count')
                    ->label('Views')
                    ->numeric()
                    ->sortable()
                    ->alignRight()
                    ->icon('phosphor-eye')
                    ->iconColor('gray'),

                TextColumn::make('likes_count')
                    ->label('Likes')
                    ->numeric()
                    ->sortable()
                    ->icon('phosphor-thumbs-up')
                    ->iconColor('gray')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('size')
                    ->label('Size')
                    ->formatStateUsing(fn ($state) => $state ? number_format($state / 1048576, 1) . ' MB' : '—')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('created_at')
                    ->label('Uploaded')
                    ->since()
                    ->sortable()
                    ->size('sm')
                    ->color('gray')
                    ->tooltip(fn (Video $record): string => $record->created_at?->format('M j, Y g:i A') ?? ''),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'pending_download' => 'Pending Download',
                        'downloading' => 'Downloading',
                        'download_failed' => 'Download Failed',
                        'pending' => 'Pending',
                        'processing' => 'Processing',
                        'processed' => 'Published',
                        'failed' => 'Failed',
                    ]),

                TernaryFilter::make('is_approved')
                    ->label('Approved'),

                TernaryFilter::make('is_featured')
                    ->label('Featured'),

                SelectFilter::make('category_id')
                    ->label('Category')
                    ->relationship('category', 'name')
                    ->searchable()
                    ->preload(),

                SelectFilter::make('user_id')
                    ->label('Uploader')
                    ->relationship('user', 'username')
                    ->searchable()
                    ->preload(),

                Filter::make('needs_moderation')
                    ->label('Needs Moderation')
                    ->query(fn (Builder $query): Builder => $query->where('is_approved', false)->where('status', 'processed'))
                    ->toggle(),

                Filter::make('created_at')
                    ->schema([
                        DatePicker::make('from')
                            ->label('From'),
                        DatePicker::make('until')
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
                            $indicators['from'] = 'From ' . Carbon::parse($data['from'])->toFormattedDateString();
                        }
                        if ($data['until'] ?? null) {
                            $indicators['until'] = 'Until ' . Carbon::parse($data['until'])->toFormattedDateString();
                        }
                        return $indicators;
                    }),
            ], layout: FiltersLayout::AboveContentCollapsible)
            ->recordActions([
                // Always-visible approve button for videos needing moderation
                Action::make('quickApprove')
                    ->label('Approve')
                    ->icon('phosphor-check-circle')
                    ->color('success')
                    ->button()
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
                        $alreadyNotified = AppNotification::where('user_id', $record->user_id)
                            ->where('type', 'video_processed')
                            ->where('data->video_id', $record->id)
                            ->exists();
                        if (!$alreadyNotified) {
                            event(new VideoProcessed($record));
                        }
                    })
                    ->visible(fn (Video $record) => !$record->is_approved && $record->status === 'processed'),

                ActionGroup::make([
                    ViewAction::make(),
                    EditAction::make(),

                    Action::make('approve')
                        ->icon('phosphor-check-circle')
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

                            // Fire VideoProcessed so "published" notification + tweet go out
                            $alreadyNotified = AppNotification::where('user_id', $record->user_id)
                                ->where('type', 'video_processed')
                                ->where('data->video_id', $record->id)
                                ->exists();
                            if (!$alreadyNotified) {
                                event(new VideoProcessed($record));
                            }
                        })
                        ->visible(fn (Video $record) => !$record->is_approved && $record->status === 'processed'),

                    Action::make('unpublish')
                        ->label('Unpublish')
                        ->icon('phosphor-eye-slash')
                        ->color('warning')
                        ->requiresConfirmation()
                        ->modalHeading('Unpublish Video')
                        ->modalDescription('This will hide the video from the live site and set it back to "needs moderation" status. The video will not be deleted.')
                        ->action(function (Video $record) {
                            $record->update([
                                'is_approved' => false,
                            ]);
                        })
                        ->visible(fn (Video $record) => $record->is_approved && $record->status === 'processed'),

                    Action::make('reject')
                        ->label('Reject')
                        ->icon('phosphor-x-circle')
                        ->color('danger')
                        ->modalHeading('Reject Video')
                        ->modalDescription('Select a reason for rejecting this video. The uploader will be notified by email.')
                        ->schema([
                            Select::make('rejection_reason')
                                ->label('Reason for Rejection')
                                ->required()
                                ->options([
                                    'Copyrighted Material' => 'Copyrighted Material',
                                    'Illegal Content' => 'Illegal Content',
                                    'Too Short' => 'Too Short',
                                    'Too Low Quality' => 'Too Low Quality',
                                    'Violates Website Guidelines' => 'Violates Website Guidelines',
                                    'Spam or Misleading' => 'Spam or Misleading',
                                    'Duplicate Content' => 'Duplicate Content',
                                    'Other' => 'Other (specify below)',
                                ]),
                            Textarea::make('custom_reason')
                                ->label('Additional Details (optional)')
                                ->rows(3)
                                ->placeholder('Provide any additional context for the uploader...'),
                        ])
                        ->action(function (Video $record, array $data) {
                            $reason = $data['rejection_reason'];
                            if ($reason === 'Other' && !empty($data['custom_reason'])) {
                                $reason = $data['custom_reason'];
                            } elseif (!empty($data['custom_reason'])) {
                                $reason .= ' — ' . $data['custom_reason'];
                            }

                            $record->update([
                                'is_approved' => false,
                                'failure_reason' => $reason,
                            ]);

                            $record->loadMissing('user');
                            if ($record->user) {
                                EmailService::sendToUser('video-rejected', $record->user->email, [
                                    'username' => $record->user->username,
                                    'video_title' => $record->title,
                                    'rejection_reason' => $reason,
                                ]);
                            }
                        })
                        ->visible(fn (Video $record) => !$record->is_approved && $record->status === 'processed'),

                    Action::make('feature')
                        ->icon('phosphor-star')
                        ->color('warning')
                        ->action(fn (Video $record) => $record->update(['is_featured' => !$record->is_featured]))
                        ->label(fn (Video $record) => $record->is_featured ? 'Unfeature' : 'Feature'),

                    Action::make('addToSchedule')
                        ->label('Add to Schedule')
                        ->icon('phosphor-calendar')
                        ->color('info')
                        ->requiresConfirmation()
                        ->modalDescription('This will add the video to the publishing schedule. It will be auto-approved and published at the scheduled time.')
                        ->action(function (Video $record) {
                            $maxOrder = Video::max('queue_order') ?? 0;
                            $record->update([
                                'queue_order' => $maxOrder + 1,
                                'is_approved' => true,
                                'published_at' => null,
                                'requires_schedule' => true,
                            ]);
                            app(VideoService::class)->recalculateScheduleQueue();
                        })
                        ->visible(fn (Video $record) => $record->status === 'processed' && is_null($record->queue_order)),

                    Action::make('removeFromSchedule')
                        ->label('Remove from Schedule')
                        ->icon('phosphor-calendar')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->action(function (Video $record) {
                            $record->update([
                                'queue_order' => null,
                                'scheduled_at' => null,
                                'requires_schedule' => false,
                            ]);
                            app(VideoService::class)->recalculateScheduleQueue();
                        })
                        ->visible(fn (Video $record) => !is_null($record->queue_order)),

                    Action::make('reprocess')
                        ->icon('phosphor-arrows-clockwise')
                        ->color('info')
                        ->requiresConfirmation()
                        ->modalDescription('This will re-dispatch the video processing job. Existing transcoded files will be skipped.')
                        ->action(function (Video $record) {
                            $record->update(['status' => 'pending']);
                            ProcessVideoJob::dispatch($record)->onQueue('video-processing');
                        })
                        ->visible(fn (Video $record) => in_array($record->status, ['failed', 'processing'])),

                    Action::make('view_frontend')
                        ->icon('phosphor-eye')
                        ->color('gray')
                        ->url(fn (Video $record): string => url('/' . $record->slug))
                        ->openUrlInNewTab()
                        ->visible(fn (Video $record) => $record->status === 'processed' && $record->is_approved),

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
                            $records->each(function (Video $v) {
                                $v->update([
                                    'is_approved' => true,
                                    'published_at' => $v->published_at ?? now(),
                                ]);

                                // Fire VideoProcessed so "published" notification + tweet go out
                                $alreadyNotified = AppNotification::where('user_id', $v->user_id)
                                    ->where('type', 'video_processed')
                                    ->where('data->video_id', $v->id)
                                    ->exists();
                                if (!$alreadyNotified) {
                                    event(new VideoProcessed($v));
                                }
                            });
                        })
                        ->deselectRecordsAfterCompletion(),

                    BulkAction::make('unapprove')
                        ->icon('phosphor-x-circle')
                        ->color('warning')
                        ->requiresConfirmation()
                        ->action(fn (Collection $records) => $records->each(fn (Video $v) => $v->update(['is_approved' => false])))
                        ->deselectRecordsAfterCompletion(),

                    BulkAction::make('feature')
                        ->icon('phosphor-star')
                        ->color('warning')
                        ->action(fn (Collection $records) => $records->each(fn (Video $v) => $v->update(['is_featured' => true])))
                        ->deselectRecordsAfterCompletion(),

                    BulkAction::make('unfeature')
                        ->icon('phosphor-star')
                        ->color('gray')
                        ->action(fn (Collection $records) => $records->each(fn (Video $v) => $v->update(['is_featured' => false])))
                        ->deselectRecordsAfterCompletion(),

                    BulkAction::make('addToSchedule')
                        ->label('Add to Schedule')
                        ->icon('phosphor-calendar')
                        ->color('info')
                        ->requiresConfirmation()
                        ->action(function (Collection $records) {
                            $maxOrder = Video::max('queue_order') ?? 0;
                            foreach ($records as $video) {
                                if ($video->status === 'processed' && is_null($video->queue_order)) {
                                    $maxOrder++;
                                    $video->update([
                                        'queue_order' => $maxOrder,
                                        'is_approved' => true,
                                        'published_at' => null,
                                        'requires_schedule' => true,
                                    ]);
                                }
                            }
                            app(VideoService::class)->recalculateScheduleQueue();
                        })
                        ->deselectRecordsAfterCompletion(),

                    DeleteBulkAction::make(),
                ]),
            ])
            ->striped()
            ->paginated([10, 25, 50, 100])
            ->recordUrl(fn (Video $record): string => route('filament.admin.resources.videos.edit', $record))
            ->emptyStateIcon('phosphor-video-camera')
            ->emptyStateHeading('No videos yet')
            ->emptyStateDescription('Upload your first video to get started.')
            ->emptyStateActions([
                Action::make('create')
                    ->label('Upload Video')
                    ->icon('phosphor-arrow-up-tray')
                    ->url(route('filament.admin.resources.videos.create'))
                    ->button(),
            ]);
    }

    public static function getWidgets(): array
    {
        return [
            VideoStatsOverview::class,
        ];
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListVideos::route('/'),
            'create' => CreateVideo::route('/create'),
            'view' => ViewVideo::route('/{record}'),
            'edit' => EditVideo::route('/{record}/edit'),
        ];
    }
}

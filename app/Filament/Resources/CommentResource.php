<?php

namespace App\Filament\Resources;

use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Actions\EditAction;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\BulkAction;
use App\Filament\Resources\CommentResource\Pages\ListComments;
use App\Filament\Resources\CommentResource\Pages\EditComment;
use App\Filament\Resources\CommentResource\Pages;
use App\Models\Comment;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class CommentResource extends Resource
{
    protected static ?string $model = Comment::class;
    protected static string | \BackedEnum | null $navigationIcon = 'phosphor-chat-text';
    protected static string | \UnitEnum | null $navigationGroup = 'Content';
    protected static ?int $navigationSort = 4;
    protected static ?string $recordTitleAttribute = 'content';

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::where('is_approved', false)->count() ?: null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Comment Details')
                    ->schema([
                        Select::make('user_id')
                            ->relationship('user', 'username')
                            ->required()
                            ->searchable()
                            ->disabled(),
                        Select::make('video_id')
                            ->relationship('video', 'title')
                            ->required()
                            ->searchable()
                            ->disabled(),
                        Textarea::make('content')
                            ->required()
                            ->maxLength(5000)
                            ->columnSpanFull(),
                    ])->columns(2),
                Section::make('Status')
                    ->schema([
                        Toggle::make('is_approved')
                            ->label('Approved'),
                        Toggle::make('is_pinned')
                            ->label('Pinned'),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['user', 'video' => fn ($q) => $q->withTrashed()]))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('user.username')
                    ->label('User')
                    ->searchable()
                    ->icon('phosphor-user')
                    ->iconColor('gray')
                    ->weight('semibold')
                    ->grow(false),
                TextColumn::make('video.title')
                    ->label('Video')
                    ->limit(30)
                    ->placeholder('(deleted)')
                    ->url(fn (Comment $record): ?string => $record->video?->slug ? url('/' . $record->video->slug) : null)
                    ->openUrlInNewTab()
                    ->searchable()
                    ->color('gray')
                    ->size('sm'),
                TextColumn::make('content')
                    ->label('Comment')
                    ->wrap()
                    ->limit(120)
                    ->searchable(),

                TextColumn::make('moderation_status')
                    ->label('Status')
                    ->badge()
                    ->alignCenter()
                    ->getStateUsing(fn (Comment $record): string => $record->is_approved ? 'Approved' : 'Pending')
                    ->color(fn (string $state): string => $state === 'Approved' ? 'success' : 'warning')
                    ->icon(fn (string $state): string => $state === 'Approved' ? 'phosphor-check-circle' : 'phosphor-clock'),

                IconColumn::make('is_pinned')
                    ->label('Pinned')
                    ->alignCenter()
                    ->boolean()
                    ->trueIcon('phosphor-bookmark')
                    ->falseIcon('phosphor-bookmark')
                    ->trueColor('warning')
                    ->falseColor('gray')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('likes_count')
                    ->label('Likes')
                    ->numeric()
                    ->sortable()
                    ->alignRight()
                    ->toggleable(isToggledHiddenByDefault: false),

                TextColumn::make('created_at')
                    ->label('Posted')
                    ->since()
                    ->sortable()
                    ->color('gray')
                    ->size('sm')
                    ->tooltip(fn (Comment $record): string => $record->created_at?->format('M j, Y g:i A') ?? ''),
            ])
            ->filters([
                TernaryFilter::make('is_approved'),
                TernaryFilter::make('is_pinned'),
            ])
            ->recordActions([
                EditAction::make(),
                Action::make('approve')
                    ->icon('phosphor-check')
                    ->color('success')
                    ->action(fn (Comment $record) => $record->update(['is_approved' => true]))
                    ->visible(fn (Comment $record) => !$record->is_approved),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    BulkAction::make('approve')
                        ->icon('phosphor-check')
                        ->action(fn ($records) => $records->each->update(['is_approved' => true])),
                ]),
            ])
            ->striped();
    }

    public static function getPages(): array
    {
        return [
            'index' => ListComments::route('/'),
            'edit' => EditComment::route('/{record}/edit'),
        ];
    }
}
<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CommentResource\Pages;
use App\Models\Comment;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;


class CommentResource extends Resource
{
    protected static ?string $model = Comment::class;
    protected static ?string $navigationIcon = 'heroicon-o-chat-bubble-left-right';
    protected static ?string $navigationGroup = 'Content';
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

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Comment Details')
                    ->schema([
                        Forms\Components\Select::make('user_id')
                            ->relationship('user', 'username')
                            ->required()
                            ->searchable()
                            ->disabled(),
                        Forms\Components\Select::make('video_id')
                            ->relationship('video', 'title')
                            ->required()
                            ->searchable()
                            ->disabled(),
                        Forms\Components\Textarea::make('content')
                            ->required()
                            ->maxLength(5000)
                            ->columnSpanFull(),
                    ])->columns(2),
                Forms\Components\Section::make('Status')
                    ->schema([
                        Forms\Components\Toggle::make('is_approved')
                            ->label('Approved'),
                        Forms\Components\Toggle::make('is_pinned')
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
                Tables\Columns\TextColumn::make('user.username')
                    ->label('User')
                    ->searchable()
                    ->icon('heroicon-m-user')
                    ->iconColor('gray')
                    ->weight('semibold')
                    ->grow(false),
                Tables\Columns\TextColumn::make('video.title')
                    ->label('Video')
                    ->limit(30)
                    ->placeholder('(deleted)')
                    ->url(fn (Comment $record): ?string => $record->video?->slug ? url('/' . $record->video->slug) : null)
                    ->openUrlInNewTab()
                    ->searchable()
                    ->color('gray')
                    ->size('sm'),
                Tables\Columns\TextColumn::make('content')
                    ->label('Comment')
                    ->wrap()
                    ->limit(120)
                    ->searchable(),
                Tables\Columns\TextColumn::make('moderation_status')
                    ->label('Status')
                    ->badge()
                    ->alignCenter()
                    ->getStateUsing(fn (Comment $record): string => $record->is_approved ? 'Approved' : 'Pending')
                    ->color(fn (string $state): string => $state === 'Approved' ? 'success' : 'warning')
                    ->icon(fn (string $state): string => $state === 'Approved' ? 'heroicon-m-check-circle' : 'heroicon-m-clock'),
                Tables\Columns\IconColumn::make('is_pinned')
                    ->label('Pinned')
                    ->alignCenter()
                    ->boolean()
                    ->trueIcon('heroicon-s-bookmark')
                    ->falseIcon('heroicon-o-bookmark')
                    ->trueColor('warning')
                    ->falseColor('gray')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('likes_count')
                    ->label('Likes')
                    ->numeric()
                    ->sortable()
                    ->alignRight()
                    ->toggleable(isToggledHiddenByDefault: false),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Posted')
                    ->since()
                    ->sortable()
                    ->color('gray')
                    ->size('sm')
                    ->tooltip(fn (Comment $record): string => $record->created_at?->format('M j, Y g:i A') ?? ''),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_approved'),
                Tables\Filters\TernaryFilter::make('is_pinned'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('approve')
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->action(fn (Comment $record) => $record->update(['is_approved' => true]))
                    ->visible(fn (Comment $record) => !$record->is_approved),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\BulkAction::make('approve')
                        ->icon('heroicon-o-check')
                        ->action(fn ($records) => $records->each->update(['is_approved' => true])),
                ]),
            ])
            ->striped();
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListComments::route('/'),
            'edit' => Pages\EditComment::route('/{record}/edit'),
        ];
    }
}

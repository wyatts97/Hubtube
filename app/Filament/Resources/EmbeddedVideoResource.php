<?php

namespace App\Filament\Resources;

use App\Filament\Resources\EmbeddedVideoResource\Pages;
use App\Models\EmbeddedVideo;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class EmbeddedVideoResource extends Resource
{
    protected static ?string $model = EmbeddedVideo::class;

    protected static ?string $navigationIcon = 'heroicon-o-link';
    protected static ?string $navigationLabel = 'Embedded Videos (Legacy)';
    protected static ?string $navigationGroup = 'Content Management';
    protected static ?int $navigationSort = 99;
    protected static bool $shouldRegisterNavigation = false;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Video Information')
                    ->schema([
                        Forms\Components\TextInput::make('title')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\Textarea::make('description')
                            ->rows(3),
                        Forms\Components\TextInput::make('source_site')
                            ->disabled(),
                        Forms\Components\TextInput::make('source_video_id')
                            ->disabled(),
                    ])
                    ->columns(2),
                    
                Forms\Components\Section::make('Embed Details')
                    ->schema([
                        Forms\Components\TextInput::make('embed_url')
                            ->url()
                            ->disabled(),
                        Forms\Components\Textarea::make('embed_code')
                            ->rows(3)
                            ->disabled(),
                        Forms\Components\TextInput::make('source_url')
                            ->url()
                            ->disabled(),
                    ]),
                    
                Forms\Components\Section::make('Metadata')
                    ->schema([
                        Forms\Components\TagsInput::make('tags'),
                        Forms\Components\TagsInput::make('actors')
                            ->label('Actors/Models'),
                    ])
                    ->columns(2),
                    
                Forms\Components\Section::make('Publishing')
                    ->schema([
                        Forms\Components\Toggle::make('is_published')
                            ->label('Published'),
                        Forms\Components\Toggle::make('is_featured')
                            ->label('Featured'),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('thumbnail_url')
                    ->label('Thumbnail')
                    ->width(120)
                    ->height(68),
                Tables\Columns\TextColumn::make('title')
                    ->searchable()
                    ->limit(50)
                    ->wrap(),
                Tables\Columns\TextColumn::make('source_site')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'xvideos' => 'danger',
                        'pornhub' => 'warning',
                        'xhamster' => 'success',
                        'xnxx' => 'info',
                        'redtube' => 'danger',
                        'youporn' => 'primary',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('duration_formatted')
                    ->label('Duration'),
                Tables\Columns\TextColumn::make('views_count')
                    ->label('Views')
                    ->formatStateUsing(fn ($state) => number_format($state))
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_published')
                    ->boolean()
                    ->label('Published'),
                Tables\Columns\IconColumn::make('is_featured')
                    ->boolean()
                    ->label('Featured'),
                Tables\Columns\TextColumn::make('imported_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('source_site')
                    ->options([
                        'xvideos' => 'XVideos',
                        'pornhub' => 'PornHub',
                        'xhamster' => 'xHamster',
                        'xnxx' => 'XNXX',
                        'redtube' => 'RedTube',
                        'youporn' => 'YouPorn',
                    ]),
                Tables\Filters\TernaryFilter::make('is_published')
                    ->label('Published'),
                Tables\Filters\TernaryFilter::make('is_featured')
                    ->label('Featured'),
            ])
            ->actions([
                Tables\Actions\Action::make('preview')
                    ->icon('heroicon-o-play')
                    ->url(fn (EmbeddedVideo $record): string => $record->source_url)
                    ->openUrlInNewTab(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('publish')
                        ->label('Publish Selected')
                        ->icon('heroicon-o-check-circle')
                        ->action(fn ($records) => $records->each->update(['is_published' => true]))
                        ->deselectRecordsAfterCompletion(),
                    Tables\Actions\BulkAction::make('unpublish')
                        ->label('Unpublish Selected')
                        ->icon('heroicon-o-x-circle')
                        ->action(fn ($records) => $records->each->update(['is_published' => false]))
                        ->deselectRecordsAfterCompletion(),
                    Tables\Actions\BulkAction::make('feature')
                        ->label('Feature Selected')
                        ->icon('heroicon-o-star')
                        ->action(fn ($records) => $records->each->update(['is_featured' => true]))
                        ->deselectRecordsAfterCompletion(),
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('imported_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListEmbeddedVideos::route('/'),
            'edit' => Pages\EditEmbeddedVideo::route('/{record}/edit'),
        ];
    }
}

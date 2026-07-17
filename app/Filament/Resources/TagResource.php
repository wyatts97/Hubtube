<?php

namespace App\Filament\Resources;

use Filament\Schemas\Schema;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use App\Services\TagSyncService;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Leek\FilamentRightClick\Menu\ContextMenuItem;
use Leek\FilamentRightClick\Menu\ContextMenuSeparator;
use App\Filament\Resources\TagResource\Pages\ListTags;
use App\Filament\Resources\TagResource\Pages\EditTag;
use App\Filament\Resources\TagResource\Pages;
use App\Models\Hashtag;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class TagResource extends Resource
{
    protected static ?string $model = Hashtag::class;

    protected static string | \BackedEnum | null $navigationIcon = 'phosphor-hash';

    protected static ?string $navigationLabel = 'Tags';

    protected static string | \UnitEnum | null $navigationGroup = 'Content';

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'slug'];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->whereHas('videos')
            ->withCount('videos');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(100)
                    ->live(onBlur: true)
                    ->afterStateUpdated(fn ($state, Set $set) => $set('slug', Str::slug((string) $state))),
                TextInput::make('slug')
                    ->required()
                    ->maxLength(100)
                    ->unique(ignoreRecord: true),
                TextInput::make('usage_count')
                    ->numeric()
                    ->disabled()
                    ->dehydrated(false),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('slug')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('videos_count')
                    ->label('Videos')
                    ->sortable(),
                TextColumn::make('usage_count')
                    ->label('Usage')
                    ->sortable(),

                TextColumn::make('updated_at')
                    ->dateTime()
                    ->label('Updated')
                    ->sortable(),
            ])
            ->filters([
                Filter::make('high_usage')
                    ->label('High Usage (10+)')
                    ->query(fn (Builder $query): Builder => $query->where('usage_count', '>=', 10)),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()
                    ->before(function (Hashtag $record): void {
                        app(TagSyncService::class)->deleteTag($record);
                    }),
            ])
            ->contextMenuActions([
                ContextMenuItem::for(EditAction::make('ctxEdit'))
                    ->label('Edit')
                    ->icon('phosphor-pencil-simple'),
                ContextMenuSeparator::make(),
                ContextMenuItem::for(
                    DeleteAction::make('ctxDelete')
                        ->before(function (Hashtag $record): void {
                            app(TagSyncService::class)->deleteTag($record);
                        }),
                )
                    ->label('Delete')
                    ->icon('phosphor-trash')
                    ->color('danger'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->before(function ($records): void {
                            $sync = app(TagSyncService::class);
                            foreach ($records as $record) {
                                $sync->deleteTag($record);
                            }
                        }),
                ]),
            ])
            ->striped();
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTags::route('/'),
            'edit' => EditTag::route('/{record}/edit'),
        ];
    }
}
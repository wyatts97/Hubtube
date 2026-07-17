<?php

namespace App\Filament\Resources;

use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Actions\EditAction;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Leek\FilamentRightClick\Menu\ContextMenuItem;
use App\Filament\Resources\ChannelResource\Pages\ListChannels;
use App\Filament\Resources\ChannelResource\Pages\CreateChannel;
use App\Filament\Resources\ChannelResource\Pages\EditChannel;
use App\Filament\Resources\ChannelResource\Pages;
use App\Models\Channel;
use Illuminate\Database\Eloquent\Model;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ChannelResource extends Resource
{
    protected static bool $shouldRegisterNavigation = false;
    protected static ?string $recordTitleAttribute = 'name';

    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'slug'];
    }

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        return [
            'Subscribers' => number_format($record->subscriber_count ?? 0),
            'Views'       => number_format($record->total_views ?? 0),
        ];
    }
    protected static ?string $model = Channel::class;
    protected static string | \BackedEnum | null $navigationIcon = 'phosphor-television';
    protected static string | \UnitEnum | null $navigationGroup = 'Content';
    protected static ?int $navigationSort = 3;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Channel Information')
                    ->schema([
                        Select::make('user_id')
                            ->relationship('user', 'username')
                            ->required()
                            ->searchable(),
                        TextInput::make('name')
                            ->required()
                            ->maxLength(100),
                        TextInput::make('slug')
                            ->required()
                            ->maxLength(100),
                        Textarea::make('description')
                            ->maxLength(1000)
                            ->columnSpanFull(),
                        TextInput::make('custom_url')
                            ->maxLength(50),
                    ])->columns(2),
                Section::make('Monetization')
                    ->schema([
                        Toggle::make('subscription_enabled')
                            ->label('Enable Paid Subscriptions'),
                        TextInput::make('subscription_price')
                            ->numeric()
                            ->prefix('$'),
                    ])->columns(2),
                Section::make('Status')
                    ->schema([
                        Toggle::make('is_verified')
                            ->label('Verified Channel'),
                        TextInput::make('subscriber_count')
                            ->numeric()
                            ->disabled(),
                        TextInput::make('total_views')
                            ->numeric()
                            ->disabled(),
                    ])->columns(3),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('user.username')
                    ->label('Owner')
                    ->searchable(),

                TextColumn::make('subscriber_count')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('total_views')
                    ->numeric()
                    ->sortable(),

                IconColumn::make('is_verified')
                    ->boolean(),
                IconColumn::make('subscription_enabled')
                    ->boolean(),

                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TernaryFilter::make('is_verified'),
                TernaryFilter::make('subscription_enabled'),
            ])
            ->recordActions([
                EditAction::make(),
                Action::make('verify')
                    ->icon('phosphor-check-circle')
                    ->color('success')
                    ->action(fn (Channel $record) => $record->update(['is_verified' => true]))
                    ->visible(fn (Channel $record) => !$record->is_verified),
            ])
            ->contextMenuActions([
                ContextMenuItem::for(EditAction::make('ctxEdit'))
                    ->label('Edit')
                    ->icon('phosphor-pencil-simple'),
                ContextMenuItem::for(
                    Action::make('ctxVerify')
                        ->icon('phosphor-check-circle')
                        ->color('success')
                        ->action(fn (Channel $record) => $record->update(['is_verified' => true]))
                        ->visible(fn (Channel $record) => !$record->is_verified),
                )
                    ->label('Verify')
                    ->icon('phosphor-check-circle')
                    ->color('success'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->striped();
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListChannels::route('/'),
            'create' => CreateChannel::route('/create'),
            'edit' => EditChannel::route('/{record}/edit'),
        ];
    }
}
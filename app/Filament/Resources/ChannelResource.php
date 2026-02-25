<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ChannelResource\Pages;
use App\Models\Channel;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

use App\Filament\Concerns\HasCustomizableNavigation;

class ChannelResource extends Resource
{
    use HasCustomizableNavigation;
    protected static bool $shouldRegisterNavigation = false;
    protected static ?string $model = Channel::class;
    protected static ?string $navigationIcon = 'heroicon-o-tv';
    protected static ?string $navigationGroup = 'Content';
    protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Channel Information')
                    ->schema([
                        Forms\Components\Select::make('user_id')
                            ->relationship('user', 'username')
                            ->required()
                            ->searchable(),
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(100),
                        Forms\Components\TextInput::make('slug')
                            ->required()
                            ->maxLength(100),
                        Forms\Components\Textarea::make('description')
                            ->maxLength(1000)
                            ->columnSpanFull(),
                        Forms\Components\TextInput::make('custom_url')
                            ->maxLength(50),
                    ])->columns(2),
                Forms\Components\Section::make('Monetization')
                    ->schema([
                        Forms\Components\Toggle::make('subscription_enabled')
                            ->label('Enable Paid Subscriptions'),
                        Forms\Components\TextInput::make('subscription_price')
                            ->numeric()
                            ->prefix('$'),
                    ])->columns(2),
                Forms\Components\Section::make('Status')
                    ->schema([
                        Forms\Components\Toggle::make('is_verified')
                            ->label('Verified Channel'),
                        Forms\Components\TextInput::make('subscriber_count')
                            ->numeric()
                            ->disabled(),
                        Forms\Components\TextInput::make('total_views')
                            ->numeric()
                            ->disabled(),
                    ])->columns(3),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('user.username')
                    ->label('Owner')
                    ->searchable(),
                Tables\Columns\TextColumn::make('subscriber_count')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('total_views')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_verified')
                    ->boolean(),
                Tables\Columns\IconColumn::make('subscription_enabled')
                    ->boolean(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_verified'),
                Tables\Filters\TernaryFilter::make('subscription_enabled'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('verify')
                    ->icon('heroicon-o-check-badge')
                    ->color('success')
                    ->action(fn (Channel $record) => $record->update(['is_verified' => true]))
                    ->visible(fn (Channel $record) => !$record->is_verified),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListChannels::route('/'),
            'create' => Pages\CreateChannel::route('/create'),
            'edit' => Pages\EditChannel::route('/{record}/edit'),
        ];
    }
}

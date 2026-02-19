<?php

namespace App\Filament\Resources;

use App\Filament\Resources\LiveStreamResource\Pages;
use App\Models\LiveStream;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class LiveStreamResource extends Resource
{
    protected static ?string $model = LiveStream::class;
    protected static ?string $navigationIcon = 'heroicon-o-signal';
    protected static ?string $navigationGroup = 'Content';
    protected static ?int $navigationSort = 5;

    public static function shouldRegisterNavigation(): bool
    {
        return (bool) \App\Models\Setting::get('live_streaming_enabled', true);
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Stream Information')
                    ->schema([
                        Forms\Components\Select::make('user_id')
                            ->relationship('user', 'username')
                            ->required()
                            ->searchable(),
                        Forms\Components\TextInput::make('title')
                            ->required()
                            ->maxLength(200),
                        Forms\Components\Textarea::make('description')
                            ->maxLength(2000),
                        Forms\Components\Select::make('status')
                            ->options([
                                'pending' => 'Pending',
                                'live' => 'Live',
                                'ended' => 'Ended',
                            ])
                            ->required(),
                    ])->columns(2),
                Forms\Components\Section::make('Settings')
                    ->schema([
                        Forms\Components\Toggle::make('gifts_enabled')
                            ->label('Allow Gifts'),
                        Forms\Components\Toggle::make('chat_enabled')
                            ->label('Allow Chat'),
                    ])->columns(2),
                Forms\Components\Section::make('Statistics')
                    ->schema([
                        Forms\Components\TextInput::make('viewer_count')
                            ->numeric()
                            ->disabled(),
                        Forms\Components\TextInput::make('peak_viewers')
                            ->numeric()
                            ->disabled(),
                        Forms\Components\TextInput::make('total_gifts_amount')
                            ->numeric()
                            ->prefix('$')
                            ->disabled(),
                    ])->columns(3),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->searchable()
                    ->limit(30),
                Tables\Columns\TextColumn::make('user.username')
                    ->label('Streamer')
                    ->searchable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'pending' => 'warning',
                        'live' => 'success',
                        'ended' => 'gray',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('viewer_count')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('peak_viewers')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('total_gifts_amount')
                    ->money('USD')
                    ->sortable(),
                Tables\Columns\TextColumn::make('started_at')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('ended_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'live' => 'Live',
                        'ended' => 'Ended',
                    ]),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('end')
                    ->icon('heroicon-o-stop')
                    ->color('danger')
                    ->action(fn (LiveStream $record) => $record->end())
                    ->visible(fn (LiveStream $record) => $record->status === 'live')
                    ->requiresConfirmation(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListLiveStreams::route('/'),
            'edit' => Pages\EditLiveStream::route('/{record}/edit'),
        ];
    }
}

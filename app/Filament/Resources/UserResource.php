<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Hash;


class UserResource extends Resource
{
    protected static ?string $model = User::class;
    protected static ?string $navigationIcon = 'heroicon-o-users';
    protected static ?string $navigationGroup = 'Users & Messages';
    protected static ?int $navigationSort = 1;
    protected static ?string $recordTitleAttribute = 'username';

    public static function getGloballySearchableAttributes(): array
    {
        return ['username', 'email'];
    }

    public static function getGlobalSearchResultDetails(\Illuminate\Database\Eloquent\Model $record): array
    {
        return [
            'Email' => $record->email,
            'Role'  => $record->is_admin ? 'Admin' : ($record->is_pro ? 'Pro' : 'User'),
        ];
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('User Information')
                    ->schema([
                        Forms\Components\TextInput::make('username')
                            ->required()
                            ->maxLength(32)
                            ->unique(ignoreRecord: true),
                        Forms\Components\TextInput::make('email')
                            ->email()
                            ->required()
                            ->unique(ignoreRecord: true),
                        Forms\Components\TextInput::make('password')
                            ->password()
                            ->required(fn (string $operation): bool => $operation === 'create')
                            ->dehydrateStateUsing(fn ($state) => $state ? Hash::make($state) : null)
                            ->dehydrated(fn ($state) => filled($state))
                            ->helperText(fn (string $operation): ?string => $operation === 'edit' ? 'Leave blank to keep current password' : null),
                        Forms\Components\TextInput::make('first_name')
                            ->maxLength(50),
                        Forms\Components\TextInput::make('last_name')
                            ->maxLength(50),
                        Forms\Components\Textarea::make('bio')
                            ->rows(3),
                        Forms\Components\Select::make('gender')
                            ->options([
                                'male' => 'Male',
                                'female' => 'Female',
                                'other' => 'Other',
                            ]),
                        Forms\Components\TextInput::make('country')
                            ->maxLength(2),
                    ])->columns(2),

                Forms\Components\Section::make('Account Status')
                    ->schema([
                        Forms\Components\Toggle::make('is_verified')
                            ->label('Verified'),
                        Forms\Components\Toggle::make('is_pro')
                            ->label('Pro User'),
                        Forms\Components\Toggle::make('is_admin')
                            ->label('Administrator'),
                        Forms\Components\TextInput::make('wallet_balance')
                            ->numeric()
                            ->prefix('$')
                            ->disabled(),
                    ])->columns(4),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('avatar')
                    ->circular()
                    ->getStateUsing(function ($record) {
                        $avatar = $record->avatar;
                        if (!$avatar) {
                            return null;
                        }
                        // If it's already a full URL, return as-is
                        if (str_starts_with($avatar, 'http')) {
                            return $avatar;
                        }
                        // If it's a relative path like /storage/..., make it absolute
                        return url($avatar);
                    })
                    ->defaultImageUrl(fn ($record) => 'https://ui-avatars.com/api/?name=' . urlencode($record->username ?? '?') . '&background=6366f1&color=fff&size=80'),
                Tables\Columns\TextColumn::make('username')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('email')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_verified')
                    ->boolean()
                    ->label('Verified'),
                Tables\Columns\IconColumn::make('is_pro')
                    ->boolean()
                    ->label('Pro'),
                Tables\Columns\IconColumn::make('is_admin')
                    ->boolean()
                    ->label('Admin'),
                Tables\Columns\TextColumn::make('videos_count')
                    ->counts('videos')
                    ->label('Videos')
                    ->numeric()
                    ->sortable()
                    ->icon('heroicon-m-video-camera')
                    ->iconColor('gray'),

                Tables\Columns\TextColumn::make('wallet_balance')
                    ->money('USD')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Joined')
                    ->since()
                    ->sortable()
                    ->size('sm')
                    ->color('gray')
                    ->tooltip(fn (User $record): string => $record->created_at?->format('M j, Y g:i A') ?? ''),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Last Active')
                    ->since()
                    ->sortable()
                    ->size('sm')
                    ->color('gray')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_verified'),
                Tables\Filters\TernaryFilter::make('is_pro'),
                Tables\Filters\TernaryFilter::make('is_admin'),
            ])
            ->actions([
                Tables\Actions\Action::make('verify')
                    ->icon('heroicon-o-check-badge')
                    ->color('success')
                    ->requiresConfirmation()
                    ->action(fn (User $record) => $record->forceFill(['is_verified' => true])->save())
                    ->visible(fn (User $record) => !$record->is_verified),
                Tables\Actions\Action::make('unverify')
                    ->icon('heroicon-o-x-circle')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->action(fn (User $record) => $record->forceFill(['is_verified' => false])->save())
                    ->visible(fn (User $record) => $record->is_verified),

                Tables\Actions\Action::make('toggle_pro')
                    ->icon(fn (User $record) => $record->is_pro ? 'heroicon-o-x-circle' : 'heroicon-o-star')
                    ->color(fn (User $record) => $record->is_pro ? 'gray' : 'warning')
                    ->label(fn (User $record) => $record->is_pro ? 'Revoke Pro' : 'Grant Pro')
                    ->requiresConfirmation()
                    ->action(fn (User $record) => $record->forceFill(['is_pro' => !$record->is_pro])->save()),

                Tables\Actions\Action::make('view_videos')
                    ->icon('heroicon-o-video-camera')
                    ->color('info')
                    ->label('Videos')
                    ->url(fn (User $record): string => route('filament.admin.resources.videos.index') . '?tableFilters[user_id][value]=' . $record->id)
                    ->visible(fn (User $record) => $record->videos_count > 0 || true),

                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->striped()
            ->defaultSort('created_at', 'desc')
            ->recordUrl(fn (User $record): string => route('filament.admin.resources.users.edit', $record));
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}

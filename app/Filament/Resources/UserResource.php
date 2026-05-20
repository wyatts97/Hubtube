<?php

namespace App\Filament\Resources;

use Illuminate\Database\Eloquent\Model;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use App\Filament\Resources\UserResource\Pages\ListUsers;
use App\Filament\Resources\UserResource\Pages\CreateUser;
use App\Filament\Resources\UserResource\Pages\EditUser;
use App\Filament\Resources\UserResource\Pages;
use App\Models\User;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Hash;


class UserResource extends Resource
{
    protected static ?string $model = User::class;
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-users';
    protected static string | \UnitEnum | null $navigationGroup = 'Users & Messages';
    protected static ?int $navigationSort = 1;
    protected static ?string $recordTitleAttribute = 'username';

    public static function getGloballySearchableAttributes(): array
    {
        return ['username', 'email'];
    }

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        return [
            'Email' => $record->email,
            'Role'  => $record->is_admin ? 'Admin' : ($record->is_pro ? 'Pro' : 'User'),
        ];
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('User Information')
                    ->schema([
                        TextInput::make('username')
                            ->required()
                            ->maxLength(32)
                            ->unique(ignoreRecord: true),
                        TextInput::make('email')
                            ->email()
                            ->required()
                            ->unique(ignoreRecord: true),
                        TextInput::make('password')
                            ->password()
                            ->required(fn (string $operation): bool => $operation === 'create')
                            ->dehydrateStateUsing(fn ($state) => $state ? Hash::make($state) : null)
                            ->dehydrated(fn ($state) => filled($state))
                            ->helperText(fn (string $operation): ?string => $operation === 'edit' ? 'Leave blank to keep current password' : null),
                        TextInput::make('first_name')
                            ->maxLength(50),
                        TextInput::make('last_name')
                            ->maxLength(50),
                        Textarea::make('bio')
                            ->rows(3),
                        Select::make('gender')
                            ->options([
                                'male' => 'Male',
                                'female' => 'Female',
                                'other' => 'Other',
                            ]),
                        TextInput::make('country')
                            ->maxLength(2),
                    ])->columns(2),

                Section::make('Account Status')
                    ->schema([
                        Toggle::make('is_verified')
                            ->label('Verified'),
                        Toggle::make('is_pro')
                            ->label('Pro User'),
                        Toggle::make('is_admin')
                            ->label('Administrator'),
                        TextInput::make('wallet_balance')
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
                ImageColumn::make('avatar')
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
                TextColumn::make('username')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('email')
                    ->searchable()
                    ->sortable(),
                IconColumn::make('is_verified')
                    ->boolean()
                    ->label('Verified'),
                IconColumn::make('is_pro')
                    ->boolean()
                    ->label('Pro'),
                IconColumn::make('is_admin')
                    ->boolean()
                    ->label('Admin'),
                TextColumn::make('videos_count')
                    ->counts('videos')
                    ->label('Videos')
                    ->numeric()
                    ->sortable()
                    ->icon('heroicon-m-video-camera')
                    ->iconColor('gray'),

                TextColumn::make('wallet_balance')
                    ->money('USD')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('created_at')
                    ->label('Joined')
                    ->since()
                    ->sortable()
                    ->size('sm')
                    ->color('gray')
                    ->tooltip(fn (User $record): string => $record->created_at?->format('M j, Y g:i A') ?? ''),

                TextColumn::make('updated_at')
                    ->label('Last Active')
                    ->since()
                    ->sortable()
                    ->size('sm')
                    ->color('gray')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TernaryFilter::make('is_verified'),
                TernaryFilter::make('is_pro'),
                TernaryFilter::make('is_admin'),
            ])
            ->recordActions([
                Action::make('verify')
                    ->icon('heroicon-o-check-badge')
                    ->color('success')
                    ->requiresConfirmation()
                    ->action(fn (User $record) => $record->forceFill(['is_verified' => true])->save())
                    ->visible(fn (User $record) => !$record->is_verified),
                Action::make('unverify')
                    ->icon('heroicon-o-x-circle')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->action(fn (User $record) => $record->forceFill(['is_verified' => false])->save())
                    ->visible(fn (User $record) => $record->is_verified),

                Action::make('toggle_pro')
                    ->icon(fn (User $record) => $record->is_pro ? 'heroicon-o-x-circle' : 'heroicon-o-star')
                    ->color(fn (User $record) => $record->is_pro ? 'gray' : 'warning')
                    ->label(fn (User $record) => $record->is_pro ? 'Revoke Pro' : 'Grant Pro')
                    ->requiresConfirmation()
                    ->action(fn (User $record) => $record->forceFill(['is_pro' => !$record->is_pro])->save()),

                Action::make('view_videos')
                    ->icon('heroicon-o-video-camera')
                    ->color('info')
                    ->label('Videos')
                    ->url(fn (User $record): string => route('filament.admin.resources.videos.index') . '?tableFilters[user_id][value]=' . $record->id)
                    ->visible(fn (User $record) => $record->videos_count > 0 || true),

                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
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
            'index' => ListUsers::route('/'),
            'create' => CreateUser::route('/create'),
            'edit' => EditUser::route('/{record}/edit'),
        ];
    }
}

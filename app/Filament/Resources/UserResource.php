<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\RequiresSuperAdmin;
use App\Filament\Resources\UserResource\Pages\CreateUser;
use App\Filament\Resources\UserResource\Pages\EditUser;
use App\Filament\Resources\UserResource\Pages\ListUsers;
use App\Models\User;
use App\Services\UserBanService;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use STS\FilamentImpersonate\Actions\Impersonate;

class UserResource extends Resource
{
    use RequiresSuperAdmin;

    protected static ?string $model = User::class;

    protected static string|\BackedEnum|null $navigationIcon = 'phosphor-users';

    protected static string|\UnitEnum|null $navigationGroup = 'Users & Email';

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
            'Role' => $record->is_admin ? 'Admin' : ($record->is_pro ? 'Pro' : 'User'),
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
                        Section::make('Tiers')
                            ->schema([
                                Toggle::make('is_verified')
                                    ->label('Verified'),
                                Toggle::make('is_pro')
                                    ->label('Pro User'),
                                Toggle::make('is_admin')
                                    ->label('Administrator'),
                                Toggle::make('is_super_admin')
                                    ->label('Super Administrator')
                                    ->helperText('Grants access to site, storage, payment and '
                                        .'integration settings, user management and the importer. '
                                        .'Only super administrators can grant this.')
                                    ->disabled(fn () => ! Auth::user()?->isSuperAdmin())
                                    ->dehydrated(fn () => (bool) Auth::user()?->isSuperAdmin()),
                            ])->columns(4)
                            ->columnSpanFull()
                            ->compact(),
                        Select::make('roles')
                            ->label('Roles')
                            ->relationship('roles', 'name')
                            ->multiple()
                            ->preload()
                            ->searchable()
                            ->helperText('Roles narrow what an administrator can reach in the panel, '
                                .'and grant front-end permissions such as skipping the moderation queue. '
                                .'An administrator with no roles keeps full access, except to super-admin areas. '
                                .'Edit what each role may do in System → Roles.')
                            ->columnSpanFull(),
                        TextInput::make('wallet_balance')
                            ->label('Wallet Balance')
                            ->numeric()
                            ->prefix('$')
                            ->disabled()
                            ->columnSpan(1),
                    ])->columns(1),
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
                        if (! $avatar) {
                            return null;
                        }
                        // If it's already a full URL, return as-is
                        if (str_starts_with($avatar, 'http')) {
                            return $avatar;
                        }

                        // If it's a relative path like /storage/..., make it absolute
                        return url($avatar);
                    })
                    ->defaultImageUrl(fn ($record) => 'https://ui-avatars.com/api/?name='.urlencode($record->username ?? '?').'&background=6366f1&color=fff&size=80')
                    ->toggleable(),
                TextColumn::make('username')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('email')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),

                IconColumn::make('is_verified')
                    ->boolean()
                    ->label('Verified')
                    ->toggleable(),
                IconColumn::make('is_pro')
                    ->boolean()
                    ->label('Pro')
                    ->toggleable(),
                IconColumn::make('is_admin')
                    ->boolean()
                    ->label('Admin')
                    ->toggleable(),

                TextColumn::make('videos_count')
                    ->counts('videos')
                    ->label('Videos')
                    ->numeric()
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('wallet_balance')
                    ->money('USD')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('points_balance')
                    ->label('Points')
                    ->numeric()
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('created_at')
                    ->label('Joined')
                    ->since()
                    ->sortable()
                    ->size('sm')
                    ->color('gray')
                    ->tooltip(fn (User $record): string => $record->created_at?->format('M j, Y g:i A') ?? '')
                    ->toggleable(),

                TextColumn::make('updated_at')
                    ->label('Last Active')
                    ->since()
                    ->sortable()
                    ->size('sm')
                    ->color('gray')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('roles.name')
                    ->label('Roles')
                    ->badge()
                    ->separator(',')
                    ->placeholder('—')
                    ->toggleable(),

                TextColumn::make('block_status')
                    ->label('Access')
                    ->badge()
                    ->state(fn (User $record): string => match (true) {
                        $record->isBanned() => 'Banned',
                        $record->isSuspended() => 'Suspended until '.$record->suspended_until->format('j M Y'),
                        default => 'Active',
                    })
                    ->color(fn (User $record): string => match (true) {
                        $record->isBanned() => 'danger',
                        $record->isSuspended() => 'warning',
                        default => 'gray',
                    })
                    ->tooltip(fn (User $record): ?string => $record->ban_reason)
                    ->toggleable(),
            ])
            ->filters([
                TernaryFilter::make('is_verified'),
                TernaryFilter::make('is_pro'),
                TernaryFilter::make('is_admin'),
                TernaryFilter::make('blocked')
                    ->label('Banned or suspended')
                    ->queries(
                        true: fn (Builder $query) => $query->blocked(),
                        false: fn (Builder $query) => $query->whereNull('banned_at')
                            ->where(fn (Builder $q) => $q->whereNull('suspended_until')->orWhere('suspended_until', '<=', now())),
                        blank: fn (Builder $query) => $query,
                    ),
            ])
            ->recordActions([
                Action::make('verify')
                    ->icon('phosphor-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->action(fn (User $record) => $record->forceFill(['is_verified' => true])->save())
                    ->visible(fn (User $record) => ! $record->is_verified),
                Action::make('unverify')
                    ->icon('phosphor-x-circle')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->action(fn (User $record) => $record->forceFill(['is_verified' => false])->save())
                    ->visible(fn (User $record) => $record->is_verified),

                Action::make('toggle_pro')
                    ->icon(fn (User $record) => $record->is_pro ? 'phosphor-x-circle' : 'phosphor-star')
                    ->color(fn (User $record) => $record->is_pro ? 'gray' : 'warning')
                    ->label(fn (User $record) => $record->is_pro ? 'Revoke Pro' : 'Grant Pro')
                    ->requiresConfirmation()
                    ->action(function (User $record) {
                        $granting = ! $record->is_pro;
                        $record->forceFill([
                            'is_pro' => $granting,
                            'pro_source' => $granting ? 'admin' : null,
                            'pro_expires_at' => null,
                        ])->save();
                    }),

                Action::make('ban')
                    ->label('Ban')
                    ->icon('phosphor-prohibit')
                    ->color('danger')
                    ->schema([
                        Textarea::make('reason')
                            ->label('Reason')
                            ->rows(2)
                            ->maxLength(500)
                            ->helperText('Shown to the user when they try to sign in.'),
                    ])
                    ->requiresConfirmation()
                    ->modalDescription('The account can no longer sign in, and any open session ends.')
                    ->action(function (User $record, array $data) {
                        app(UserBanService::class)->ban($record, $data['reason'] ?? null, Auth::user());

                        Notification::make()->title('User banned')->success()->send();
                    })
                    ->visible(fn (User $record) => ! $record->isBanned() && ! $record->is_admin),

                Action::make('suspend')
                    ->label('Suspend')
                    ->icon('phosphor-clock-countdown')
                    ->color('warning')
                    ->schema([
                        DateTimePicker::make('until')
                            ->label('Suspended until')
                            ->native(false)
                            ->minDate(now()->addMinutes(5))
                            ->default(now()->addWeek())
                            ->required(),
                        Textarea::make('reason')
                            ->label('Reason')
                            ->rows(2)
                            ->maxLength(500),
                    ])
                    ->action(function (User $record, array $data) {
                        app(UserBanService::class)->suspend(
                            $record,
                            Carbon::parse($data['until']),
                            $data['reason'] ?? null,
                            Auth::user(),
                        );

                        Notification::make()->title('User suspended')->success()->send();
                    })
                    ->visible(fn (User $record) => ! $record->isBlocked() && ! $record->is_admin),

                Action::make('lift_block')
                    ->label('Lift block')
                    ->icon('phosphor-lock-open')
                    ->color('success')
                    ->requiresConfirmation()
                    ->action(function (User $record) {
                        app(UserBanService::class)->lift($record, Auth::user());

                        Notification::make()->title('Block lifted')->success()->send();
                    })
                    ->visible(fn (User $record) => $record->isBlocked()),

                Action::make('view_videos')
                    ->icon('phosphor-video-camera')
                    ->color('info')
                    ->label('Videos')
                    ->url(fn (User $record): string => route('filament.admin.resources.videos.index').'?tableFilters[user_id][value]='.$record->id)
                    ->visible(fn (User $record) => $record->videos_count > 0 || true),

                // Log in as this user to see the site exactly as they do, without
                // knowing their password. Only visible for non-admin targets
                // (User::canBeImpersonated()) and only usable by admins
                // (User::canImpersonate()) — both enforced by the package itself.
                // Entering/leaving impersonation is logged via the package's
                // EnterImpersonation/LeaveImpersonation events (see
                // AppServiceProvider::boot()).
                Impersonate::make()
                    ->color('warning')
                    ->requiresConfirmation()
                    ->redirectTo('/'),

                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('ban')
                        ->label('Ban')
                        ->icon('phosphor-prohibit')
                        ->color('danger')
                        ->schema([
                            Textarea::make('reason')->label('Reason')->rows(2)->maxLength(500),
                        ])
                        ->requiresConfirmation()
                        ->action(function (Collection $records, array $data) {
                            $service = app(UserBanService::class);
                            $banned = $records->reject->is_admin
                                ->each(fn (User $user) => $service->ban($user, $data['reason'] ?? null, Auth::user()))
                                ->count();

                            Notification::make()->title("Banned {$banned} user(s)")->success()->send();
                        })
                        ->deselectRecordsAfterCompletion(),

                    BulkAction::make('lift_block')
                        ->label('Lift block')
                        ->icon('phosphor-lock-open')
                        ->color('success')
                        ->requiresConfirmation()
                        ->action(function (Collection $records) {
                            $service = app(UserBanService::class);
                            $records->each(fn (User $user) => $service->lift($user, Auth::user()));

                            Notification::make()->title('Blocks lifted')->success()->send();
                        })
                        ->deselectRecordsAfterCompletion(),

                    DeleteBulkAction::make(),
                ]),
            ])
            ->striped()
            ->defaultSort('created_at', 'desc')
            ->recordUrl(fn (User $record): string => route('filament.admin.resources.users.edit', $record))
            ->emptyStateIcon('phosphor-users')
            ->emptyStateHeading('No users yet')
            ->emptyStateDescription('Registered accounts appear here for role, verification, and Pro management.')
            ->emptyStateActions([
                Action::make('create')
                    ->color('success')
                    ->label('Add User')
                    ->icon('phosphor-plus')
                    ->url(static::getUrl('create'))
                    ->button(),
            ]);
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

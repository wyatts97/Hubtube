<?php

namespace App\Filament\Pages;

use App\Models\PointsRedemption;
use App\Models\PointsTransaction;
use App\Models\Setting;
use App\Models\User;
use App\Services\AdminLogger;
use App\Services\PointsService;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class PointsSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|\BackedEnum|null $navigationIcon = 'phosphor-star';

    protected static ?string $navigationLabel = 'Reward Points';

    protected static string|\UnitEnum|null $navigationGroup = 'Monetization';

    protected static ?int $navigationSort = 5;

    protected string $view = 'filament.pages.points-settings';

    public ?array $data = [];
    public ?array $adjustData = [];

    public function mount(): void
    {
        $this->form->fill([
            'points_enabled' => Setting::get('points_enabled', true),
            'points_video_upload_enabled' => Setting::get('points_video_upload_enabled', true),
            'points_per_video_upload' => Setting::get('points_per_video_upload', 100),
            'points_image_upload_enabled' => Setting::get('points_image_upload_enabled', true),
            'points_per_image_upload' => Setting::get('points_per_image_upload', 25),
            'points_comment_enabled' => Setting::get('points_comment_enabled', true),
            'points_per_comment' => Setting::get('points_per_comment', 5),
            'points_comment_daily_cap' => Setting::get('points_comment_daily_cap', 50),
            'points_redemption_enabled' => Setting::get('points_redemption_enabled', true),
            'points_per_redemption_cost' => Setting::get('points_per_redemption_cost', 3000),
            'points_pro_grant_days' => Setting::get('points_pro_grant_days', 30),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Reward Points System')
                    ->description('Reward users with points for contributing content. Points can be redeemed for temporary Ad-Free Pro access.')
                    ->schema([
                        Toggle::make('points_enabled')
                            ->label('Enable Reward Points System')
                            ->helperText('Master kill switch for the entire points feature')
                            ->live(),
                    ]),

                Section::make('Earning Points')
                    ->description('Configure how many points users earn for each action. Each method can be independently enabled or disabled.')
                    ->visible(fn ($get) => $get('points_enabled'))
                    ->schema([
                        Grid::make(2)->schema([
                            Toggle::make('points_video_upload_enabled')
                                ->label('Video Uploads')
                                ->helperText('Award points when a video is approved via moderation')
                                ->live(),
                            TextInput::make('points_per_video_upload')
                                ->label('Points Per Approved Video')
                                ->numeric()
                                ->minValue(0)
                                ->default(100)
                                ->visible(fn ($get) => $get('points_video_upload_enabled')),
                        ]),

                        Grid::make(2)->schema([
                            Toggle::make('points_image_upload_enabled')
                                ->label('Image Uploads')
                                ->helperText('Award points when an image is approved via moderation')
                                ->live(),
                            TextInput::make('points_per_image_upload')
                                ->label('Points Per Approved Image')
                                ->numeric()
                                ->minValue(0)
                                ->default(25)
                                ->visible(fn ($get) => $get('points_image_upload_enabled')),
                        ]),

                        Grid::make(2)->schema([
                            Toggle::make('points_comment_enabled')
                                ->label('Comments')
                                ->helperText('Award points for approved comments')
                                ->live(),
                            TextInput::make('points_per_comment')
                                ->label('Points Per Comment')
                                ->numeric()
                                ->minValue(0)
                                ->default(5)
                                ->visible(fn ($get) => $get('points_comment_enabled')),
                        ]),

                        TextInput::make('points_comment_daily_cap')
                            ->label('Comment Daily Cap (points)')
                            ->helperText('Maximum points a user can earn from comments per day. 0 = unlimited.')
                            ->numeric()
                            ->minValue(0)
                            ->default(50)
                            ->visible(fn ($get) => $get('points_enabled') && $get('points_comment_enabled')),
                    ]),

                Section::make('Redemption — Ad-Free Pro')
                    ->description('Users can redeem accumulated points for temporary Pro membership (ad-free experience + all Pro perks).')
                    ->visible(fn ($get) => $get('points_enabled'))
                    ->schema([
                        Toggle::make('points_redemption_enabled')
                            ->label('Enable Redemption')
                            ->helperText('Allow users to spend points. Disable to let users keep accumulating without spending.')
                            ->live(),
                        Grid::make(2)->schema([
                            TextInput::make('points_per_redemption_cost')
                                ->label('Points Cost Per Redemption')
                                ->helperText('How many points one redemption costs')
                                ->numeric()
                                ->minValue(1)
                                ->default(3000)
                                ->visible(fn ($get) => $get('points_redemption_enabled')),
                            TextInput::make('points_pro_grant_days')
                                ->label('Days of Pro Granted')
                                ->helperText('How many days of Ad-Free Pro each redemption grants')
                                ->numeric()
                                ->minValue(1)
                                ->default(30)
                                ->visible(fn ($get) => $get('points_redemption_enabled')),
                        ]),
                    ]),
            ])
            ->statePath('data');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('save')
                ->label('Save Settings')
                ->action('save'),
        ];
    }

    protected function getForms(): array
    {
        return [
            'form',
            'adjustForm',
        ];
    }

    public function adjustForm(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('user_id')
                    ->label('User')
                    ->searchable()
                    ->getSearchResultsUsing(fn (string $search): array =>
                        User::where('username', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%")
                            ->limit(20)
                            ->get()
                            ->mapWithKeys(fn (User $u) => [$u->id => "{$u->username} ({$u->points_balance} pts)"])
                            ->toArray()
                    )
                    ->getOptionLabelUsing(fn ($value): ?string =>
                        User::find($value)?->username
                    )
                    ->native(false)
                    ->live()
                    ->required(),
                TextInput::make('points')
                    ->label('Points (negative to deduct)')
                    ->numeric()
                    ->required(),
                TextInput::make('reason')
                    ->label('Reason')
                    ->helperText('Shown to user in their points history')
                    ->required(),
            ])
            ->statePath('adjustData');
    }

    public function adjustPoints(): void
    {
        $data = $this->adjustForm->getState();
        $user = User::find($data['user_id']);
        $points = (int) $data['points'];

        if (!$user) {
            Notification::make()->title('User not found.')->danger()->send();
            return;
        }

        if ($points === 0) {
            Notification::make()->title('Points cannot be zero.')->danger()->send();
            return;
        }

        $service = app(PointsService::class);

        if ($points > 0) {
            $service->award($user, PointsTransaction::TYPE_ADMIN_ADJUSTMENT, $points, null, "Admin adjustment: {$data['reason']}");
        } else {
            try {
                $service->spend($user, abs($points), PointsTransaction::TYPE_ADMIN_ADJUSTMENT, "Admin adjustment: {$data['reason']}");
            } catch (\Exception $e) {
                Notification::make()->title($e->getMessage())->danger()->send();
                return;
            }
        }

        AdminLogger::log('Adjusted ' . number_format($points) . ' points for user ' . $user->username . ': ' . $data['reason']);

        Notification::make()
            ->title('Points adjusted successfully for ' . $user->username)
            ->success()
            ->send();

        $this->adjustForm->fill();
    }

    public function save(): void
    {
        $data = $this->form->getState();

        foreach ($data as $key => $value) {
            $type = match (true) {
                is_bool($value) => 'boolean',
                is_int($value) => 'integer',
                default => 'string',
            };
            Setting::set($key, $value, 'points', $type);
        }

        AdminLogger::settingsSaved('Points', array_keys($data));

        Notification::make()
            ->title('Points settings saved successfully')
            ->success()
            ->send();
    }

    /**
     * Live stats for the blade view (program usage overview).
     */
    public function getStatsProperty(): array
    {
        return [
            'total_earned' => (int) PointsTransaction::where('points', '>', 0)->sum('points'),
            'total_redeemed' => (int) abs(PointsTransaction::where('type', PointsTransaction::TYPE_REDEMPTION)->sum('points')),
            'active_points_pro' => User::where('pro_source', 'points')
                ->whereNotNull('pro_expires_at')
                ->where('pro_expires_at', '>', now())
                ->count(),
            'total_redemptions' => PointsRedemption::count(),
        ];
    }
}

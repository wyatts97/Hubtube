<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\Analytics\CategoryViewsChartWidget;
use App\Filament\Widgets\Analytics\RevenueChartWidget;
use App\Filament\Widgets\Analytics\SignupsChartWidget;
use App\Filament\Widgets\Analytics\UploadsChartWidget;
use App\Models\Setting;
use App\Models\User;
use App\Models\Video;
use App\Models\VideoAd;
use App\Services\AdminLogger;
use BezhanSalleh\GoogleAnalytics\Widgets as GaWidgets;
use Filament\Actions\Action;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Spatie\Analytics\Facades\Analytics as AnalyticsFacade;
use Spatie\Analytics\Period;

class Analytics extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string | \BackedEnum | null $navigationIcon  = 'phosphor-chart-bar';
    protected static ?string $navigationLabel = 'Analytics';
    protected static string | \UnitEnum | null $navigationGroup = 'Overview';
    protected static ?int    $navigationSort  = 2;
    protected string  $view            = 'filament.pages.analytics';

    public ?array $data = [];
    public string $activeTab = 'local';

    public function mount(): void
    {
        $this->form->fill([
            'google_analytics_enabled' => (bool) Setting::get('google_analytics_enabled', false),
            'google_analytics_property_id' => Setting::get('google_analytics_property_id', ''),
            'google_analytics_service_account_json' => Setting::getDecrypted('google_analytics_service_account_json', ''),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Google Analytics Configuration')
                    ->description('Connect your GA4 property to display the analytics widgets.')
                    ->icon('phosphor-globe')
                    ->schema([
                        Toggle::make('google_analytics_enabled')
                            ->label('Enable Google Analytics')
                            ->reactive()
                            ->helperText('When enabled, the Google Analytics widgets will be shown on the Google Analytics tab.'),

                        TextInput::make('google_analytics_property_id')
                            ->label('GA4 Property ID')
                            ->placeholder('123456789')
                            ->helperText('Enter the numeric property ID from GA4 admin settings (not the G-XXXX measurement ID).')
                            ->visible(fn ($get) => $get('google_analytics_enabled'))
                            ->required(fn ($get) => $get('google_analytics_enabled'))
                            ->regex('/^\d+$/')
                            ->validationMessages([
                                'regex' => 'The property ID must be numeric, e.g. 123456789. Do not use the G-XXXX measurement ID.',
                            ]),

                        Textarea::make('google_analytics_service_account_json')
                            ->label('Service Account JSON Key')
                            ->placeholder('Paste the full JSON contents from your Google service account key...')
                            ->helperText('Create a service account in Google Cloud Console, enable the Google Analytics Data API, and paste the JSON key here.')
                            ->rows(10)
                            ->visible(fn ($get) => $get('google_analytics_enabled'))
                            ->required(fn ($get) => $get('google_analytics_enabled'))
                            ->rules(['json']),

                        Placeholder::make('ga_instructions')
                            ->content('Share your GA4 property with the service account email listed in the JSON key. The property ID should be the numeric ID shown in GA4 admin settings.')
                            ->columnSpanFull(),
                    ])
                    ->collapsible()
                    ->columnSpanFull(),
            ])
            ->statePath('data');
    }

    /**
     * Widgets are rendered manually inside the page content so the tabs
     * can sit at the top of the page instead of below the header widgets.
     */
    public function getHeaderWidgets(): array
    {
        return [];
    }

    public function getHeaderWidgetsColumns(): int|array
    {
        return 2;
    }

    public function getLocalWidgets(): array
    {
        $widgets = [];

        if (class_exists(UploadsChartWidget::class)) {
            $widgets[] = UploadsChartWidget::class;
            $widgets[] = SignupsChartWidget::class;
            $widgets[] = CategoryViewsChartWidget::class;
            $widgets[] = RevenueChartWidget::class;
        }

        return $widgets;
    }

    public function getGoogleWidgets(): array
    {
        // Only render widgets when the integration is enabled and the last
        // connection test succeeded. This prevents 500 errors from widgets
        // when the API credentials or permissions are invalid.
        if (! (bool) Setting::get('google_analytics_enabled', false)) {
            return [];
        }

        if (Setting::get('google_analytics_last_test_status', '') !== 'success') {
            return [];
        }

        return [
            GaWidgets\PageViewsWidget::class,
            GaWidgets\VisitorsWidget::class,
            GaWidgets\ActiveUsersOneDayWidget::class,
            GaWidgets\ActiveUsersSevenDayWidget::class,
            GaWidgets\ActiveUsersTwentyEightDayWidget::class,
            GaWidgets\SessionsWidget::class,
            GaWidgets\SessionsByCountryWidget::class,
            GaWidgets\SessionsDurationWidget::class,
            GaWidgets\SessionsByDeviceWidget::class,
            GaWidgets\MostVisitedPagesWidget::class,
            GaWidgets\TopReferrersListWidget::class,
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('save')
                ->label('Save Settings')
                ->icon('phosphor-check')
                ->action('save')
                ->visible(fn () => $this->activeTab === 'google'),
        ];
    }

    public function updatedActiveTab(): void
    {
        // Livewire will re-render the page content automatically, so the
        // widgets for the active tab will swap in without a full page load.
    }

    public function save(): void
    {
        $data = $this->form->getState();
        $enabled = (bool) $data['google_analytics_enabled'];

        Setting::set('google_analytics_enabled', $enabled ? '1' : '0', 'analytics', 'boolean');
        Setting::set('google_analytics_property_id', $data['google_analytics_property_id'] ?? '', 'analytics', 'string');
        Setting::setEncrypted('google_analytics_service_account_json', $data['google_analytics_service_account_json'] ?? '', 'analytics');

        // Re-apply config immediately so the user can test the widgets.
        $this->applyGoogleAnalyticsConfig();

        AdminLogger::settingsSaved('Google Analytics', [
            'google_analytics_enabled',
            'google_analytics_property_id',
            'google_analytics_service_account_json',
        ]);

        if ($enabled) {
            try {
                // Make a lightweight API call to surface configuration/permission errors.
                AnalyticsFacade::fetchTotalVisitorsAndPageViews(Period::days(1), 1);

                Setting::set('google_analytics_last_test_status', 'success', 'analytics', 'string');
                Setting::set('google_analytics_last_test_message', '', 'analytics', 'string');
                Setting::set('google_analytics_last_test_at', now()->toDateTimeString(), 'analytics', 'string');

                Notification::make()
                    ->title('Google Analytics connected successfully')
                    ->success()
                    ->send();

                return;
            } catch (\Throwable $e) {
                report($e);

                Setting::set('google_analytics_last_test_status', 'error', 'analytics', 'string');
                Setting::set('google_analytics_last_test_message', $e->getMessage(), 'analytics', 'string');
                Setting::set('google_analytics_last_test_at', now()->toDateTimeString(), 'analytics', 'string');

                Notification::make()
                    ->title('Google Analytics connection failed')
                    ->body($e->getMessage())
                    ->danger()
                    ->persistent()
                    ->send();

                return;
            }
        }

        Setting::set('google_analytics_last_test_status', '', 'analytics', 'string');
        Setting::set('google_analytics_last_test_message', '', 'analytics', 'string');

        Notification::make()
            ->title('Google Analytics settings saved')
            ->success()
            ->send();
    }

    protected function applyGoogleAnalyticsConfig(): void
    {
        $enabled = (bool) Setting::get('google_analytics_enabled', false);
        $propertyId = Setting::get('google_analytics_property_id', '');
        $json = Setting::getDecrypted('google_analytics_service_account_json', '');

        $credentials = [];
        if ($enabled && !empty($json)) {
            $decoded = json_decode($json, true);
            if (is_array($decoded)) {
                $credentials = $decoded;
            }
        }

        config([
            'analytics.property_id' => $enabled ? $propertyId : '',
            'analytics.service_account_credentials_json' => $credentials,
        ]);
    }

    public function getSummaryStats(): array
    {
        return [
            'total_videos'      => Video::count(),
            'total_users'       => User::count(),
            'total_views'       => Video::sum('views_count'),
            'videos_this_week'  => Video::where('created_at', '>=', now()->subWeek())->count(),
            'users_this_week'   => User::where('created_at', '>=', now()->subWeek())->count(),
            'total_impressions' => VideoAd::sum('impressions_count'),
            'total_clicks'      => VideoAd::sum('clicks_count'),
        ];
    }

    public function getAdPerformance(): array
    {
        return VideoAd::active()
            ->select('id', 'name', 'placement', 'type', 'impressions_count', 'clicks_count')
            ->orderByDesc('impressions_count')
            ->limit(20)
            ->get()
            ->map(function ($ad) {
                $ctr = $ad->impressions_count > 0
                    ? round(($ad->clicks_count / $ad->impressions_count) * 100, 2)
                    : 0;
                return [
                    'id'          => $ad->id,
                    'name'        => $ad->name,
                    'placement'   => $ad->placement,
                    'type'        => $ad->type,
                    'impressions' => $ad->impressions_count,
                    'clicks'      => $ad->clicks_count,
                    'ctr'         => $ctr,
                ];
            })
            ->toArray();
    }
}

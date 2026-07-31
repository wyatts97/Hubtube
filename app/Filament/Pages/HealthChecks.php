<?php

namespace App\Filament\Pages;

use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Artisan;
use Spatie\Health\ResultStores\EloquentHealthResultStore;

class HealthChecks extends Page
{
    protected static string | \BackedEnum | null $navigationIcon = 'phosphor-heartbeat';
    protected static ?string $navigationLabel = 'Health Checks';
    protected static string | \UnitEnum | null $navigationGroup = 'Tools';
    protected static ?int $navigationSort = 91;

    protected string $view = 'filament.pages.health-checks';

    public bool $running = false;
    public array $checkResults = [];
    public string $overallStatus = 'unknown';

    public function mount(): void
    {
        $this->loadResults();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('run')
                ->label('Run Health Checks')
                ->icon('phosphor-pulse')
                ->action(function () {
                    $this->running = true;

                    try {
                        Artisan::call('health:check');
                        Notification::make()
                            ->title('Health checks completed')
                            ->success()
                            ->send();
                    } catch (\Throwable $e) {
                        Notification::make()
                            ->title('Health check failed')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    } finally {
                        $this->running = false;
                    }

                    $this->loadResults();
                })
                ->disabled(fn () => $this->running),
        ];
    }

    public function loadResults(): void
    {
        try {
            $store = app(EloquentHealthResultStore::class);
            $latestResults = $store->latestResults();

            if (!$latestResults) {
                $this->checkResults = [];
                $this->overallStatus = 'unknown';
                return;
            }

            $checks = [];
            $overall = 'ok';

            foreach ($latestResults->storedCheckResults as $result) {
                $statusValue = $result->status->value;
                $checks[] = [
                    'name' => $result->checkName,
                    'label' => $result->checkLabel ?? $result->checkName,
                    'status' => $statusValue,
                    'message' => $result->notificationMessage ?? $result->shortSummary ?? '',
                    'last_checked' => $result->lastChecked?->diffForHumans() ?? '—',
                ];

                if (in_array($statusValue, ['failed', 'crashed', 'error'])) {
                    $overall = 'failed';
                } elseif ($statusValue === 'warning' && $overall !== 'failed') {
                    $overall = 'warning';
                }
            }

            $this->checkResults = $checks;
            $this->overallStatus = $overall;
        } catch (\Throwable $e) {
            $this->checkResults = [];
            $this->overallStatus = 'unknown';
        }
    }
}

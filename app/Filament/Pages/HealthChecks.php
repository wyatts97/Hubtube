<?php

namespace App\Filament\Pages;

use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Artisan;
use Livewire\Attributes\Computed;

class HealthChecks extends Page
{
    protected static string | \BackedEnum | null $navigationIcon = 'phosphor-heartbeat';
    protected static ?string $navigationLabel = 'Health Checks';
    protected static string | \UnitEnum | null $navigationGroup = 'Tools';
    protected static ?int $navigationSort = 91;

    protected string $view = 'filament.pages.health-checks';

    public bool $running = false;

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
                })
                ->disabled(fn () => $this->running),
        ];
    }

    #[Computed]
    public function checkResults(): array
    {
        try {
            $results = \Spatie\Health\ResultStores\EloquentHealthResultStore::class;
            $store = app($results);

            $latestResults = $store->latestResults();

            if (!$latestResults) {
                return [];
            }

            $checks = [];
            foreach ($latestResults->storedCheckResults as $result) {
                $checks[] = [
                    'name' => $result->checkName,
                    'label' => $result->checkLabel ?? $result->checkName,
                    'status' => $result->status->value,
                    'message' => $result->notificationMessage ?? $result->shortSummary ?? '',
                    'last_checked' => $result->lastChecked?->diffForHumans() ?? '—',
                ];
            }

            return $checks;
        } catch (\Throwable $e) {
            return [];
        }
    }

    #[Computed]
    public function overallStatus(): string
    {
        $results = $this->checkResults;

        if (empty($results)) {
            return 'unknown';
        }

        foreach ($results as $result) {
            if (in_array($result['status'], ['failed', 'crashed', 'error'])) {
                return 'failed';
            }
        }

        foreach ($results as $result) {
            if ($result['status'] === 'warning') {
                return 'warning';
            }
        }

        return 'ok';
    }
}

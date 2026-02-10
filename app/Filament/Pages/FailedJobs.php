<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

class FailedJobs extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-exclamation-triangle';
    protected static ?string $navigationLabel = 'Failed Jobs';
    protected static ?string $navigationGroup = 'System';
    protected static ?int $navigationSort = 99;
    protected static string $view = 'filament.pages.failed-jobs';

    public static function shouldRegisterNavigation(): bool
    {
        try {
            return DB::table('failed_jobs')->count() > 0;
        } catch (\Exception $e) {
            return false;
        }
    }

    public function getFailedJobsProperty(): \Illuminate\Support\Collection
    {
        try {
            return DB::table('failed_jobs')
                ->orderByDesc('failed_at')
                ->get()
                ->map(function ($job) {
                    $payload = json_decode($job->payload, true);
                    $command = isset($payload['data']['command'])
                        ? unserialize($payload['data']['command'])
                        : null;

                    $jobClass = $payload['displayName'] ?? 'Unknown';
                    $shortClass = class_basename($jobClass);

                    return (object) [
                        'id' => $job->id,
                        'uuid' => $job->uuid,
                        'queue' => $job->queue,
                        'job_class' => $shortClass,
                        'full_class' => $jobClass,
                        'failed_at' => $job->failed_at,
                        'exception' => $job->exception,
                        'exception_short' => \Illuminate\Support\Str::limit($job->exception, 300),
                    ];
                });
        } catch (\Exception $e) {
            return collect();
        }
    }

    public function retryJob(string $uuid): void
    {
        Artisan::call('queue:retry', ['id' => [$uuid]]);

        Notification::make()
            ->title('Job queued for retry')
            ->success()
            ->send();
    }

    public function deleteJob(int $id): void
    {
        DB::table('failed_jobs')->where('id', $id)->delete();

        Notification::make()
            ->title('Failed job deleted')
            ->success()
            ->send();
    }

    public function retryAll(): void
    {
        Artisan::call('queue:retry', ['id' => ['all']]);

        Notification::make()
            ->title('All failed jobs queued for retry')
            ->success()
            ->send();
    }

    public function flushAll(): void
    {
        Artisan::call('queue:flush');

        Notification::make()
            ->title('All failed jobs deleted')
            ->success()
            ->send();
    }
}

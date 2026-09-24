<?php

use App\Filament\Pages\Backups;
use App\Filament\Widgets\Analytics\SignupsChartWidget;
use App\Filament\Widgets\StatsOverview;
use App\Jobs\RunBackupCommandJob;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

test('creating a backup from the panel queues it instead of running inline', function () {
    Queue::fake();
    asAdmin();

    Livewire::test(Backups::class)->callAction('create');

    Queue::assertPushed(RunBackupCommandJob::class, fn ($job) => $job->command === 'backup:run');
});

test('the backup job reports back to the admin who started it', function () {
    $admin = asAdmin();
    Artisan::shouldReceive('call')->once()->with('backup:clean')->andReturn(0);
    Artisan::shouldReceive('output')->andReturn('done');

    (new RunBackupCommandJob('backup:clean', $admin->id))->handle();

    expect($admin->notifications()->count())->toBe(1);
});

test('dashboard widgets do not poll', function () {
    foreach ([StatsOverview::class, SignupsChartWidget::class] as $widget) {
        $method = new ReflectionMethod($widget, 'getPollingInterval');
        expect($method->invoke(new $widget))->toBeNull();
    }
});

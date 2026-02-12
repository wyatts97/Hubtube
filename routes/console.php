<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('horizon:snapshot')->everyFiveMinutes();
Schedule::command('queue:prune-batches --hours=48')->daily();
Schedule::command('sanctum:prune-expired --hours=24')->daily();
Schedule::command('videos:prune-deleted --days=30')->daily();
Schedule::command('storage:cleanup')->daily();
Schedule::command('uploads:cleanup-chunks --hours=24')->daily();

<?php

use App\Models\Setting;
use App\Services\Translation\TranslationSchedule;
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
Schedule::command('videos:prune-bulk-temp')->dailyAt('03:15')->withoutOverlapping();

// Publish scheduled videos every minute so they go live on time
Schedule::command('videos:publish-scheduled')->everyMinute();

// Revoke expired points-granted Pro access
Schedule::command('points:expire-pro')->hourly();

// Safety net for image alt text. The observers in AppServiceProvider fill the
// *_alt_text columns on save, so this normally finds nothing — it exists to
// catch rows written around Eloquent (bulk inserts, direct DB updates, an
// import) and rows that had no title yet when they were first saved.
Schedule::command('seo:backfill-alt-text')
    ->dailyAt('04:30')
    ->withoutOverlapping()
    ->onOneServer();

// Spatie Backup: nightly backup + weekly cleanup (skipped when backup_enabled setting is off)
Schedule::command('backup:run')->dailyAt('01:00')->withoutOverlapping()
    ->skip(fn () => !Setting::get('backup_enabled', true));
Schedule::command('backup:clean')->weekly()->sundays()->at('02:00')
    ->skip(fn () => !Setting::get('backup_enabled', true));

// Scheduled content translation. Registered every minute with a lazily
// evaluated gate: Schedule::cron() needs its expression as a string at
// registration time, which would mean a DB read on every console bootstrap.
Schedule::command('translations:run')
    ->everyMinute()
    ->withoutOverlapping(120)
    ->onOneServer()
    ->runInBackground()
    ->skip(fn () => ! TranslationSchedule::isDueNow());

// Spatie Health: run checks every 10 minutes
Schedule::command('health:check')->everyTenMinutes();
// Heartbeat backing QueueCheck — proves the worker is draining jobs, which is
// what surfaces a stalled background-translation pipeline.
Schedule::command('health:queue-check-heartbeat')->everyMinute();
// Heartbeat backing ScheduleCheck. Without it that check can never pass, since
// it only reports whether this command has run recently.
Schedule::command('health:schedule-check-heartbeat')->everyMinute();
Schedule::command('model:prune', ['--model' => [\Spatie\Health\Models\HealthCheckResultHistoryItem::class]])->daily();

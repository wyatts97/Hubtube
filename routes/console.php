<?php

use App\Models\Setting;
use App\Models\VideoView;
use App\Services\Translation\TranslationSchedule;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use Spatie\Health\Models\HealthCheckResultHistoryItem;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('horizon:snapshot')->everyFiveMinutes();
Schedule::command('queue:prune-batches --hours=48')->daily();

// failed_jobs is never pruned by default, so one old failure sticks around
// forever. 168h matches filament-jobs-monitor's configured 7-day retention so
// the two failure tables age out together instead of drifting apart.
Schedule::command('queue:prune-failed --hours=168')->daily();

// pruning.retention_days is set in config/filament-jobs-monitor.php but nothing
// ever ran the command, so queue_monitors grew without bound — it gets a row
// per job run, and the scheduler below fires jobs every minute.
Schedule::command('filament-jobs-monitor:prune')->daily();
Schedule::command('sanctum:prune-expired --hours=24')->daily();
Schedule::command('videos:prune-deleted --days=30')->daily();
Schedule::command('storage:cleanup')->daily();
Schedule::command('uploads:cleanup-chunks --hours=24')->daily();
Schedule::command('videos:prune-bulk-temp')->dailyAt('03:15')->withoutOverlapping();

// Publish scheduled videos every minute so they go live on time
Schedule::command('videos:publish-scheduled')->everyMinute();

// Media Library index. The page keeps the index in step with its own actions,
// but files also arrive from the encoder, from imports and from the shell, so
// the incremental pass picks those up. It only re-reads directories whose mtime
// has moved, which is cheap — but a directory's mtime does not change when a
// file's *contents* change, hence the weekly full pass. --prune stats every
// indexed row, so it belongs in the weekly run rather than the ten-minute one.
Schedule::command('media:index')
    ->everyTenMinutes()
    ->withoutOverlapping()
    ->onOneServer();
Schedule::command('media:index --full --prune')
    ->weeklyOn(0, '03:40')
    ->withoutOverlapping()
    ->onOneServer();

// Thumbnails for files nobody has browsed to yet. The page queues whatever
// folder you open, so this is the trickle that eventually covers the rest —
// limited per run so a first pass over an existing library spreads out instead
// of flooding the queue. The prune sweep lists the whole thumbnail directory,
// hence weekly.
Schedule::command('media:thumbnails --limit=500')
    ->hourly()
    ->withoutOverlapping()
    ->onOneServer();
Schedule::command('media:thumbnails --limit=0 --prune')
    ->weeklyOn(0, '04:05')
    ->withoutOverlapping()
    ->onOneServer();

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
    ->skip(fn () => ! Setting::get('backup_enabled', true));
Schedule::command('backup:clean')->weekly()->sundays()->at('02:00')
    ->skip(fn () => ! Setting::get('backup_enabled', true));

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
Schedule::command('model:prune', ['--model' => [HealthCheckResultHistoryItem::class]])->daily();
// Raw per-view rows; the running total on videos.views_count is unaffected.
Schedule::command('model:prune', ['--model' => [VideoView::class]])->dailyAt('04:10');

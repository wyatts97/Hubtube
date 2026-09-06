<?php

use App\Models\Report;
use App\Models\User;
use App\Models\Video;
use App\Services\SystemStatusBar;
use Croustibat\FilamentJobsMonitor\Models\FailureGroup;
use Illuminate\Support\Facades\DB;

/*
|--------------------------------------------------------------------------
| Admin topbar status bar
|--------------------------------------------------------------------------
|
| The failures pill used to count rows in Laravel's `failed_jobs` while
| linking at the jobs-monitor index, which lists the unrelated `queue_monitors`
| table. Nothing in that page deletes failed_jobs rows and nothing pruned them,
| so one old failure left a permanent red badge pointing at an empty page, with
| no way to clear it from the UI.
|
| It now counts unresolved FailureGroups and links to the failures page, whose
| default tab runs the identical query — so the number and the page it opens
| cannot disagree.
|
*/

/** Resolve a status bar that has not memoised anything yet. */
function statusBar(): SystemStatusBar
{
    app()->forgetScopedInstances();

    return app(SystemStatusBar::class);
}

function itemNamed(string $key): ?array
{
    foreach (statusBar()->getActionItems() as $item) {
        if ($item['key'] === $key) {
            return $item;
        }
    }

    return null;
}

test('the failures pill counts unresolved failure groups', function () {
    FailureGroup::create([
        'signature' => 'sig-open-1',
        'exception_class' => 'RuntimeException',
        'message' => 'Transcode blew up',
        'occurrences_count' => 1,
    ]);

    FailureGroup::create([
        'signature' => 'sig-resolved-1',
        'exception_class' => 'RuntimeException',
        'message' => 'Already dealt with',
        'occurrences_count' => 1,
        'resolved_at' => now(),
    ]);

    expect(itemNamed('failures')['count'])->toBe(1);
});

test('the failures pill links at the page that shows the same rows', function () {
    asAdmin();

    $item = itemNamed('failures');

    // The regression: this used to resolve to the jobs-monitor index, which
    // renders `queue_monitors` rather than the failures the badge counted.
    expect($item['url'])->toContain('/failures');
});

test('the failures count is not taken from failed_jobs', function () {
    // A stale failed_jobs row with no matching failure group is exactly the
    // situation that produced a permanently stuck badge.
    DB::table('failed_jobs')->insert([
        'uuid' => (string) Str::uuid(),
        'connection' => 'redis',
        'queue' => 'video-processing',
        'payload' => '{}',
        'exception' => 'stale',
        'failed_at' => now(),
    ]);

    expect(DB::table('failed_jobs')->count())->toBe(1);
    expect(itemNamed('failures')['count'])->toBe(0);
});

test('the pill is named for job failures, not logs', function () {
    $item = itemNamed('failures');

    expect($item)->not->toBeNull();
    expect($item['shortLabel'])->toBe('Failures');
    expect($item['icon'])->toBe('phosphor-bug');
    expect(itemNamed('logs'))->toBeNull();
});

test('every pill is rendered even at zero so the strip stays stable', function () {
    $items = statusBar()->getActionItems();

    expect($items)->not->toBeEmpty();
    // The view mutes a zero rather than dropping it; colour is what changes.
    foreach ($items as $item) {
        expect($item['count'])->toBeInt();
    }
});

test('counts are resolved once per request rather than per render hook', function () {
    $bar = statusBar();

    $bar->getActionItems();

    $queries = 0;
    DB::listen(function () use (&$queries) {
        $queries++;
    });

    // The second call is what the mobile render hook makes.
    $bar->getActionItems();

    expect($queries)->toBe(0);
});

test('saving a moderated model busts the cached counts', function () {
    $user = User::factory()->create();

    expect(itemNamed('reports')['count'])->toBe(0);

    $video = Video::factory()->for($user)->create();

    Report::create([
        'user_id' => $user->id,
        'reportable_type' => $video->getMorphClass(),
        'reportable_id' => $video->id,
        'reason' => Report::REASON_SPAM,
    ]);

    // Without the flush hook this would still read 0 until the TTL expired.
    expect(itemNamed('reports')['count'])->toBe(1);
});

test('both the desktop strip and the phone chip render into the topbar', function () {
    asAdmin();

    $html = $this->get('/admin')->assertStatus(200)->getContent();

    // Filament hides .fi-topbar-start below 1024px, so the desktop strip alone
    // left mobile with no status bar at all. Both variants ship; CSS picks one.
    expect($html)->toContain('ht-topbar-pills');
    expect($html)->toContain('ht-topbar-mobile__chip');
});

test('failed job tables are pruned on a schedule', function () {
    $commands = collect(app(\Illuminate\Console\Scheduling\Schedule::class)->events())
        ->map(fn ($event) => $event->command ?? '');

    // failed_jobs has no default pruning, which is why a single old failure
    // could sit there indefinitely.
    expect($commands->contains(fn ($c) => str_contains($c, 'queue:prune-failed')))->toBeTrue();

    // pruning.retention_days was configured but the command was never run.
    expect($commands->contains(fn ($c) => str_contains($c, 'filament-jobs-monitor:prune')))->toBeTrue();
});

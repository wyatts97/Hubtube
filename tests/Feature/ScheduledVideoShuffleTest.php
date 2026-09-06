<?php

use App\Filament\Pages\ScheduledVideos;
use App\Models\Setting;
use App\Models\User;
use App\Models\Video;
use Livewire\Livewire;

/*
|--------------------------------------------------------------------------
| Scheduled video queue shuffle
|--------------------------------------------------------------------------
|
| Order and publish time live in two different columns. `queue_order` is what
| the admin table sorts on, but PublishScheduledVideos selects on
| `scheduled_at` — so renumbering alone would reshuffle the page and change
| nothing about what actually publishes.
|
| VideoService::recalculateScheduleQueue() is the bridge: it walks videos in
| `queue_order` and rewrites both the order and `scheduled_at` from the
| configured posts-per-day and start hour. These tests pin that the shuffle
| goes through it, so the new order reaches the publisher.
|
*/

/** Seed N pending scheduled videos in a known order. */
function seedQueue(int $count = 8): void
{
    $user = User::factory()->create();

    foreach (range(1, $count) as $i) {
        Video::factory()->for($user)->create([
            'title' => "Queued {$i}",
            'queue_order' => $i,
            'scheduled_at' => now()->addHours($i),
            'published_at' => null,
        ]);
    }
}

function pendingQueue()
{
    return Video::whereNotNull('queue_order')
        ->whereNull('published_at')
        ->orderBy('queue_order')
        ->get();
}

test('shuffling reorders the pending queue', function () {
    asAdmin();
    seedQueue(12);

    $before = pendingQueue()->pluck('title')->all();

    Livewire::test(ScheduledVideos::class)->callAction('shuffle');

    $after = pendingQueue()->pluck('title')->all();

    // Same videos, different order. With 12 items a false failure from an
    // identity permutation is 1 in 479 million.
    expect($after)->not->toBe($before);
    expect(collect($after)->sort()->values()->all())
        ->toBe(collect($before)->sort()->values()->all());
});

test('shuffling leaves queue_order as a contiguous 1..N with no gaps', function () {
    asAdmin();
    seedQueue(10);

    Livewire::test(ScheduledVideos::class)->callAction('shuffle');

    expect(pendingQueue()->pluck('queue_order')->all())->toBe(range(1, 10));
});

test('shuffling reassigns publish times so they follow the new order', function () {
    asAdmin();
    Setting::set('schedule_posts_per_day', 4, 'general', 'integer');
    Setting::set('schedule_start_hour', '08:00:00');
    seedQueue(6);

    Livewire::test(ScheduledVideos::class)->callAction('shuffle');

    $queue = pendingQueue();

    // This is the point of the feature: scheduled_at must ascend in lockstep
    // with queue_order, otherwise the publisher ignores the shuffle entirely.
    $times = $queue->pluck('scheduled_at')->map(fn ($t) => $t?->timestamp)->all();
    expect($times)->not->toContain(null);
    expect($times)->toBe(collect($times)->sort()->values()->all());

    // 4 posts per day is a 6-hour interval.
    for ($i = 1; $i < count($times); $i++) {
        expect($times[$i] - $times[$i - 1])->toBe(6 * 3600);
    }
});

test('shuffling never touches already published videos', function () {
    asAdmin();
    $user = User::factory()->create();

    $published = Video::factory()->for($user)->create([
        'queue_order' => null,
        'scheduled_at' => null,
        'published_at' => now()->subDay(),
    ]);

    seedQueue(5);

    Livewire::test(ScheduledVideos::class)->callAction('shuffle');

    $published->refresh();
    expect($published->queue_order)->toBeNull();
    expect($published->published_at)->not->toBeNull();
});

test('shuffling an empty queue is a no-op rather than an error', function () {
    asAdmin();

    Livewire::test(ScheduledVideos::class)
        ->callAction('shuffle')
        ->assertHasNoErrors();

    expect(pendingQueue())->toBeEmpty();
});

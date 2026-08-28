<?php

use App\Models\Playlist;
use App\Models\Subscription;
use App\Models\User;
use App\Models\Video;

/*
|--------------------------------------------------------------------------
| Subscription activity feed
|--------------------------------------------------------------------------
|
| /feed used to be a flat offset-paginated grid of subscribed channels'
| videos. It is now activity — uploads grouped per creator plus new public
| playlists — on a keyset cursor.
|
*/

function subscribeTo(User $subscriber, User $channel): void
{
    Subscription::create(['subscriber_id' => $subscriber->id, 'channel_id' => $channel->id]);
}

test('the feed shows uploads from subscribed channels only', function () {
    $viewer = asUser();
    $followed = User::factory()->create();
    $stranger = User::factory()->create();
    subscribeTo($viewer, $followed);

    Video::factory()->create(['user_id' => $followed->id, 'title' => 'Followed Upload', 'published_at' => now()]);
    Video::factory()->create(['user_id' => $stranger->id, 'title' => 'Stranger Upload', 'published_at' => now()]);

    $this->get('/feed')
        ->assertOk()
        ->assertSee('Followed Upload')
        ->assertDontSee('Stranger Upload');
});

test('consecutive uploads by one creator collapse into a single entry', function () {
    $viewer = asUser();
    $followed = User::factory()->create();
    subscribeTo($viewer, $followed);

    Video::factory()->count(4)->create([
        'user_id' => $followed->id,
        'published_at' => now(),
    ]);

    $this->get('/feed')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            // One entry carrying four videos, not four entries.
            ->has('activity', 1)
            ->has('activity.0.videos', 4)
            ->etc());
});

test('two creators do not collapse into one entry', function () {
    $viewer = asUser();
    $a = User::factory()->create();
    $b = User::factory()->create();
    subscribeTo($viewer, $a);
    subscribeTo($viewer, $b);

    Video::factory()->create(['user_id' => $a->id, 'published_at' => now()]);
    Video::factory()->create(['user_id' => $b->id, 'published_at' => now()->subMinute()]);

    $this->get('/feed')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('activity', 2)->etc());
});

test('new public playlists appear in the feed', function () {
    $viewer = asUser();
    $followed = User::factory()->create();
    subscribeTo($viewer, $followed);

    Playlist::factory()->create([
        'user_id' => $followed->id,
        'title' => 'A Public Mix',
        'privacy' => 'public',
        'is_default' => false,
    ]);

    $this->get('/feed')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('activity.0.type', 'playlist')
            ->where('activity.0.subject.title', 'A Public Mix')
            ->etc());
});

test('private playlists and unpublished videos stay out of the feed', function () {
    $viewer = asUser();
    $followed = User::factory()->create();
    subscribeTo($viewer, $followed);

    Playlist::factory()->create([
        'user_id' => $followed->id, 'title' => 'Secret Mix', 'privacy' => 'private', 'is_default' => false,
    ]);
    Video::factory()->private()->create(['user_id' => $followed->id, 'title' => 'Private Upload', 'published_at' => now()]);
    Video::factory()->unapproved()->create(['user_id' => $followed->id, 'title' => 'Pending Upload', 'published_at' => now()]);

    $this->get('/feed')
        ->assertOk()
        ->assertDontSee('Secret Mix')
        ->assertDontSee('Private Upload')
        ->assertDontSee('Pending Upload')
        ->assertInertia(fn ($page) => $page->has('activity', 0)->etc());
});

test('the cursor pages backwards through the feed without repeating', function () {
    $viewer = asUser();
    $followed = User::factory()->create();
    subscribeTo($viewer, $followed);

    // Distinct creators so grouping does not merge everything into one entry.
    foreach (range(1, 30) as $i) {
        $creator = User::factory()->create();
        subscribeTo($viewer, $creator);
        Video::factory()->create([
            'user_id' => $creator->id,
            'title' => "Upload {$i}",
            'published_at' => now()->subMinutes($i),
        ]);
    }

    $first = $this->get('/feed')->assertOk();
    $firstPage = $first->viewData('page')['props'];

    expect($firstPage['nextCursor'])->not->toBeNull();

    $second = $this->getJson('/api/feed?cursor='.urlencode($firstPage['nextCursor']))->assertOk();

    $firstTitles = collect($firstPage['activity'])->flatMap(fn ($e) => collect($e['videos'] ?? [])->pluck('title'));
    $secondTitles = collect($second->json('activity'))->flatMap(fn ($e) => collect($e['videos'] ?? [])->pluck('title'));

    expect($secondTitles)->not->toBeEmpty();
    expect($firstTitles->intersect($secondTitles))->toBeEmpty();
});

test('a malformed cursor serves the first page instead of erroring', function () {
    $viewer = asUser();
    $followed = User::factory()->create();
    subscribeTo($viewer, $followed);
    Video::factory()->create(['user_id' => $followed->id, 'published_at' => now()]);

    $this->getJson('/api/feed?cursor=not-a-date')
        ->assertOk()
        ->assertJsonCount(1, 'activity');
});

test('the feed distinguishes no subscriptions from no activity', function () {
    $viewer = asUser();

    $this->get('/feed')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('hasSubscriptions', false)->etc());

    subscribeTo($viewer, User::factory()->create());

    $this->get('/feed')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('hasSubscriptions', true)->etc());
});

test('guests cannot reach the feed', function () {
    $this->get('/feed')->assertRedirect('/login');
});

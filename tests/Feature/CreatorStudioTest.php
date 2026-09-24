<?php

use App\Models\Category;
use App\Models\Hashtag;
use App\Models\Setting;
use App\Models\User;
use App\Models\Video;
use App\Models\VideoView;
use App\Models\WatchHistory;
use App\Services\VideoAnalytics;

/*
|--------------------------------------------------------------------------
| Creator Studio — the video manager and per-video analytics
|--------------------------------------------------------------------------
|
| Before this, a creator's only list of their own videos was the ten most
| recent on the dashboard. These cover the manager's filters and bulk actions
| and the analytics built on video_views and watch_history.
|
*/

// ── Access ──────────────────────────────────────────────────────────────────

test('the studio needs a signed-in account', function () {
    $this->get('/studio/videos')->assertRedirect('/login');
});

test('the studio lists only your own videos', function () {
    $user = asUser();
    Video::factory()->create(['user_id' => $user->id, 'title' => 'Mine']);
    Video::factory()->create(['title' => 'Somebody Else\'s']);

    $page = inertiaPagePayload($this->get('/studio/videos'));
    $titles = collect($page['props']['videos']['data'])->pluck('title');

    expect($page['component'])->toBe('Studio/Videos')
        ->and($titles)->toContain('Mine')
        ->and($titles)->not->toContain('Somebody Else\'s');
});

test('/studio redirects to the video manager', function () {
    asUser();

    $this->get('/studio')->assertRedirect('/studio/videos');
});

// ── Filtering ───────────────────────────────────────────────────────────────

test('videos can be filtered by their studio status', function () {
    $user = asUser();

    $published = Video::factory()->create(['user_id' => $user->id, 'title' => 'Live One']);
    $draft = Video::factory()->create(['user_id' => $user->id, 'title' => 'Draft One', 'is_draft' => true]);
    $processing = Video::factory()->create(['user_id' => $user->id, 'title' => 'Encoding One', 'status' => 'processing']);
    $review = Video::factory()->unapproved()->create(['user_id' => $user->id, 'title' => 'Review One', 'status' => 'processed']);

    $titlesFor = function (string $status) {
        $page = inertiaPagePayload($this->get("/studio/videos?status={$status}"));

        return collect($page['props']['videos']['data'])->pluck('title')->all();
    };

    expect($titlesFor('published'))->toBe(['Live One'])
        ->and($titlesFor('draft'))->toBe(['Draft One'])
        ->and($titlesFor('processing'))->toBe(['Encoding One'])
        ->and($titlesFor('review'))->toBe(['Review One']);
});

test('a scheduled video is told apart from a plain draft', function () {
    $user = asUser();
    Video::factory()->create(['user_id' => $user->id, 'title' => 'Waiting', 'is_draft' => true, 'scheduled_at' => now()->addDay()]);
    Video::factory()->create(['user_id' => $user->id, 'title' => 'Parked', 'is_draft' => true]);

    $page = inertiaPagePayload($this->get('/studio/videos?status=scheduled'));

    expect(collect($page['props']['videos']['data'])->pluck('title')->all())->toBe(['Waiting']);
});

test('videos can be searched by title and filtered by privacy and category', function () {
    $user = asUser();
    $category = Category::factory()->create();

    Video::factory()->create(['user_id' => $user->id, 'title' => 'Sunset Timelapse', 'category_id' => $category->id]);
    Video::factory()->unlisted()->create(['user_id' => $user->id, 'title' => 'Sunrise Timelapse']);
    Video::factory()->create(['user_id' => $user->id, 'title' => 'Unrelated Clip']);

    $page = inertiaPagePayload($this->get('/studio/videos?q=Timelapse'));
    expect(collect($page['props']['videos']['data'])->pluck('title'))->toHaveCount(2);

    $page = inertiaPagePayload($this->get('/studio/videos?privacy=unlisted'));
    expect(collect($page['props']['videos']['data'])->pluck('title')->all())->toBe(['Sunrise Timelapse']);

    $page = inertiaPagePayload($this->get("/studio/videos?category={$category->id}"));
    expect(collect($page['props']['videos']['data'])->pluck('title')->all())->toBe(['Sunset Timelapse']);
});

test('the status counts describe the creator s own library', function () {
    $user = asUser();
    Video::factory()->count(2)->create(['user_id' => $user->id]);
    Video::factory()->create(['user_id' => $user->id, 'is_draft' => true]);
    Video::factory()->count(3)->create();

    $page = inertiaPagePayload($this->get('/studio/videos'));

    expect($page['props']['counts']['all'])->toBe(3)
        ->and($page['props']['counts']['published'])->toBe(2)
        ->and($page['props']['counts']['draft'])->toBe(1);
});

// ── Bulk actions ────────────────────────────────────────────────────────────

test('a bulk privacy change applies to the selected videos', function () {
    $user = asUser();
    Setting::set('allow_private_uploads', true, 'general', 'boolean');

    $videos = Video::factory()->count(3)->create(['user_id' => $user->id, 'privacy' => 'public']);

    $this->post('/studio/videos/bulk', [
        'action' => 'privacy',
        'privacy' => 'private',
        'video_ids' => [$videos[0]->id, $videos[1]->id],
    ])->assertRedirect();

    expect($videos[0]->fresh()->privacy)->toBe('private')
        ->and($videos[1]->fresh()->privacy)->toBe('private')
        ->and($videos[2]->fresh()->privacy)->toBe('public');
});

test('a bulk privacy change cannot use a privacy the admin switched off', function () {
    $user = asUser();
    $video = Video::factory()->create(['user_id' => $user->id]);

    // Both unlisted and private uploads are off by default.
    $this->post('/studio/videos/bulk', [
        'action' => 'privacy',
        'privacy' => 'private',
        'video_ids' => [$video->id],
    ])->assertSessionHasErrors('privacy');
});

test('a bulk category change applies', function () {
    // Category and tag edits need full edit rights (Pro).
    $user = asUser(User::factory()->pro()->create());
    $category = Category::factory()->create();
    $video = Video::factory()->create(['user_id' => $user->id, 'category_id' => null]);

    $this->post('/studio/videos/bulk', [
        'action' => 'category',
        'category_id' => $category->id,
        'video_ids' => [$video->id],
    ])->assertRedirect();

    expect($video->fresh()->category_id)->toBe($category->id);
});

test('bulk tagging adds to the tags a video already has', function () {
    // Category and tag edits need full edit rights (Pro).
    $user = asUser(User::factory()->pro()->create());
    Hashtag::factory()->create(['name' => 'sunset', 'slug' => 'sunset']);
    Hashtag::factory()->create(['name' => 'timelapse', 'slug' => 'timelapse']);

    $video = Video::factory()->create(['user_id' => $user->id, 'tags' => ['sunset']]);

    $this->post('/studio/videos/bulk', [
        'action' => 'tags',
        'tags' => ['timelapse'],
        'video_ids' => [$video->id],
    ])->assertRedirect();

    expect($video->fresh()->tags)->toContain('sunset')->toContain('timelapse');
});

test('bulk tagging is held to the existing tag vocabulary', function () {
    $user = asUser();
    $video = Video::factory()->create(['user_id' => $user->id]);

    $this->post('/studio/videos/bulk', [
        'action' => 'tags',
        'tags' => ['a-tag-nobody-has-used'],
        'video_ids' => [$video->id],
    ])->assertSessionHasErrors('tags');
});

test('a bulk delete removes the selected videos', function () {
    $user = asUser();
    $videos = Video::factory()->count(2)->create(['user_id' => $user->id]);

    $this->post('/studio/videos/bulk', [
        'action' => 'delete',
        'video_ids' => $videos->pluck('id')->all(),
    ])->assertRedirect();

    $this->assertSoftDeleted('videos', ['id' => $videos[0]->id]);
    $this->assertSoftDeleted('videos', ['id' => $videos[1]->id]);
});

test('a bulk action cannot reach somebody else s videos', function () {
    asUser();
    $theirs = Video::factory()->create(['privacy' => 'public']);

    $this->post('/studio/videos/bulk', [
        'action' => 'delete',
        'video_ids' => [$theirs->id],
    ])->assertRedirect();

    $this->assertDatabaseHas('videos', ['id' => $theirs->id, 'deleted_at' => null]);
});

test('an unknown bulk action is refused', function () {
    $user = asUser();
    $video = Video::factory()->create(['user_id' => $user->id]);

    $this->post('/studio/videos/bulk', [
        'action' => 'publish-everything',
        'video_ids' => [$video->id],
    ])->assertSessionHasErrors('action');
});

// ── Analytics ───────────────────────────────────────────────────────────────

test('analytics are for the video owner alone', function () {
    $owner = User::factory()->create();
    $video = Video::factory()->create(['user_id' => $owner->id]);

    asUser();
    $this->get("/studio/videos/{$video->id}/analytics")->assertForbidden();

    asUser($owner);
    $this->get("/studio/videos/{$video->id}/analytics")->assertOk();
});

test('an admin can read anyone s analytics', function () {
    $video = Video::factory()->create();
    asAdmin();

    $this->get("/studio/videos/{$video->id}/analytics")->assertOk();
});

test('the daily series covers every day in the range, gaps included', function () {
    $owner = asUser();
    $video = Video::factory()->create(['user_id' => $owner->id]);

    VideoView::factory()->count(3)->create(['video_id' => $video->id, 'created_at' => now()]);
    VideoView::factory()->create(['video_id' => $video->id, 'created_at' => now()->subDays(3)]);

    $page = inertiaPagePayload($this->get("/studio/videos/{$video->id}/analytics?days=7"));
    $series = $page['props']['analytics']['views_by_day'];

    expect($series)->toHaveCount(7)
        ->and(collect($series)->last()['views'])->toBe(3)
        ->and(collect($series)->sum('views'))->toBe(4)
        // The empty days are present rather than skipped.
        ->and(collect($series)->where('views', 0)->count())->toBe(5);
});

test('views outside the range are left out', function () {
    $owner = asUser();
    $video = Video::factory()->create(['user_id' => $owner->id]);

    VideoView::factory()->create(['video_id' => $video->id, 'created_at' => now()]);
    VideoView::factory()->create(['video_id' => $video->id, 'created_at' => now()->subDays(40)]);

    $page = inertiaPagePayload($this->get("/studio/videos/{$video->id}/analytics?days=7"));

    expect($page['props']['analytics']['totals']['views_in_range'])->toBe(1);
});

test('an unsupported range falls back to the default', function () {
    $owner = asUser();
    $video = Video::factory()->create(['user_id' => $owner->id]);

    $page = inertiaPagePayload($this->get("/studio/videos/{$video->id}/analytics?days=9999"));

    expect($page['props']['analytics']['range_days'])->toBe(VideoAnalytics::DEFAULT_RANGE);
});

test('countries and referrers are grouped, and a referrer is reduced to its host', function () {
    $owner = asUser();
    $video = Video::factory()->create(['user_id' => $owner->id]);

    VideoView::factory()->count(2)->create([
        'video_id' => $video->id,
        'country' => 'DE',
        'referrer' => 'https://www.reddit.com/r/videos/abc',
        'created_at' => now(),
    ]);
    VideoView::factory()->create([
        'video_id' => $video->id,
        'country' => 'US',
        'referrer' => null,
        'created_at' => now(),
    ]);

    $analytics = app(VideoAnalytics::class)->for($video->fresh(), 7);

    expect($analytics['countries'][0])->toBe(['country' => 'DE', 'views' => 2])
        ->and(collect($analytics['sources'])->pluck('views', 'source')->all())
        ->toBe(['reddit.com' => 2, 'direct' => 1]);
});

test('unique viewers count a guest by address and a member by account', function () {
    $owner = asUser();
    $video = Video::factory()->create(['user_id' => $owner->id]);
    $member = User::factory()->create();

    // Same member twice, same guest address twice, one other guest.
    VideoView::factory()->count(2)->create(['video_id' => $video->id, 'user_id' => $member->id, 'ip_address' => null, 'created_at' => now()]);
    VideoView::factory()->count(2)->create(['video_id' => $video->id, 'user_id' => null, 'ip_address' => '203.0.113.5', 'created_at' => now()]);
    VideoView::factory()->create(['video_id' => $video->id, 'user_id' => null, 'ip_address' => '203.0.113.9', 'created_at' => now()]);

    $analytics = app(VideoAnalytics::class)->for($video->fresh(), 7);

    expect($analytics['totals']['views_in_range'])->toBe(5)
        ->and($analytics['totals']['viewers_in_range'])->toBe(3)
        ->and($analytics['totals']['signed_in_views_in_range'])->toBe(2);
});

test('watch time comes from the watch history and reports an average', function () {
    $owner = asUser();
    $video = Video::factory()->create(['user_id' => $owner->id, 'duration' => 100]);

    WatchHistory::create(['user_id' => User::factory()->create()->id, 'video_id' => $video->id, 'watched_seconds' => 100, 'completed' => true]);
    WatchHistory::create(['user_id' => User::factory()->create()->id, 'video_id' => $video->id, 'watched_seconds' => 40, 'completed' => false]);

    $engagement = app(VideoAnalytics::class)->for($video->fresh(), 7)['engagement'];

    expect($engagement['tracked_viewers'])->toBe(2)
        ->and($engagement['total_watch_seconds'])->toBe(140)
        ->and($engagement['average_watch_seconds'])->toBe(70)
        ->and($engagement['average_watched_percent'])->toBe(70.0)
        ->and($engagement['completion_rate'])->toBe(50.0);
});

test('a video of unknown length reports no watched percentage rather than zero', function () {
    $owner = asUser();
    $video = Video::factory()->create(['user_id' => $owner->id, 'duration' => 0]);

    WatchHistory::create(['user_id' => User::factory()->create()->id, 'video_id' => $video->id, 'watched_seconds' => 30]);

    $engagement = app(VideoAnalytics::class)->for($video->fresh(), 7)['engagement'];

    expect($engagement['average_watched_percent'])->toBeNull()
        ->and($engagement['average_watch_seconds'])->toBe(30);
});

test('another video s views never show up in these numbers', function () {
    $owner = asUser();
    $video = Video::factory()->create(['user_id' => $owner->id]);
    $other = Video::factory()->create(['user_id' => $owner->id]);

    VideoView::factory()->count(4)->create(['video_id' => $other->id, 'created_at' => now()]);

    $analytics = app(VideoAnalytics::class)->for($video->fresh(), 7);

    expect($analytics['totals']['views_in_range'])->toBe(0);
});

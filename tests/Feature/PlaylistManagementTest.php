<?php

use App\Models\Playlist;
use App\Models\User;
use App\Models\Video;

/*
|--------------------------------------------------------------------------
| Playlists — privacy, ordering and Watch Later
|--------------------------------------------------------------------------
|
| The playlists table always had a privacy column and an is_default flag, but
| nothing wrote either: every playlist was created public, and the public
| listing showed all of them. These cover the finished behaviour.
|
*/

// ── Privacy ─────────────────────────────────────────────────────────────────

test('a playlist is created with the privacy the owner picked', function () {
    $user = asUser();

    $this->postJson('/playlists', [
        'title' => 'Private Picks',
        'privacy' => 'private',
    ])->assertCreated();

    expect(Playlist::where('user_id', $user->id)->first()->privacy)->toBe('private');
});

test('an unknown privacy value is refused', function () {
    asUser();

    $this->postJson('/playlists', ['title' => 'Nope', 'privacy' => 'secret'])
        ->assertStatus(422);
});

test('a private playlist is invisible to everyone but its owner', function () {
    $owner = User::factory()->create();
    $playlist = Playlist::factory()->create(['user_id' => $owner->id, 'privacy' => 'private']);

    $this->get("/playlist/{$playlist->slug}")->assertNotFound();

    asUser();
    $this->get("/playlist/{$playlist->slug}")->assertNotFound();

    asUser($owner);
    $this->get("/playlist/{$playlist->slug}")->assertOk();
});

test('an unlisted playlist opens for anyone with the link', function () {
    $playlist = Playlist::factory()->create(['privacy' => 'unlisted']);

    $this->get("/playlist/{$playlist->slug}")->assertOk();
});

test('the public listing shows public playlists only', function () {
    $public = Playlist::factory()->create(['privacy' => 'public', 'video_count' => 2, 'title' => 'Open List']);
    $unlisted = Playlist::factory()->create(['privacy' => 'unlisted', 'video_count' => 2, 'title' => 'Unlisted List']);
    $private = Playlist::factory()->create(['privacy' => 'private', 'video_count' => 2, 'title' => 'Private List']);

    $page = inertiaPagePayload($this->get('/public-playlists'));
    $titles = collect($page['props']['playlists']['data'])->pluck('title');

    expect($titles)->toContain('Open List')
        ->not->toContain('Unlisted List')
        ->not->toContain('Private List');
});

test('the owner can change a playlist privacy later', function () {
    $user = asUser();
    $playlist = Playlist::factory()->create(['user_id' => $user->id, 'privacy' => 'public']);

    $this->putJson("/playlists/{$playlist->id}", [
        'title' => $playlist->title,
        'privacy' => 'private',
    ])->assertOk();

    expect($playlist->fresh()->privacy)->toBe('private');
});

// ── Ordering ────────────────────────────────────────────────────────────────

test('the owner can reorder a playlist', function () {
    $user = asUser();
    $playlist = Playlist::factory()->create(['user_id' => $user->id]);
    $videos = Video::factory()->count(3)->create();
    $videos->each(fn ($video) => $playlist->addVideo($video));

    $reversed = $videos->pluck('id')->reverse()->values();

    $this->putJson("/playlists/{$playlist->id}/order", ['video_ids' => $reversed->all()])
        ->assertOk();

    expect($playlist->fresh()->videos()->pluck('videos.id')->all())->toBe($reversed->all());
});

test('a reorder cannot add or drop videos', function () {
    $user = asUser();
    $playlist = Playlist::factory()->create(['user_id' => $user->id]);
    $videos = Video::factory()->count(3)->create();
    $videos->each(fn ($video) => $playlist->addVideo($video));
    $outsider = Video::factory()->create();

    // Only the last video is named, and a video from outside the playlist is
    // thrown in for good measure.
    $this->putJson("/playlists/{$playlist->id}/order", [
        'video_ids' => [$videos[2]->id, $outsider->id],
    ])->assertOk();

    $order = $playlist->fresh()->videos()->pluck('videos.id')->all();

    expect($order)->toHaveCount(3)
        ->and($order[0])->toBe($videos[2]->id)
        ->and($order)->not->toContain($outsider->id);
});

test('a stranger cannot reorder someone else s playlist', function () {
    $playlist = Playlist::factory()->create();
    $video = Video::factory()->create();
    $playlist->addVideo($video);

    asUser();

    $this->putJson("/playlists/{$playlist->id}/order", ['video_ids' => [$video->id]])
        ->assertStatus(403);
});

// ── Removing videos ─────────────────────────────────────────────────────────

test('removing a video twice does not drive the count below zero', function () {
    $user = asUser();
    $playlist = Playlist::factory()->create(['user_id' => $user->id]);
    $video = Video::factory()->create();
    $playlist->addVideo($video);

    $this->deleteJson("/playlists/{$playlist->id}/videos", ['video_id' => $video->id])->assertOk();
    $this->deleteJson("/playlists/{$playlist->id}/videos", ['video_id' => $video->id])->assertOk();

    expect($playlist->fresh()->video_count)->toBe(0);
});

test('removing the video a playlist took its thumbnail from picks another', function () {
    $user = asUser();
    $playlist = Playlist::factory()->create(['user_id' => $user->id, 'thumbnail' => null]);
    $first = Video::factory()->create();
    $second = Video::factory()->create();

    $playlist->addVideo($first);
    $playlist->addVideo($second);
    expect($playlist->fresh()->thumbnail)->toBe($first->thumbnail_url);

    $playlist->refresh()->removeVideo($first);

    expect($playlist->fresh()->thumbnail)->toBe($second->thumbnail_url);
});

// ── Watch Later ─────────────────────────────────────────────────────────────

test('visiting the playlists page creates Watch Later once', function () {
    $user = asUser();

    $this->get('/playlists')->assertOk();
    $this->get('/playlists')->assertOk();

    $defaults = Playlist::where('user_id', $user->id)->where('is_default', true)->get();

    expect($defaults)->toHaveCount(1)
        ->and($defaults->first()->title)->toBe(Playlist::WATCH_LATER)
        // A list of things you mean to watch is nobody else's business.
        ->and($defaults->first()->privacy)->toBe('private');
});

test('a video can be saved to Watch Later and taken back off', function () {
    $user = asUser();
    $video = Video::factory()->create();

    $saved = $this->postJson("/videos/{$video->id}/watch-later")->assertOk();
    expect($saved->json('saved'))->toBeTrue()
        ->and($saved->json('video_count'))->toBe(1);

    $removed = $this->postJson("/videos/{$video->id}/watch-later")->assertOk();
    expect($removed->json('saved'))->toBeFalse()
        ->and($removed->json('video_count'))->toBe(0);
});

test('Watch Later cannot be deleted or renamed', function () {
    $user = asUser();
    $playlist = Playlist::watchLaterFor($user);

    $this->delete("/playlists/{$playlist->id}")->assertStatus(403);

    $this->putJson("/playlists/{$playlist->id}", [
        'title' => 'Something Else',
        'privacy' => 'public',
    ])->assertOk();

    $playlist->refresh();

    expect($playlist->title)->toBe(Playlist::WATCH_LATER)
        // Its privacy is still the owner's to change.
        ->and($playlist->privacy)->toBe('public');
});

test('a video nobody may watch cannot be saved to Watch Later', function () {
    asUser();
    $video = Video::factory()->private()->create();

    $this->postJson("/videos/{$video->id}/watch-later")->assertStatus(403);
});

test('the watch page always offers Watch Later in the save menu', function () {
    asUser();
    $video = Video::factory()->create();

    $page = inertiaPagePayload($this->get("/{$video->slug}"));
    $playlists = collect($page['props']['userPlaylists']);

    expect($playlists->firstWhere('is_default', true))->not->toBeNull()
        ->and($playlists->first()['title'])->toBe(Playlist::WATCH_LATER);
});

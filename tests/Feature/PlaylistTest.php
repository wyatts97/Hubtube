<?php

use App\Models\Playlist;
use App\Models\Video;

/*
|--------------------------------------------------------------------------
| Playlists — CRUD, Add/Remove Videos
|--------------------------------------------------------------------------
*/

test('guest cannot access playlists page', function () {
    $this->get('/playlists')->assertRedirect('/login');
});

test('authenticated user can access playlists page', function () {
    asUser();
    $this->get('/playlists')->assertStatus(200);
});

test('authenticated user can create a playlist', function () {
    $user = asUser();

    $response = $this->post('/playlists', [
        'title' => 'My Test Playlist',
        'description' => 'A test playlist',
        'privacy' => 'public',
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('playlists', [
        'user_id' => $user->id,
        'title' => 'My Test Playlist',
    ]);
});

test('playlist owner can update playlist', function () {
    $user = asUser();
    $playlist = Playlist::factory()->create(['user_id' => $user->id]);

    $response = $this->put("/playlists/{$playlist->id}", [
        'title' => 'Updated Playlist',
        'description' => 'Updated description',
        'privacy' => 'private',
    ]);

    $response->assertOk();
    expect($playlist->fresh()->title)->toBe('Updated Playlist');
});

test('playlist owner can delete playlist', function () {
    $user = asUser();
    $playlist = Playlist::factory()->create(['user_id' => $user->id]);

    $response = $this->delete("/playlists/{$playlist->id}");
    $response->assertRedirect();
    $this->assertDatabaseMissing('playlists', ['id' => $playlist->id]);
});

test('non-owner cannot delete playlist', function () {
    $playlist = Playlist::factory()->create();
    asUser();

    $this->delete("/playlists/{$playlist->id}")->assertStatus(403);
});

test('public playlist page loads for guests', function () {
    $playlist = Playlist::factory()->create(['privacy' => 'public']);
    $this->get("/playlist/{$playlist->slug}")->assertStatus(200);
});

test('authenticated user can add video to playlist', function () {
    $user = asUser();
    $playlist = Playlist::factory()->create(['user_id' => $user->id]);
    $video = Video::factory()->create();

    $response = $this->post("/playlists/{$playlist->id}/videos", [
        'video_id' => $video->id,
    ]);

    $response->assertOk();
    $response->assertJson(['success' => true]);
});

test('authenticated user can remove video from playlist', function () {
    $user = asUser();
    $playlist = Playlist::factory()->create(['user_id' => $user->id]);
    $video = Video::factory()->create();
    $playlist->addVideo($video);

    $response = $this->delete("/playlists/{$playlist->id}/videos", [
        'video_id' => $video->id,
    ]);

    $response->assertOk();
    $response->assertJson(['success' => true]);
});

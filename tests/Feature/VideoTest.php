<?php

use App\Models\Category;
use App\Models\User;
use App\Models\Video;

/*
|--------------------------------------------------------------------------
| Video — CRUD, Privacy, Access Control
|--------------------------------------------------------------------------
*/

test('guest cannot access upload page', function () {
    $this->get('/upload')->assertRedirect('/login');
});

test('authenticated user can access upload page', function () {
    asUser();
    $this->get('/upload')->assertStatus(200);
});

test('public video is visible to guests', function () {
    $video = Video::factory()->create(['privacy' => 'public']);
    $this->get("/{$video->slug}")->assertStatus(200);
});

test('private video returns 403 for non-owner', function () {
    $video = Video::factory()->private()->create();
    $otherUser = asUser();

    $response = $this->get("/{$video->slug}");
    // Private videos should not be accessible by other users
    $response->assertStatus(403);
});

test('private video is accessible by owner', function () {
    $user = asUser();
    $video = Video::factory()->private()->create(['user_id' => $user->id]);

    $this->get("/{$video->slug}")->assertStatus(200);
});

test('video owner can access edit page', function () {
    $user = asUser();
    $video = Video::factory()->create(['user_id' => $user->id]);

    $this->get("/videos/{$video->id}/edit")->assertStatus(200);
});

test('non-owner cannot access video edit page', function () {
    $video = Video::factory()->create();
    asUser();

    $this->get("/videos/{$video->id}/edit")->assertStatus(403);
});

test('video owner can update video', function () {
    $user = asUser();
    $video = Video::factory()->create(['user_id' => $user->id]);

    $response = $this->put("/videos/{$video->id}", [
        'title' => 'Updated Title',
        'description' => 'Updated description',
        'privacy' => 'public',
        'category_id' => $video->category_id,
    ]);

    $response->assertRedirect();
    expect($video->fresh()->title)->toBe('Updated Title');
});

test('video owner can delete video', function () {
    $user = asUser();
    $video = Video::factory()->create(['user_id' => $user->id]);

    $response = $this->delete("/videos/{$video->id}");

    $response->assertRedirect();
    $this->assertSoftDeleted('videos', ['id' => $video->id]);
});

test('non-owner cannot delete video', function () {
    $video = Video::factory()->create();
    asUser();

    $this->delete("/videos/{$video->id}")->assertStatus(403);
});

test('authenticated user can like a video', function () {
    asUser();
    $video = Video::factory()->create();

    $response = $this->post("/videos/{$video->id}/like");
    $response->assertRedirect();
});

test('authenticated user can dislike a video', function () {
    asUser();
    $video = Video::factory()->create();

    $response = $this->post("/videos/{$video->id}/dislike");
    $response->assertRedirect();
});

test('load more videos API returns JSON', function () {
    Video::factory()->count(5)->create();

    $response = $this->getJson('/api/videos/load-more');
    $response->assertStatus(200);
});

test('video ads API returns JSON', function () {
    $response = $this->getJson('/api/video-ads');
    $response->assertStatus(200);
    $response->assertJsonStructure([]);
});

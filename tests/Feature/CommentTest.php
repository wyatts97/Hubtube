<?php

use App\Models\Comment;
use App\Models\Video;

/*
|--------------------------------------------------------------------------
| Comments — CRUD, Replies, Likes
|--------------------------------------------------------------------------
*/

test('authenticated user can post a comment', function () {
    $user = asUser();
    $video = Video::factory()->create();

    $response = $this->post("/videos/{$video->id}/comments", [
        'content' => 'This is a test comment.',
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('comments', [
        'video_id' => $video->id,
        'user_id' => $user->id,
        'content' => 'This is a test comment.',
    ]);
});

test('guest cannot post a comment', function () {
    $video = Video::factory()->create();

    $this->post("/videos/{$video->id}/comments", [
        'content' => 'Guest comment',
    ])->assertRedirect('/login');
});

test('comment requires content', function () {
    asUser();
    $video = Video::factory()->create();

    $response = $this->post("/videos/{$video->id}/comments", [
        'content' => '',
    ]);

    $response->assertSessionHasErrors('content');
});

test('comment owner can update their comment', function () {
    $user = asUser();
    $video = Video::factory()->create();
    $comment = Comment::factory()->create([
        'user_id' => $user->id,
        'video_id' => $video->id,
    ]);

    $response = $this->put("/comments/{$comment->id}", [
        'content' => 'Updated comment text.',
    ]);

    $response->assertOk();
    $response->assertJson(['comment' => ['id' => $comment->id]]);
    expect($comment->fresh()->content)->toBe('Updated comment text.');
});

test('non-owner cannot update comment', function () {
    $comment = Comment::factory()->create();
    asUser();

    $this->put("/comments/{$comment->id}", [
        'content' => 'Hacked!',
    ])->assertStatus(403);
});

test('comment owner can delete their comment', function () {
    $user = asUser();
    $comment = Comment::factory()->create(['user_id' => $user->id]);

    $response = $this->delete("/comments/{$comment->id}");
    $response->assertOk();
    $response->assertJson(['success' => true]);
    $this->assertSoftDeleted('comments', ['id' => $comment->id]);
});

test('authenticated user can like a comment', function () {
    asUser();
    $comment = Comment::factory()->create();

    $response = $this->post("/comments/{$comment->id}/like");
    $response->assertOk();
    $response->assertJsonStructure(['liked', 'disliked', 'likesCount']);
});

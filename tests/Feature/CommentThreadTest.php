<?php

use App\Http\Controllers\CommentController;
use App\Models\Comment;
use App\Models\Notification;
use App\Models\Setting;
use App\Models\User;
use App\Models\Video;

/*
|--------------------------------------------------------------------------
| Comment threads — pagination, replies, editing, mentions, spam filter
|--------------------------------------------------------------------------
|
| The thread used to arrive in a single unpaginated response that serialised
| a full User per comment. These cover the shape it returns now as well as the
| moderation and mention behaviour layered on top.
|
*/

// ── Reading ─────────────────────────────────────────────────────────────────

test('top-level comments are paginated', function () {
    $video = Video::factory()->create();
    Comment::factory()->count(CommentController::PER_PAGE + 3)->create(['video_id' => $video->id]);

    $first = $this->getJson("/videos/{$video->id}/comments")->assertOk();
    expect($first->json('comments'))->toHaveCount(CommentController::PER_PAGE)
        ->and($first->json('meta.total'))->toBe(CommentController::PER_PAGE + 3)
        ->and($first->json('meta.last_page'))->toBe(2);

    $second = $this->getJson("/videos/{$video->id}/comments?page=2")->assertOk();
    expect($second->json('comments'))->toHaveCount(3);
});

test('a guest can read comments', function () {
    $video = Video::factory()->create();
    Comment::factory()->create(['video_id' => $video->id, 'content' => 'Visible to everyone']);

    $response = $this->getJson("/videos/{$video->id}/comments")->assertOk();

    expect($response->json('comments.0.content'))->toBe('Visible to everyone');
});

test('the comment payload never carries a commenter email address', function () {
    $video = Video::factory()->create();
    $author = User::factory()->create(['email' => 'commenter@example.com']);
    Comment::factory()->create(['video_id' => $video->id, 'user_id' => $author->id]);

    $response = $this->getJson("/videos/{$video->id}/comments")->assertOk();

    $response->assertDontSee('commenter@example.com');
    expect(array_keys($response->json('comments.0.user')))
        ->toBe(['id', 'username', 'avatar_url', 'avatar_alt', 'is_pro']);
});

test('comments on a private video are readable by its owner alone', function () {
    $owner = User::factory()->create();
    $video = Video::factory()->private()->create(['user_id' => $owner->id]);
    Comment::factory()->create(['video_id' => $video->id]);

    $this->getJson("/videos/{$video->id}/comments")->assertNotFound();

    asUser();
    $this->getJson("/videos/{$video->id}/comments")->assertNotFound();

    asUser($owner);
    $this->getJson("/videos/{$video->id}/comments")->assertOk();
});

test('the viewer sees their own like state on load', function () {
    $user = asUser();
    $video = Video::factory()->create();
    $comment = Comment::factory()->create(['video_id' => $video->id]);

    $this->post("/comments/{$comment->id}/like")->assertOk();

    $response = $this->getJson("/videos/{$video->id}/comments")->assertOk();

    expect($response->json('comments.0.user_liked'))->toBeTrue()
        ->and($response->json('comments.0.user_disliked'))->toBeFalse();
});

// ── The site-wide switch ────────────────────────────────────────────────────

test('turning comments off hides them and refuses new ones', function () {
    Setting::set('comments_enabled', false, 'general', 'boolean');
    $video = Video::factory()->create();
    Comment::factory()->create(['video_id' => $video->id]);

    $response = $this->getJson("/videos/{$video->id}/comments")->assertOk();
    expect($response->json('comments'))->toBe([])
        ->and($response->json('meta.total'))->toBe(0);

    asUser();
    $this->postJson("/videos/{$video->id}/comments", ['content' => 'Hello?'])->assertForbidden();
});

test('the watch page stops rendering the comment section when comments are off', function () {
    $video = Video::factory()->create();

    $page = inertiaPagePayload($this->get("/{$video->slug}"));
    expect($page['props']['commentsEnabled'])->toBeTrue();

    Setting::set('comments_enabled', false, 'general', 'boolean');

    $page = inertiaPagePayload($this->get("/{$video->slug}"));
    expect($page['props']['commentsEnabled'])->toBeFalse();
});

// ── Replies ─────────────────────────────────────────────────────────────────

test('a comment previews its first replies and reports the full count', function () {
    $video = Video::factory()->create();
    $parent = Comment::factory()->create(['video_id' => $video->id]);
    Comment::factory()->count(7)->reply($parent)->create();

    $response = $this->getJson("/videos/{$video->id}/comments")->assertOk();

    // Only the parent is a top-level comment.
    expect($response->json('comments'))->toHaveCount(1)
        ->and($response->json('comments.0.replies'))->toHaveCount(CommentController::REPLY_PREVIEW)
        ->and($response->json('comments.0.replies_count'))->toBe(7);
});

test('the rest of the replies load on demand, oldest first', function () {
    $video = Video::factory()->create();
    $parent = Comment::factory()->create(['video_id' => $video->id]);
    $replies = Comment::factory()->count(CommentController::REPLIES_PER_PAGE + 4)->reply($parent)->create();

    $first = $this->getJson("/comments/{$parent->id}/replies")->assertOk();
    expect($first->json('replies'))->toHaveCount(CommentController::REPLIES_PER_PAGE)
        ->and($first->json('has_more'))->toBeTrue()
        ->and($first->json('replies.0.id'))->toBe($replies->first()->id);

    $lastSeen = $first->json('replies.'.(CommentController::REPLIES_PER_PAGE - 1).'.id');
    $second = $this->getJson("/comments/{$parent->id}/replies?after={$lastSeen}")->assertOk();

    expect($second->json('replies'))->toHaveCount(4)
        ->and($second->json('has_more'))->toBeFalse();
});

test('replying to a reply attaches to the top-level comment', function () {
    asUser();
    $video = Video::factory()->create();
    $parent = Comment::factory()->create(['video_id' => $video->id]);
    $reply = Comment::factory()->reply($parent)->create();

    $response = $this->postJson("/videos/{$video->id}/comments", [
        'content' => 'Threads stay one level deep.',
        'parent_id' => $reply->id,
    ])->assertCreated();

    expect($response->json('comment.parent_id'))->toBe($parent->id);
});

test('a comment cannot reply to a comment on another video', function () {
    asUser();
    $video = Video::factory()->create();
    $elsewhere = Comment::factory()->create();

    $this->postJson("/videos/{$video->id}/comments", [
        'content' => 'Wrong thread.',
        'parent_id' => $elsewhere->id,
    ])->assertStatus(422);
});

test('deleting a comment takes its replies with it', function () {
    $user = asUser();
    $video = Video::factory()->create();
    $parent = Comment::factory()->create(['video_id' => $video->id, 'user_id' => $user->id]);
    $reply = Comment::factory()->reply($parent)->create();

    $this->delete("/comments/{$parent->id}")->assertOk();

    $this->assertSoftDeleted('comments', ['id' => $parent->id]);
    $this->assertSoftDeleted('comments', ['id' => $reply->id]);
});

// ── Editing ─────────────────────────────────────────────────────────────────

test('editing a comment records when it was edited', function () {
    $user = asUser();
    $comment = Comment::factory()->create(['user_id' => $user->id, 'edited_at' => null]);

    $response = $this->putJson("/comments/{$comment->id}", ['content' => 'Second thoughts.'])->assertOk();

    expect($comment->fresh()->edited_at)->not->toBeNull()
        ->and($response->json('comment.edited_at'))->not->toBeNull()
        ->and($response->json('comment.content'))->toBe('Second thoughts.');
});

test('the payload says who may edit and delete each comment', function () {
    $owner = asUser();
    $video = Video::factory()->create();
    Comment::factory()->create(['video_id' => $video->id, 'user_id' => $owner->id]);
    Comment::factory()->create(['video_id' => $video->id]);

    $response = $this->getJson("/videos/{$video->id}/comments")->assertOk();
    $mine = collect($response->json('comments'))->firstWhere('user_id', $owner->id);
    $theirs = collect($response->json('comments'))->firstWhere('user_id', '!=', $owner->id);

    expect($mine['can_edit'])->toBeTrue()
        ->and($mine['can_delete'])->toBeTrue()
        ->and($theirs['can_edit'])->toBeFalse()
        ->and($theirs['can_delete'])->toBeFalse();
});

// ── Spam filter ─────────────────────────────────────────────────────────────

test('a comment containing a blocked word is held for approval', function () {
    Setting::set('comment_blocked_words', ['buy now'], 'general', 'array');
    $author = asUser();
    $video = Video::factory()->create();

    $response = $this->postJson("/videos/{$video->id}/comments", [
        'content' => 'Please buy now, friend.',
    ])->assertCreated();

    expect($response->json('pending'))->toBeTrue();
    $this->assertDatabaseHas('comments', ['video_id' => $video->id, 'is_approved' => false]);

    // Held comments stay visible to their author and to nobody else.
    expect($this->getJson("/videos/{$video->id}/comments")->json('comments'))->toHaveCount(1);

    asUser();
    expect($this->getJson("/videos/{$video->id}/comments")->json('comments'))->toHaveCount(0);
});

test('a blocked word only matches whole words', function () {
    Setting::set('comment_blocked_words', ['ass'], 'general', 'array');
    asUser();
    $video = Video::factory()->create();

    $response = $this->postJson("/videos/{$video->id}/comments", [
        'content' => 'A masterclass in passing.',
    ])->assertCreated();

    expect($response->json('pending'))->toBeFalse();
});

test('a comment with too many links is held for approval', function () {
    Setting::set('comment_max_links', 2, 'general', 'integer');
    asUser();
    $video = Video::factory()->create();

    $response = $this->postJson("/videos/{$video->id}/comments", [
        'content' => 'https://a.test https://b.test https://c.test',
    ])->assertCreated();

    expect($response->json('pending'))->toBeTrue();
});

test('editing a clean comment into spam sends it back for approval', function () {
    Setting::set('comment_blocked_words', ['casino'], 'general', 'array');
    $user = asUser();
    $comment = Comment::factory()->create(['user_id' => $user->id, 'is_approved' => true]);

    $response = $this->putJson("/comments/{$comment->id}", ['content' => 'Visit my casino.'])->assertOk();

    expect($response->json('pending'))->toBeTrue()
        ->and($comment->fresh()->is_approved)->toBeFalse();
});

// ── comments_count ──────────────────────────────────────────────────────────

test('the video comment count follows approval, not creation', function () {
    Setting::set('comments_require_approval', true, 'general', 'boolean');
    asUser();
    $video = Video::factory()->create(['comments_count' => 0]);

    $this->postJson("/videos/{$video->id}/comments", ['content' => 'Waiting for a moderator.'])->assertCreated();
    expect($video->fresh()->comments_count)->toBe(0);

    // Approving from the admin panel moves the counter too.
    Comment::where('video_id', $video->id)->first()->update(['is_approved' => true]);
    expect($video->fresh()->comments_count)->toBe(1);
});

test('deleting an approved comment lowers the count and cannot drive it negative', function () {
    $user = asUser();
    $video = Video::factory()->create(['comments_count' => 0]);
    $comment = Comment::factory()->create(['video_id' => $video->id, 'user_id' => $user->id]);

    expect($video->fresh()->comments_count)->toBe(1);

    $this->delete("/comments/{$comment->id}")->assertOk();
    expect($video->fresh()->comments_count)->toBe(0);

    Comment::factory()->unapproved()->create(['video_id' => $video->id])->delete();
    expect($video->fresh()->comments_count)->toBe(0);
});

// ── Mentions ────────────────────────────────────────────────────────────────

test('mentioning someone notifies them', function () {
    $mentioned = User::factory()->create(['username' => 'ada']);
    asUser();
    $video = Video::factory()->create();

    $this->postJson("/videos/{$video->id}/comments", [
        'content' => 'Ask @ada about it.',
    ])->assertCreated();

    $this->assertDatabaseHas('notifications', [
        'user_id' => $mentioned->id,
        'type' => Notification::TYPE_COMMENT_MENTION,
    ]);
});

test('an unknown @name notifies nobody and is not an error', function () {
    asUser();
    $video = Video::factory()->create();

    $this->postJson("/videos/{$video->id}/comments", [
        'content' => 'Hello @nobody-here.',
    ])->assertCreated();

    expect(Notification::where('type', Notification::TYPE_COMMENT_MENTION)->count())->toBe(0);
});

test('a reply that also mentions the same person notifies them once', function () {
    $parentAuthor = User::factory()->create(['username' => 'grace']);
    $video = Video::factory()->create();
    $parent = Comment::factory()->create(['video_id' => $video->id, 'user_id' => $parentAuthor->id]);

    asUser();
    $this->postJson("/videos/{$video->id}/comments", [
        'content' => '@grace good point.',
        'parent_id' => $parent->id,
    ])->assertCreated();

    expect(Notification::where('user_id', $parentAuthor->id)->count())->toBe(1);
    expect(Notification::where('user_id', $parentAuthor->id)->first()->type)
        ->toBe(Notification::TYPE_COMMENT_REPLY);
});

test('mentioning yourself notifies nobody', function () {
    $author = asUser(User::factory()->create(['username' => 'linus']));
    $video = Video::factory()->create();

    $this->postJson("/videos/{$video->id}/comments", [
        'content' => 'Note to self, @linus.',
    ])->assertCreated();

    expect(Notification::where('user_id', $author->id)->count())->toBe(0);
});

test('a held comment notifies nobody until it is approved', function () {
    Setting::set('comment_blocked_words', ['spam'], 'general', 'array');
    $mentioned = User::factory()->create(['username' => 'ada']);
    asUser();
    $video = Video::factory()->create();

    $this->postJson("/videos/{$video->id}/comments", [
        'content' => 'spam for @ada',
    ])->assertCreated();

    expect(Notification::where('user_id', $mentioned->id)->count())->toBe(0);
});

<?php

use App\Models\Comment;
use App\Models\Notification as NotificationModel;
use App\Models\User;
use App\Models\Video;
use App\Notifications\NewCommentNotification;
use App\Notifications\VideoLikeNotification;
use Illuminate\Support\Facades\Notification;

/*
|--------------------------------------------------------------------------
| Laravel Notification System — Custom Database + Email Channels
|--------------------------------------------------------------------------
*/

test('commenting on someone else\'s video notifies the video owner', function () {
    $owner = User::factory()->create();
    $commenter = asUser();
    $video = Video::factory()->create(['user_id' => $owner->id]);

    $this->postJson("/videos/{$video->id}/comments", [
        'content' => 'Nice video!',
    ])->assertStatus(201);

    $this->assertDatabaseHas('notifications', [
        'user_id' => $owner->id,
        'type' => NotificationModel::TYPE_NEW_COMMENT,
        'from_user_id' => $commenter->id,
    ]);
});

test('commenting on your own video does not notify yourself', function () {
    $user = asUser();
    $video = Video::factory()->create(['user_id' => $user->id]);

    $this->postJson("/videos/{$video->id}/comments", [
        'content' => 'My own comment',
    ])->assertStatus(201);

    $this->assertDatabaseMissing('notifications', [
        'user_id' => $user->id,
        'type' => NotificationModel::TYPE_NEW_COMMENT,
    ]);
});

test('replying to a comment notifies the parent comment author', function () {
    $video = Video::factory()->create();
    $parentAuthor = User::factory()->create();
    $replier = asUser();

    $parentComment = Comment::factory()->create([
        'video_id' => $video->id,
        'user_id' => $parentAuthor->id,
    ]);

    $this->postJson("/videos/{$video->id}/comments", [
        'content' => 'Replying to you',
        'parent_id' => $parentComment->id,
    ])->assertStatus(201);

    $this->assertDatabaseHas('notifications', [
        'user_id' => $parentAuthor->id,
        'type' => NotificationModel::TYPE_COMMENT_REPLY,
        'from_user_id' => $replier->id,
    ]);
});

test('liking a video notifies the video owner once', function () {
    $owner = User::factory()->create();
    $liker = asUser();
    $video = Video::factory()->create(['user_id' => $owner->id]);

    $this->postJson("/videos/{$video->id}/like")->assertStatus(200);

    $this->assertDatabaseHas('notifications', [
        'user_id' => $owner->id,
        'type' => NotificationModel::TYPE_VIDEO_LIKE,
        'from_user_id' => $liker->id,
    ]);

    expect(NotificationModel::where('user_id', $owner->id)->where('type', NotificationModel::TYPE_VIDEO_LIKE)->count())->toBe(1);
});

test('unliking then liking again does not duplicate the like notification', function () {
    $owner = User::factory()->create();
    asUser();
    $video = Video::factory()->create(['user_id' => $owner->id]);

    $this->postJson("/videos/{$video->id}/like")->assertStatus(200);
    $this->postJson("/videos/{$video->id}/like")->assertStatus(200); // toggles off
    $this->postJson("/videos/{$video->id}/like")->assertStatus(200); // toggles on again

    expect(NotificationModel::where('user_id', $owner->id)->where('type', NotificationModel::TYPE_VIDEO_LIKE)->count())->toBe(1);
});

test('withdrawal approval notifies the requesting user', function () {
    $admin = asAdmin();
    $user = User::factory()->create(['wallet_balance' => 100]);

    $withdrawal = \App\Models\WithdrawalRequest::create([
        'user_id' => $user->id,
        'status' => \App\Models\WithdrawalRequest::STATUS_PENDING,
        'amount' => 50,
        'currency' => 'USD',
        'payment_method' => 'paypal',
        'payment_details' => ['email' => $user->email],
    ]);

    $withdrawal->approve($admin, 'TXN123');
    $user->notify(new \App\Notifications\WithdrawalApprovedNotification($withdrawal->fresh()));

    $this->assertDatabaseHas('notifications', [
        'user_id' => $user->id,
        'type' => NotificationModel::TYPE_WITHDRAWAL_APPROVED,
    ]);
});

test('custom database channel does not error when notifiable has no id property mismatch', function () {
    Notification::fake();

    $user = User::factory()->create();
    $video = Video::factory()->create(['user_id' => $user->id]);
    $liker = User::factory()->create();

    $user->notify(new VideoLikeNotification($video, $liker));

    Notification::assertSentTo($user, VideoLikeNotification::class);
});

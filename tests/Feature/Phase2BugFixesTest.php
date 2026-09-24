<?php

use App\Models\CCBillSubscription;
use App\Models\Plan;
use App\Models\PointsTransaction;
use App\Models\User;
use App\Models\Video;
use App\Services\PointsService;
use App\Services\VideoService;
use App\Services\WalletService;
use Illuminate\Http\UploadedFile;
use Laravel\Cashier\Events\WebhookReceived;

test('a scheduled upload stays an unpublished draft until its time', function () {
    $user = User::factory()->create();
    $when = now()->addDay();

    $video = app(VideoService::class)->create([
        'title' => 'Scheduled Upload',
        'privacy' => 'public',
        'video_file' => UploadedFile::fake()->create('clip.mp4', 64, 'video/mp4'),
        'scheduled_at' => $when->toDateTimeString(),
    ], $user);

    expect($video->fresh())
        ->published_at->toBeNull()
        ->is_draft->toBeTrue()
        ->and($video->fresh()->scheduled_at->timestamp)->toBe($when->timestamp);
});

test('an unscheduled upload is published immediately', function () {
    $video = app(VideoService::class)->create([
        'title' => 'Now',
        'privacy' => 'public',
        'video_file' => UploadedFile::fake()->create('clip.mp4', 64, 'video/mp4'),
    ], User::factory()->create());

    expect($video->fresh())->published_at->not->toBeNull()->is_draft->toBeFalse();
});

test('editing a video can clear its description and category', function () {
    $video = Video::factory()->create(['description' => 'Old text']);

    app(VideoService::class)->update($video, ['description' => null, 'category_id' => null]);

    expect($video->fresh())->description->toBeNull()->category_id->toBeNull();
});

test('points for the same reference are awarded once', function () {
    $user = User::factory()->create();
    $video = Video::factory()->create(['user_id' => $user->id]);
    $points = app(PointsService::class);

    $points->award($user, PointsTransaction::TYPE_VIDEO_UPLOAD, 10, $video);
    $points->award($user, PointsTransaction::TYPE_VIDEO_UPLOAD, 10, $video);

    expect(PointsTransaction::where('reference_id', $video->id)->count())->toBe(1)
        ->and($user->fresh()->points_balance)->toBe(10);
});

test('a cancelled stripe subscription keeps pro granted by an active ccbill subscription', function () {
    $user = User::factory()->create();
    $user->forceFill(['stripe_id' => 'cus_both', 'is_pro' => true, 'pro_source' => 'ccbill'])->save();
    CCBillSubscription::create([
        'user_id' => $user->id,
        'plan_id' => Plan::factory()->create()->id,
        'ccbill_subscription_id' => 'sub-live',
        'status' => CCBillSubscription::STATUS_ACTIVE,
        'subscription_type' => 'recurring',
        'current_period_end' => now()->addDays(20),
    ]);

    event(new WebhookReceived([
        'type' => 'customer.subscription.deleted',
        'data' => ['object' => ['customer' => 'cus_both', 'status' => 'canceled']],
    ]));

    expect($user->fresh()->is_pro)->toBeTrue();
});

test('a cancelled stripe subscription revokes stripe-only pro', function () {
    $user = User::factory()->create();
    $user->forceFill(['stripe_id' => 'cus_only', 'is_pro' => true, 'pro_source' => 'stripe'])->save();

    event(new WebhookReceived([
        'type' => 'customer.subscription.deleted',
        'data' => ['object' => ['customer' => 'cus_only', 'status' => 'canceled']],
    ]));

    expect($user->fresh()->is_pro)->toBeFalse();
});

test('wallet arithmetic is exact to the cent', function () {
    $user = User::factory()->create(['wallet_balance' => 0]);
    $wallet = app(WalletService::class);

    $wallet->credit($user, 0.1, 'deposit');
    $wallet->credit($user, 0.2, 'deposit');
    $wallet->debit($user, 0.3, 'purchase');

    expect((string) $user->fresh()->wallet_balance)->toBe('0.00');
});

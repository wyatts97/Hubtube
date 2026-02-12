<?php

use App\Models\User;
use App\Models\Video;

/*
|--------------------------------------------------------------------------
| Video Model — Scopes, Accessors, Business Logic
|--------------------------------------------------------------------------
*/

test('video formatted duration formats correctly for minutes', function () {
    $video = Video::factory()->make(['duration' => 125]);
    expect($video->formatted_duration)->toBe('2:05');
});

test('video formatted duration formats correctly for hours', function () {
    $video = Video::factory()->make(['duration' => 3661]);
    expect($video->formatted_duration)->toBe('1:01:01');
});

test('video formatted duration handles zero', function () {
    $video = Video::factory()->make(['duration' => 0]);
    expect($video->formatted_duration)->toBe('0:00');
});

test('public video is accessible by anyone', function () {
    $video = Video::factory()->make(['privacy' => 'public']);
    expect($video->isAccessibleBy(null))->toBeTrue();
});

test('private video is not accessible by guest', function () {
    $video = Video::factory()->make(['privacy' => 'private', 'user_id' => 1]);
    expect($video->isAccessibleBy(null))->toBeFalse();
});

test('private video is accessible by owner', function () {
    $user = User::factory()->create();
    $video = Video::factory()->make(['privacy' => 'private', 'user_id' => $user->id]);
    expect($video->isAccessibleBy($user))->toBeTrue();
});

test('private video is not accessible by other user', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $video = Video::factory()->make(['privacy' => 'private', 'user_id' => $owner->id]);
    expect($video->isAccessibleBy($other))->toBeFalse();
});

test('unlisted video is accessible by any authenticated user', function () {
    $user = User::factory()->create();
    $video = Video::factory()->make(['privacy' => 'unlisted', 'user_id' => 999]);
    expect($video->isAccessibleBy($user))->toBeTrue();
});

test('video isPaid returns true when price is set', function () {
    $video = Video::factory()->make(['price' => 9.99, 'rent_price' => 0]);
    expect($video->isPaid())->toBeTrue();
});

test('video isPaid returns true when rent price is set', function () {
    $video = Video::factory()->make(['price' => 0, 'rent_price' => 4.99]);
    expect($video->isPaid())->toBeTrue();
});

test('video isPaid returns false when no price', function () {
    $video = Video::factory()->make(['price' => 0, 'rent_price' => 0]);
    expect($video->isPaid())->toBeFalse();
});

test('video shouldBeSearchable returns false for database driver', function () {
    config(['scout.driver' => 'database']);
    $video = Video::factory()->make(['status' => 'processed', 'is_approved' => true]);
    expect($video->shouldBeSearchable())->toBeFalse();
});

test('video scope public filters correctly', function () {
    Video::factory()->create(['privacy' => 'public']);
    Video::factory()->create(['privacy' => 'private']);
    Video::factory()->create(['privacy' => 'unlisted']);

    expect(Video::public()->count())->toBe(1);
});

test('video scope approved filters correctly', function () {
    Video::factory()->create(['is_approved' => true]);
    Video::factory()->create(['is_approved' => false]);

    expect(Video::approved()->count())->toBe(1);
});

test('video scope processed filters correctly', function () {
    Video::factory()->create(['status' => 'processed']);
    Video::factory()->create(['status' => 'processing']);

    expect(Video::processed()->count())->toBe(1);
});

test('video scope shorts filters correctly', function () {
    Video::factory()->create(['is_short' => true]);
    Video::factory()->create(['is_short' => false]);

    expect(Video::shorts()->count())->toBe(1);
});

test('video casts tags as array', function () {
    $video = Video::factory()->create(['tags' => ['tag1', 'tag2', 'tag3']]);
    $fresh = $video->fresh();

    expect($fresh->tags)->toBeArray();
    expect($fresh->tags)->toContain('tag1');
});

test('video casts qualities_available as array', function () {
    $video = Video::factory()->create(['qualities_available' => ['720p', '1080p']]);
    $fresh = $video->fresh();

    expect($fresh->qualities_available)->toBeArray();
    expect($fresh->qualities_available)->toContain('720p');
});

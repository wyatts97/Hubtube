<?php

use App\Models\Category;
use App\Models\User;
use App\Models\Video;
use App\Services\BulkVideoCreator;
use Illuminate\Support\Facades\Storage;

/*
|--------------------------------------------------------------------------
| Draft videos
|--------------------------------------------------------------------------
|
| A draft has finished processing but is private to its uploader. Videos
| waiting on the publishing schedule are drafts, which is what stops a
| scheduled video being openable by anyone who guesses its URL before its
| time arrives.
|
*/

test('a draft is not viewable by guests or other users', function () {
    $video = Video::factory()->create(['is_draft' => true]);

    $this->get("/{$video->slug}")->assertForbidden();

    asUser();
    $this->get("/{$video->slug}")->assertForbidden();
});

test('the uploader and admins can view a draft', function () {
    $owner = User::factory()->create();
    $video = Video::factory()->create(['is_draft' => true, 'user_id' => $owner->id]);

    asUser($owner);
    $this->get("/{$video->slug}")->assertOk();

    asAdmin();
    $this->get("/{$video->slug}")->assertOk();
});

test('drafts stay out of listings and search indexing', function () {
    Video::factory()->create(['is_draft' => true, 'title' => 'Hidden Draft Clip']);
    $live = Video::factory()->create(['title' => 'Live Clip']);

    $this->get('/videos')
        ->assertOk()
        ->assertSee('Live Clip')
        ->assertDontSee('Hidden Draft Clip');

    expect(Video::factory()->make(['is_draft' => true])->shouldBeSearchable())->toBeFalse();
    expect(Video::public()->approved()->pluck('id')->all())->toBe([$live->id]);
});

test('drafts are left out of the sitemap', function () {
    $draft = Video::factory()->create(['is_draft' => true]);
    $live = Video::factory()->create();

    $this->get('/sitemap-videos.xml')
        ->assertOk()
        ->assertSee($live->slug)
        ->assertDontSee($draft->slug);
});

test('bulk uploads queued for scheduling are created as drafts', function () {
    Storage::fake('public');
    $category = Category::factory()->create();
    $user = User::factory()->create();

    $path = 'videos/admin-uploads/clip.mp4';
    Storage::disk('public')->put($path, 'x');

    $ids = app(BulkVideoCreator::class)->createMany([[
        'title' => 'Queued Clip',
        'description' => 'From the bulk uploader.',
        'category_id' => $category->id,
        'user_id' => $user->id,
        'tags' => [],
        'age_restricted' => true,
        'file_path' => $path,
        'file_name' => 'clip.mp4',
        'file_size' => 1024,
    ]], true, $user->id);

    $video = Video::findOrFail($ids[0]);
    expect($video->is_draft)->toBeTrue()
        ->and($video->queue_order)->not->toBeNull()
        ->and($video->published_at)->toBeNull();
});

test('publishing a scheduled draft makes it live', function () {
    $video = Video::factory()->create([
        'is_draft' => true,
        'is_approved' => true,
        'published_at' => null,
        'scheduled_at' => now()->subMinute(),
        'queue_order' => 1,
        'status' => 'processed',
    ]);

    $this->artisan('videos:publish-scheduled')->assertSuccessful();

    $video->refresh();
    expect($video->is_draft)->toBeFalse()
        ->and($video->published_at)->not->toBeNull();

    $this->get("/{$video->slug}")->assertOk();
});

test('a draft cannot be embedded', function () {
    $video = Video::factory()->create(['is_draft' => true]);

    $this->get("/embed/{$video->slug}")->assertNotFound();
});

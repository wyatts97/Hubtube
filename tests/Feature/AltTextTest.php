<?php

use App\Models\Channel;
use App\Models\Gallery;
use App\Models\Image;
use App\Models\Setting;
use App\Models\User;
use App\Models\Video;
use App\Services\AltTextService;
use Illuminate\Support\Facades\DB;

/*
|--------------------------------------------------------------------------
| Image Alt Text — generation, persistence and backfill
|--------------------------------------------------------------------------
*/

function altText(): AltTextService
{
    return app(AltTextService::class);
}

/*
| Template substitution
*/

test('video alt text names the title and the uploader channel', function () {
    $user = User::factory()->create(['username' => 'wyatts']);
    $user->channel()->update(['name' => 'Wyatt Films']);

    $video = Video::factory()->create(['title' => 'Kyoto Rooftop Timelapse', 'user_id' => $user->id]);
    $video->load('user.channel');

    expect(altText()->forVideo($video))
        ->toBe('Kyoto Rooftop Timelapse - video thumbnail from Wyatt Films');
});

test('video alt text falls back to the username when there is no channel name', function () {
    $user = User::factory()->create(['username' => 'wyatts']);
    $user->channel()->update(['name' => '']);

    $video = Video::factory()->create(['title' => 'Kyoto Rooftop', 'user_id' => $user->id]);
    $video->load('user.channel');

    expect(altText()->forVideo($video))->toBe('Kyoto Rooftop - video thumbnail from wyatts');
});

test('an unloaded relation degrades instead of lazy loading', function () {
    $video = Video::factory()->create(['title' => 'Kyoto Rooftop']);

    // No ->load() call: forVideo() must not touch the user relation, both
    // because shouldBeStrict() would throw and because a lazy load here would
    // become an N+1 on every video grid on the site.
    expect(altText()->forVideo($video))->toBe('Kyoto Rooftop - video thumbnail');
});

test('image alt text uses the description when the title is blank', function () {
    $user = User::factory()->create(['username' => 'wyatts']);
    $image = Image::factory()->create([
        'title' => '',
        'description' => 'A red vintage motorcycle',
        'user_id' => $user->id,
    ]);
    $image->load('user');

    expect(altText()->forImage($image))->toBe('A red vintage motorcycle - photo by wyatts');
});

test('avatar and gallery alt text read naturally', function () {
    $user = User::factory()->create(['username' => 'wyatts']);
    $gallery = Gallery::create([
        'user_id' => $user->id,
        'title' => 'Garage Builds',
        'slug' => 'garage-builds',
        'privacy' => 'public',
    ]);

    expect(altText()->forUserAvatar($user))->toBe('Profile picture of wyatts')
        ->and(altText()->forGallery($gallery))->toBe('Cover image for the gallery Garage Builds');
});

/*
| Normalization — the cases a naive str_replace gets wrong
*/

test('a dangling connector left by an empty variable is removed', function () {
    Setting::set('seo_image_alt_template', '{title} - photo by {username}', 'seo', 'string');

    // No user relation loaded, so {username} resolves to nothing and the
    // template would otherwise end in a bare "- photo by".
    $image = Image::factory()->create(['title' => 'Red Motorcycle']);

    expect(altText()->forImage($image))->toBe('Red Motorcycle - photo');
});

test('a template that resolves to nothing at all yields a generic label', function () {
    Setting::set('seo_image_alt_template', '{title} by {username}', 'seo', 'string');

    $image = Image::factory()->make(['title' => '', 'description' => '']);

    expect(altText()->forImage($image))->toBe('Photo');
});

test('alt text is truncated on a word boundary and carries no ellipsis', function () {
    $image = Image::factory()->create(['title' => str_repeat('motorcycle ', 30)]);

    $result = altText()->forImage($image);

    expect(mb_strlen($result))->toBeLessThanOrEqual(AltTextService::MAX_LENGTH)
        ->and($result)->not->toEndWith('...')
        ->and($result)->not->toEndWith('motorcy');
});

test('markup in a title never reaches the alt attribute', function () {
    $image = Image::factory()->create(['title' => '<b>Red</b> &amp; Blue']);

    expect(altText()->forImage($image))->toStartWith('Red & Blue');
});

test('tags are capped so keywords cannot fill the length budget', function () {
    Setting::set('seo_video_thumbnail_alt_template', '{title} - {tags}', 'seo', 'string');

    $video = Video::factory()->create([
        'title' => 'Kyoto',
        'tags' => ['travel', 'japan', 'timelapse', 'drone', 'sunset'],
    ]);

    expect(altText()->forVideo($video))->toBe('Kyoto - travel, japan, timelapse');
});

/*
| Persistence via observers
*/

test('a new video gets thumbnail alt text without a backfill run', function () {
    $video = Video::factory()->create(['title' => 'Kyoto Rooftop']);

    expect($video->fresh()->thumbnail_alt_text)->toBe('Kyoto Rooftop - video thumbnail');
});

test('a manually edited alt text survives later saves', function () {
    $video = Video::factory()->create(['title' => 'Kyoto Rooftop']);

    $video->update(['thumbnail_alt_text' => 'Hand written description']);
    $video->update(['views_count' => 99]);

    expect($video->fresh()->thumbnail_alt_text)->toBe('Hand written description');
});

test('the model accessor generates for rows that predate the backfill', function () {
    $video = Video::factory()->create(['title' => 'Kyoto Rooftop']);

    // Simulate a pre-migration row by clearing the column behind the observer.
    DB::table('videos')->where('id', $video->id)->update(['thumbnail_alt_text' => null]);

    expect(Video::find($video->id)->thumbnail_alt)->toBe('Kyoto Rooftop - video thumbnail');
});

test('alt text is serialized to the frontend payload', function () {
    $video = Video::factory()->create(['title' => 'Kyoto Rooftop']);
    $image = Image::factory()->create(['title' => 'Red Motorcycle']);

    expect($video->toArray())->toHaveKey('thumbnail_alt')
        ->and($image->toArray())->toHaveKey('alt');
});

/*
| Backfill command
*/

test('the backfill fills rows that have no alt text', function () {
    $user = User::factory()->create(['username' => 'wyatts']);
    $user->channel()->update(['name' => 'Wyatt Films']);

    $video = Video::factory()->create(['title' => 'Kyoto Rooftop', 'user_id' => $user->id]);
    DB::table('videos')->where('id', $video->id)->update(['thumbnail_alt_text' => null]);

    $this->artisan('seo:backfill-alt-text', ['--type' => 'videos'])->assertSuccessful();

    // The command eager-loads user.channel, so unlike the bare accessor its
    // output names the channel too. That is the point of running it.
    expect($video->fresh()->thumbnail_alt_text)
        ->toBe('Kyoto Rooftop - video thumbnail from Wyatt Films');
});

test('a dry run reports without writing', function () {
    $video = Video::factory()->create(['title' => 'Kyoto Rooftop']);
    DB::table('videos')->where('id', $video->id)->update(['thumbnail_alt_text' => null]);

    $this->artisan('seo:backfill-alt-text', ['--type' => 'videos', '--dry-run' => true])
        ->assertSuccessful();

    expect($video->fresh()->thumbnail_alt_text)->toBeNull();
});

test('the backfill leaves existing alt text alone unless forced', function () {
    $user = User::factory()->create(['username' => 'wyatts']);
    $user->channel()->update(['name' => 'Wyatt Films']);

    $video = Video::factory()->create(['title' => 'Kyoto Rooftop', 'user_id' => $user->id]);
    $video->update(['thumbnail_alt_text' => 'Hand written description']);

    $this->artisan('seo:backfill-alt-text', ['--type' => 'videos'])->assertSuccessful();
    expect($video->fresh()->thumbnail_alt_text)->toBe('Hand written description');

    $this->artisan('seo:backfill-alt-text', ['--type' => 'videos', '--force' => true])
        ->assertSuccessful();
    expect($video->fresh()->thumbnail_alt_text)
        ->toBe('Kyoto Rooftop - video thumbnail from Wyatt Films');
});

test('an unknown type is rejected', function () {
    $this->artisan('seo:backfill-alt-text', ['--type' => 'penguins'])->assertFailed();
});

test('the backfill covers every media type in one run', function () {
    $user = User::factory()->create(['username' => 'wyatts']);
    Image::factory()->create(['title' => 'Red Motorcycle', 'user_id' => $user->id]);

    DB::table('images')->update(['alt_text' => null]);
    DB::table('users')->update(['avatar_alt_text' => null]);
    DB::table('channels')->update(['banner_alt_text' => null]);

    $this->artisan('seo:backfill-alt-text')->assertSuccessful();

    expect(DB::table('images')->whereNull('alt_text')->count())->toBe(0)
        ->and(DB::table('users')->whereNull('avatar_alt_text')->count())->toBe(0)
        ->and(DB::table('channels')->whereNull('banner_alt_text')->count())->toBe(0);
});

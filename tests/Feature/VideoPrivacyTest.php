<?php

use App\Http\Controllers\VideoController;
use App\Models\Category;
use App\Models\Hashtag;
use App\Models\Setting;
use App\Models\User;
use App\Models\Video;
use App\Support\VideoPrivacy;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

/*
|--------------------------------------------------------------------------
| Video privacy — access rules, admin toggles, owner changes
|--------------------------------------------------------------------------
*/

function allowPrivacy(bool $unlisted = false, bool $private = false): void
{
    Setting::set('allow_unlisted_uploads', $unlisted, 'general', 'boolean');
    Setting::set('allow_private_uploads', $private, 'general', 'boolean');
}

/** Put an assembled upload where finalize() expects it and return the client upload id. */
function stageAssembledUpload(User $user): string
{
    $clientId = 'privacy-test-'.$user->id;
    $method = new ReflectionMethod(VideoController::class, 'scopedUploadId');
    $scoped = $method->invoke(app(VideoController::class), $user->id, $clientId);

    $dir = storage_path('app/chunks');
    if (! is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    file_put_contents("{$dir}/{$scoped}.mp4", str_repeat("\0", 2048));

    return $clientId;
}

function finalizePayload(string $uploadId, array $overrides = []): array
{
    // Uploaders may only use tags that already exist (KnownTags).
    foreach (['alpha', 'bravo', 'charlie'] as $tag) {
        Hashtag::firstOrCreate(['slug' => $tag], ['name' => $tag]);
    }

    return array_merge([
        'upload_id' => $uploadId,
        'extension' => 'mp4',
        'original_filename' => 'clip.mp4',
        'title' => 'Privacy Fixture',
        'description' => 'A description long enough to satisfy validation.',
        'category_id' => Category::factory()->create()->id,
        'tags' => ['alpha', 'bravo', 'charlie'],
    ], $overrides);
}

// ── Access ──────────────────────────────────────────────────────────────────

test('guests can watch an unlisted video by its link', function () {
    $video = Video::factory()->unlisted()->create();

    $this->get("/{$video->slug}")->assertOk();
});

test('guests cannot watch a private video', function () {
    $video = Video::factory()->private()->create();

    $this->get("/{$video->slug}")->assertForbidden();
});

test('admins can watch another user\'s private video', function () {
    $video = Video::factory()->private()->create();
    asAdmin();

    $this->get("/{$video->slug}")->assertOk();
});

test('unlisted videos stay out of the public listing', function () {
    $video = Video::factory()->unlisted()->create(['title' => 'Hidden From Lists']);

    $this->get('/videos')->assertOk()->assertDontSee('Hidden From Lists');
});

// ── Allowed options ─────────────────────────────────────────────────────────

test('only public is offered while both admin toggles are off', function () {
    allowPrivacy();

    expect(VideoPrivacy::allowedFor(User::factory()->create()))->toBe(['public']);
});

test('each toggle unlocks only its own option', function () {
    $user = User::factory()->create();

    allowPrivacy(unlisted: true);
    expect(VideoPrivacy::allowedFor($user))->toBe(['public', 'unlisted']);

    allowPrivacy(private: true);
    expect(VideoPrivacy::allowedFor($user))->toBe(['public', 'private']);
});

test('admins can always choose any privacy', function () {
    allowPrivacy();

    expect(VideoPrivacy::allowedFor(User::factory()->admin()->create()))->toBe(['public', 'unlisted', 'private']);
});

test('a video keeps its current privacy selectable after the toggle is turned off', function () {
    allowPrivacy();
    $video = Video::factory()->private()->create();

    expect(VideoPrivacy::allowedFor($video->user, $video))->toBe(['public', 'private']);
});

test('the upload page receives the allowed privacy options', function () {
    allowPrivacy(unlisted: true);
    asUser();

    $page = inertiaPagePayload($this->get('/upload'));

    expect($page['props']['privacyOptions'])->toBe(['public', 'unlisted']);
});

// ── Upload ──────────────────────────────────────────────────────────────────

test('finalize defaults to public when no privacy is sent', function () {
    Queue::fake();
    Storage::fake('public');
    $user = asUser();

    $this->postJson('/upload/finalize', finalizePayload(stageAssembledUpload($user)))->assertOk();

    expect(Video::where('user_id', $user->id)->sole()->privacy)->toBe('public');
});

test('finalize rejects private uploads while the toggle is off', function () {
    Queue::fake();
    Storage::fake('public');
    allowPrivacy(unlisted: true);
    $user = asUser();

    $this->postJson('/upload/finalize', finalizePayload(stageAssembledUpload($user), ['privacy' => 'private']))
        ->assertStatus(422)
        ->assertJsonValidationErrors('privacy');

    expect(Video::where('user_id', $user->id)->exists())->toBeFalse();
});

test('finalize accepts private uploads once the toggle is on', function () {
    Queue::fake();
    Storage::fake('public');
    allowPrivacy(private: true);
    $user = asUser();

    $this->postJson('/upload/finalize', finalizePayload(stageAssembledUpload($user), ['privacy' => 'private']))->assertOk();

    expect(Video::where('user_id', $user->id)->sole()->privacy)->toBe('private');
});

// ── Changing privacy later ──────────────────────────────────────────────────

test('any owner can change privacy from the status page within the allowed options', function () {
    allowPrivacy(unlisted: true);
    $user = asUser();
    $video = Video::factory()->create(['user_id' => $user->id]);

    $this->put("/videos/{$video->id}/privacy", ['privacy' => 'unlisted'])->assertRedirect();

    expect($video->fresh()->privacy)->toBe('unlisted');
});

test('owners cannot pick a privacy the admin has not enabled', function () {
    allowPrivacy(unlisted: true);
    $user = asUser();
    $video = Video::factory()->create(['user_id' => $user->id]);

    $this->put("/videos/{$video->id}/privacy", ['privacy' => 'private'])->assertSessionHasErrors('privacy');

    expect($video->fresh()->privacy)->toBe('public');
});

test('other users cannot change a video\'s privacy', function () {
    allowPrivacy(unlisted: true);
    $video = Video::factory()->create();
    asUser();

    $this->put("/videos/{$video->id}/privacy", ['privacy' => 'unlisted'])->assertForbidden();
});

test('the pro edit form enforces the same privacy options', function () {
    allowPrivacy();
    $user = asUser(User::factory()->pro()->create());
    $video = Video::factory()->create(['user_id' => $user->id]);

    $this->put("/videos/{$video->id}", [
        'title' => 'Still Public',
        'privacy' => 'private',
    ])->assertSessionHasErrors('privacy');

    expect($video->fresh()->privacy)->toBe('public');
});
